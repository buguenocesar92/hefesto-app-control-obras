<?php

declare(strict_types=1);

namespace Tests\Feature\Inspecciones;

use App\Enums\EstadoHallazgo;
use App\Enums\EstadoTarea;
use App\Enums\GravedadHallazgo;
use App\Enums\OrigenTarea;
use App\Models\Hallazgo;
use App\Models\Inspeccion;
use App\Models\Obra;
use App\Models\Tarea;
use App\Models\TipoInspeccion;
use App\Services\Inspecciones\GeneradorTareasCorreccion;
use App\Services\Tenancy\ContextoTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA REGLA CENTRAL DEL MÓDULO DE INSPECCIONES
 *
 * Un hallazgo de una inspección genera una tarea de corrección CON PLAZO.
 *
 * Sin ese vinculo, la inspección es un papel que nadie vuelve a abrir: el
 * hallazgo queda anotado, el acta se archiva, y el problema sigue en la obra.
 * Con el vinculo, el hallazgo arrastra a una tarea con `fecha_plazo` y
 * `responsable_id`, que es la única forma de que la observacion termine en
 * algo.
 *
 * Estas pruebas comprueban que el vinculo exista, que el plazo se calcule
 * según la gravedad, y que no se pueda duplicar.
 */
class HallazgoGeneraTareaTest extends TestCase
{
    use RefreshDatabase;

