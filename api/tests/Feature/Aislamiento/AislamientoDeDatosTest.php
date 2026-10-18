<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Obra;
use App\Models\Tarea;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PRUEBAS CRITICAS: una constructora no puede ver los datos de otra.
 *
 * Estas pruebas van contra PostgreSQL de verdad, porque el aislamiento esta
 * implementado con Row Level Security. Contra SQLite PASARIAN sin comprobar
 * nada. Ver phpunit.xml.
 *
 * Lo que se comprueba aca:
 *
 *  1. Sin contexto de empresa, las tablas de negocio devuelven CERO filas y no
 *     fallan en silencio.
 *  2. Con el contexto de otra empresa, una consulta devuelve SOLO sus filas.
 *  3. Una obra ajena no existe para esta empresa (404, no 403).
 *  4. No se puede escribir una fila en nombre de otra empresa.
 *  5. El contexto NO sobrevive a la transacción.
 *
 * La 5 es la más importante para el worker de colas, que reutiliza la misma
 * conexión para trabajos de empresas distintas.
 */
class AislamientoDeDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_contexto_de_empresa_la_consulta_falla_en_vez_de_traer_datos(): void
    {
        $primera = $this->crearEmpresaConUsuario('primera');
        Obra::factory()->create(['tenant_id' => $primera['empresa']->id]);

        // Se limpia el contexto a propósito: es la situacion de una consulta
        // que se escapa del middleware.
        //
        // Lo que se espera NO es "cero filas", sino un ERROR. `app_tenant_id()`
        // lanza excepción cuando no hay empresa, y la política aborta. Devolver
        // cero filas sería más comodo de probar, pero dejaria pasar el caso
        // peligroso: que alguien "arregle" la funcion devolviendo NULL y
        // termine leyendo filas de la constructora equivocada sin que nada lo
        // delate. Ahí el aislamiento falla abierto.
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, '']);

        $this->expectException(QueryException::class);

        Obra::query()->count();
    }

    public function test_cada_constructora_solo_ve_sus_propias_obras(): void
    {
        $primera = $this->crearEmpresaConUsuario('primera');
        Obra::factory()->count(3)->create(['tenant_id' => $primera['empresa']->id]);

        $segunda = $this->crearEmpresaConUsuario('segunda');
        Obra::factory()->count(2)->create(['tenant_id' => $segunda['empresa']->id]);

        $this->fijarEmpresa($primera['id']);
        $this->assertSame(3, Obra::query()->count());
        $this->assertSame(
            $primera['id'],
            Obra::query()->value('tenant_id'),
            'Una obra de otra constructora se colaria en el listado.'
        );

        $this->fijarEmpresa($segunda['id']);
        $this->assertSame(2, Obra::query()->count());
        $this->assertSame($segunda['id'], Obra::query()->value('tenant_id'));
    }

    public function test_la_obra_de_otra_constructora_no_se_puede_leer_ni_escribir(): void
    {
        $primera = $this->crearEmpresaConUsuario('primera');
        $segunda = $this->crearEmpresaConUsuario('segunda');

        $obraAjena = Obra::factory()->create(['tenant_id' => $segunda['empresa']->id]);

        // Volvemos al contexto de la primera constructora.
        $this->fijarEmpresa($primera['id']);

        $this->assertNull(Obra::find($obraAjena->id));

        // Y tampoco se puede tocar: la política tiene WITH CHECK.
        $this->expectException(QueryException::class);

        DB::table('obras')
            ->where('id', $obraAjena->id)
            ->update(['nombre' => 'obra secuestrada']);
    }

    public function test_no_se_puede_insertar_una_fila_con_la_empresa_de_otra(): void
    {
        $primera = $this->crearEmpresaConUsuario('primera');
        $segunda = $this->crearEmpresaConUsuario('segunda');

        // Contexto de la primera constructora, pero se intenta guardar una
        // obra de la segunda. La política WITH CHECK lo tiene que impedir.
        $this->fijarEmpresa($primera['id']);

        $this->expectException(QueryException::class);

        Obra::query()->create([
            'tenant_id' => $segunda['empresa']->id,
            'codigo' => 'OB-99999',
            'nombre' => 'Obra con empresa ajena',
            'cliente_nombre' => 'Cliente de ejemplo',
            'direccion' => 'Direccion de ejemplo',
            'tipo' => 'Edificacion',
            'fecha_inicio' => '2026-01-01',
            'monto_contratado' => 100,
            'estado' => 'planificacion',
        ]);
    }

    public function test_el_contexto_de_empresa_no_sobrevive_a_la_transaccion(): void
    {
        $empresa = $this->crearEmpresaConUsuario('primera');
        Obra::factory()->create(['tenant_id' => $empresa['empresa']->id]);

        $contexto = new ContextoTenant();

        $conteo = $contexto->ejecutarAislado(
            $empresa['id'],
            fn (): int => Obra::query()->count()
        );

        $this->assertSame(1, $conteo);
        $this->assertNull(
            $contexto->empresaIdEnConexion(),
            'La variable de contexto debe desaparecer al terminar la transaccion. '
            . 'Si queda, el worker de colas podria leer los datos de otra constructora.'
        );
    }

    public function test_el_contexto_de_empresa_se_descarta_tambien_al_revertir(): void
    {
        $empresa = $this->crearEmpresaConUsuario('primera');

        $contexto = new ContextoTenant();

        // Transacción que falla y se revierte: el valor también tiene que
        // desaparecer, porque un rollback deja la conexión como estaba.
        try {
            $contexto->ejecutarAislado($empresa['id'], static function (): void {
                throw new \RuntimeException('fallo controlado');
            });
        } catch (\RuntimeException) {
            // Se espera: la excepción es lo que dispara el rollback.
        }

        $this->assertNull($contexto->empresaIdEnConexion());
    }

    public function test_la_funcion_de_contexto_falla_cerrada_sin_empresa(): void
    {
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, '']);

        $this->expectException(QueryException::class);

        DB::select('SELECT public.app_tenant_id()');
    }

    public function test_las_tareas_de_otra_constructora_tampoco_se_ven(): void
    {
        $primera = $this->crearEmpresaConUsuario('primera');
        $segunda = $this->crearEmpresaConUsuario('segunda');

        $obraAjena = Obra::factory()->create(['tenant_id' => $segunda['empresa']->id]);

        Tarea::factory()->count(2)->create([
            'tenant_id' => $segunda['empresa']->id,
            'obra_id' => $obraAjena->id,
        ]);

        $this->fijarEmpresa($primera['id']);

        $this->assertSame(0, Tarea::query()->count());
    }
}