    private GeneradorTareasCorreccion $generador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generador = new GeneradorTareasCorreccion(new ContextoTenant());
    }

    public function test_un_hallazgo_genera_una_tarea_de_correccion_con_plazo_y_responsable(): void
    {
        $ctx = $this->entorno();

        $hallazgo = $this->crearHallazgo($ctx, GravedadHallazgo::Media);

        $tarea = $this->generador->paraHallazgo($hallazgo, (string) $ctx['maestro']->id);

        $this->assertNotNull($tarea->fecha_plazo, 'Una correccion sin fecha de plazo es una observacion decorativa.');
        $this->assertSame($ctx['maestro']->id, $tarea->responsable_id);
        $this->assertSame(OrigenTarea::CorreccionInspeccion, $tarea->origen);
        $this->assertSame($hallazgo->inspeccion_id, $tarea->origen_inspeccion_id);
        $this->assertSame($hallazgo->id, $tarea->origen_hallazgo_id);
        $this->assertSame($ctx['obra']->id, $tarea->obra_id);
        $this->assertSame(EstadoTarea::Pendiente, $tarea->estado);
    }

    public function test_el_vinculo_esta_escrito_en_los_dos_sentidos(): void
    {
        $ctx = $this->entorno();
        $hallazgo = $this->crearHallazgo($ctx, GravedadHallazgo::Alta);

        $tarea = $this->generador->paraHallazgo($hallazgo, (string) $ctx['maestro']->id);

        // Desde el hallazgo: se ve la tarea y su fecha.
        $hallazgo->refresh();

        $this->assertSame($tarea->id, $hallazgo->tarea_correccion_id);
        $this->assertNotNull($hallazgo->tareaCorreccion);
        $this->assertSame($tarea->fecha_plazo?->toDateString(), $hallazgo->tareaCorreccion->fecha_plazo?->toDateString());

        // Y desde la tarea se vuelve al hallazgo y a la inspección.
        $this->assertSame($hallazgo->id, $tarea->hallazgoOrigen->id);
        $this->assertNotNull($tarea->inspeccionOrigen);
    }

    public function test_el_plazo_depende_de_la_gravedad(): void
    {
        $ctx = $this->entorno();

        $grave = $this->generador->paraHallazgo(
            $this->crearHallazgo($ctx, GravedadHallazgo::Alta),
            (string) $ctx['maestro']->id
        );

        $leve = $this->generador->paraHallazgo(
            $this->crearHallazgo($ctx, GravedadHallazgo::Baja),
            (string) $ctx['maestro']->id
        );

        // Más gravedad, menos días: un hallazgo critico no puede quedar para
        // dentro de dos semanas.
        $this->assertLessThan(
            $leve->fecha_plazo,
            $grave->fecha_plazo,
            'Un hallazgo de gravedad alta no puede tener el mismo plazo que uno bajo.'
        );
    }

    public function test_el_plazo_se_cuenta_en_dias_habiles(): void
    {
        $ctx = $this->entorno();

        // El generador NO cuenta sabado ni domingo.
        $viernes = CarbonImmutable::parse('2026-10-16'); // viernes
        $this->assertTrue($viernes->isFriday());

        $plazo = $this->generador->fechaPlazo($viernes, 1);

        $this->assertSame('2026-10-19', $plazo->toDateString(), 'Un dia habil despues de un viernes es el lunes.');
        $this->assertFalse($plazo->isWeekend());
    }

    public function test_generar_dos_veces_no_duplica_la_tarea(): void
    {
        $ctx = $this->entorno();
        $hallazgo = $this->crearHallazgo($ctx, GravedadHallazgo::Media);

        $primera = $this->generador->paraHallazgo($hallazgo, (string) $ctx['maestro']->id);
        $segunda = $this->generador->paraHallazgo($hallazgo->refresh(), (string) $ctx['maestro']->id);

        $this->assertSame($primera->id, $segunda->id);

        $this->assertSame(
            1,
            Tarea::query()->where('origen_hallazgo_id', $hallazgo->id)->count(),
            'Un hallazgo no puede generar dos tareas de correccion.'
        );
    }

    public function test_no_se_puede_generar_una_correccion_sin_plazo(): void
    {
        $ctx = $this->entorno();
        $hallazgo = $this->crearHallazgo($ctx, GravedadHallazgo::Media);

        $this->expectException(\InvalidArgumentException::class);

        $this->generador->paraHallazgo($hallazgo, (string) $ctx['maestro']->id, 0);
    }

    public function test_el_hallazgo_se_cierra_cuando_su_tarea_se_completa(): void
    {
        $ctx = $this->entorno();
        $hallazgo = $this->crearHallazgo($ctx, GravedadHallazgo::Alta);

        $tarea = $this->generador->paraHallazgo($hallazgo, (string) $ctx['maestro']->id);

        $this->assertSame(EstadoHallazgo::EnProceso, $hallazgo->fresh()->estado);

        $tarea->update(['estado' => EstadoTarea::Completada->value, 'fecha_cierre' => now()]);

        $this->assertTrue($hallazgo->fresh()->seCierraPorTareaCompletada());
    }

    public function test_un_hallazgo_de_otra_constructora_no_genera_tarea_aqui(): void
    {
        $primera = $this->entorno();
        $hallazgo = $this->crearHallazgo($primera, GravedadHallazgo::Media);

        $segunda = $this->crearEmpresaConUsuario('segunda');
        $this->fijarEmpresa($segunda['id']);

        // Con la segunda empresa en el contexto, el hallazgo de la primera no
        // existe. El generador no puede "adivinarla".
        $this->assertNull(Hallazgo::find($hallazgo->id));
        $this->assertSame(0, Tarea::query()->count());
    }

    /**
     * Monta empresa, personas, obra e inspección, y deja el contexto fijado.
     *
     * @return array{empresa: \App\Models\Tenant, usuario: \App\Models\Usuario, maestro: \App\Models\Usuario, obra: Obra, inspección: Inspección, tipo: TipoInspeccion}
     */
    private function entorno(): array
    {
        $ctx = $this->crearEmpresaConUsuario('prueba');

        $maestro = \App\Models\Usuario::query()->create([
            'tenant_id' => $ctx['empresa']->id,
            'nombre' => 'Maestro de prueba',
            'correo' => 'maestro@prueba.cl',
            'rol' => 'maestro',
            'activo' => true,
        ]);

        $obra = Obra::factory()->create(['tenant_id' => $ctx['empresa']->id]);

        $tipo = TipoInspeccion::query()->create([
            'tenant_id' => $ctx['empresa']->id,
            'nombre' => 'Inspeccion de fundaciones',
            'activo' => true,
        ]);

        $inspeccion = Inspeccion::query()->create([
            'tenant_id' => $ctx['empresa']->id,
            'obra_id' => $obra->id,
            'tipo_inspeccion_id' => $tipo->id,
            'fecha_inspeccion' => now()->subDays(2)->toDateString(),
            'inspector_id' => $ctx['usuario']->id,
            'resultado' => 'con_hallazgos',
        ]);

        return [
            'empresa' => $ctx['empresa'],
            'usuario' => $ctx['usuario'],
            'maestro' => $maestro,
            'obra' => $obra,
            'inspeccion' => $inspeccion,
            'tipo' => $tipo,
        ];
    }

    /** @param array<string, mixed> $ctx */
    private function crearHallazgo(array $ctx, GravedadHallazgo $gravedad): Hallazgo
    {
        return Hallazgo::query()->create([
            'tenant_id' => $ctx['empresa']->id,
            'inspeccion_id' => $ctx['inspeccion']->id,
            'descripcion' => 'Armadura expuesta en el sector norte.',
            'gravedad' => $gravedad->value,
            'estado' => EstadoHallazgo::Pendiente->value,
        ]);
    }
}