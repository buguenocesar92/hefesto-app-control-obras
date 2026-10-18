<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use App\Enums\ClaseRegistroSeguridad;
use App\Enums\RolUsuario;
use App\Models\Obra;
use App\Models\RegistroSeguridad;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UN CASI ACCIDENTE NO ES UN ACCIDENTE, Y SE REGISTRA DISTINTO
 *
 * No es una distincion de forma. El casi accidente es el dato que permite
 * prevenir antes de que se repita el accidente. Si los dos se mezclaran en una
 * sola categoria, el casi accidente se diluiria dentro de "no paso nada", que
 * es exactamente como se pierde.
 *
 * Estas pruebas comprueban tres cosas:
 *  1. Que la clase se elija al escribir y tenga reglas propias.
 *  2. Que un casi accidente con horas perdidas NO se pueda registrar (si las
 *     tiene, es un accidente y hay que decirlo).
 *  3. Que los dos clases se cuenten por separado y no se sumen.
 */
class RegistroDeSeguridadTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_casi_accidente_no_puede_tener_lesionado_ni_horas_perdidas(): void
    {
        $ctx = $this->entorno();

        $respuesta = $this->actingAs($ctx['usuario'])
            ->postJson('/api/v1/obras/' . $ctx['obra']->id . '/registros-seguridad', [
                'obra_id' => $ctx['obra']->id,
                'clase' => ClaseRegistroSeguridad::CasiAccidente->value,
                'fecha_ocurrencia' => now()->subHour()->toIso8601String(),
                'descripcion' => 'Un puntal cayo al soltarlo. No alcanzo a nadie.',
                'lesionado' => true,
                'horas_perdidas' => 8,
                'medidas_correctivas' => 'Se exige trabar los puntales.',
                'reportado_por_id' => $ctx['usuario']->id,
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['lesionado', 'horas_perdidas']);
    }

    public function test_un_accidente_exige_lesionado_y_horas_perdidas(): void
    {
        $ctx = $this->entorno();

        $respuesta = $this->actingAs($ctx['usuario'])
            ->postJson('/api/v1/obras/' . $ctx['obra']->id . '/registros-seguridad', [
                'obra_id' => $ctx['obra']->id,
                'clase' => ClaseRegistroSeguridad::Accidente->value,
                'fecha_ocurrencia' => now()->subHour()->toIso8601String(),
                'descripcion' => 'Caida desde 1,8 metros.',
                'lesionado' => false,
                'horas_perdidas' => 0,
                'reportado_por_id' => $ctx['usuario']->id,
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['lesionado', 'horas_perdidas']);
    }

    public function test_un_casi_accidente_valido_se_registra_sin_lesion(): void
    {
        $ctx = $this->entorno();

        $respuesta = $this->actingAs($ctx['usuario'])
            ->postJson('/api/v1/obras/' . $ctx['obra']->id . '/registros-seguridad', [
                'obra_id' => $ctx['obra']->id,
                'clase' => ClaseRegistroSeguridad::CasiAccidente->value,
                'fecha_ocurrencia' => now()->subHour()->toIso8601String(),
                'descripcion' => 'Un puntal cayo al soltarlo. No alcanzo a nadie.',
                'lesionado' => false,
                'horas_perdidas' => 0,
                'medidas_correctivas' => 'Se exige trabar los puntales antes de cada tanda.',
                'reportado_por_id' => $ctx['usuario']->id,
            ]);

        $respuesta->assertStatus(201);
        $respuesta->assertJsonPath('data.clase', 'casi_accidente');
        $respuesta->assertJsonPath('data.es_accidente', false);
    }

    public function test_accidentes_y_casi_accidentes_se_cuentan_por_separado(): void
    {
        $ctx = $this->entorno();

        RegistroSeguridad::query()->create([
            'tenant_id' => $ctx['empresa']->id,
            'obra_id' => $ctx['obra']->id,
            'clase' => ClaseRegistroSeguridad::Accidente->value,
            'fecha_ocurrencia' => now()->subDays(5),
            'descripcion' => 'Caida desde 1,8 metros al retirar un puntal.',
            'lesionado' => true,
            'horas_perdidas' => 24,
            'anonizado' => true,
            'medidas_correctivas' => 'Lineas de vida instaladas.',
            'reportado_por_id' => $ctx['usuario']->id,
        ]);

        RegistroSeguridad::query()->create([
            'tenant_id' => $ctx['empresa']->id,
            'obra_id' => $ctx['obra']->id,
            'clase' => ClaseRegistroSeguridad::CasiAccidente->value,
            'fecha_ocurrencia' => now()->subDays(2),
            'descripcion' => 'Un puntal cayo al soltarlo, por no estar trabado.',
            'lesionado' => false,
            'horas_perdidas' => 0,
            'anonizado' => true,
            'medidas_correctivas' => 'Se exige trabar los puntales.',
            'reportado_por_id' => $ctx['usuario']->id,
        ]);

        $respuesta = $this->actingAs($ctx['usuario'])
            ->getJson('/api/v1/obras/' . $ctx['obra']->id . '/registros-seguridad');

        $respuesta->assertStatus(200);
        $respuesta->assertJsonPath('resumen.accidentes', 1);
        $respuesta->assertJsonPath('resumen.casi_accidentes', 1);
    }

    public function test_un_registro_anonimizado_no_puede_traer_nombre_ni_rut(): void
    {
        $ctx = $this->entorno();

        $respuesta = $this->actingAs($ctx['usuario'])
            ->postJson('/api/v1/obras/' . $ctx['obra']->id . '/registros-seguridad', [
                'obra_id' => $ctx['obra']->id,
                'clase' => ClaseRegistroSeguridad::Accidente->value,
                'fecha_ocurrencia' => now()->subHour()->toIso8601String(),
                'descripcion' => 'Caida desde 1,8 metros.',
                'lesionado' => true,
                'horas_perdidas' => 24,
                'anonizado' => true,
                'persona_afectada_nombre' => 'Nombre que no se puede guardar',
                'reportado_por_id' => $ctx['usuario']->id,
            ]);

        $respuesta->assertStatus(422);
        $respuesta->assertJsonValidationErrors(['anonizado']);
    }

    public function test_anonizar_quita_el_dato_personal_y_conserva_el_hecho(): void
    {
        $ctx = $this->entorno();

        $registro = RegistroSeguridad::query()->create([
            'tenant_id' => $ctx['empresa']->id,
            'obra_id' => $ctx['obra']->id,
            'clase' => ClaseRegistroSeguridad::Accidente->value,
            'fecha_ocurrencia' => now()->subDays(5),
            'descripcion' => 'Caida desde 1,8 metros al retirar un puntal.',
            'lesionado' => true,
            'horas_perdidas' => 24,
            'persona_afectada_nombre' => 'Trabajador de ejemplo',
            'persona_afectada_rut' => '11222333',
            'anonizado' => false,
            'medidas_correctivas' => 'Lineas de vida instaladas.',
            'reportado_por_id' => $ctx['usuario']->id,
        ]);

        $respuesta = $this->actingAs($ctx['usuario'])
            ->postJson('/api/v1/registros-seguridad/' . $registro->id . '/anonimizar');

        $respuesta->assertStatus(200);

        $registro->refresh();

        // El dato personal desaparece.
        $this->assertNull($registro->persona_afectada_nombre);
        $this->assertNull($registro->persona_afectada_rut);
        $this->assertTrue($registro->anonizado);

        // El hecho NO desaparece: eso es lo que sirve para que no se repita.
        $this->assertTrue($registro->esAccidente());
        $this->assertSame(24.0, (float) $registro->horas_perdidas);
        $this->assertNotSame('', $registro->descripcion);
    }

    public function test_el_cliente_no_registra_accidentes(): void
    {
        $ctx = $this->entorno();

        $cliente = Usuario::query()->create([
            'tenant_id' => $ctx['empresa']->id,
            'nombre' => 'Cliente de ejemplo',
            'correo' => 'cliente@prueba.cl',
            'rol' => RolUsuario::Cliente->value,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($cliente)
            ->postJson('/api/v1/obras/' . $ctx['obra']->id . '/registros-seguridad', [
                'obra_id' => $ctx['obra']->id,
                'clase' => ClaseRegistroSeguridad::CasiAccidente->value,
                'fecha_ocurrencia' => now()->subHour()->toIso8601String(),
                'descripcion' => 'Registro que el cliente no deberia poder crear.',
                'reportado_por_id' => $cliente->id,
            ]);

        $this->assertContains(
            $respuesta->status(),
            [403, 404, 422],
            'El cliente no deberia poder registrar accidentes.'
        );

        $this->assertSame(0, DB::table('registros_seguridad')->count());
    }

    /** @return array{empresa: \App\Models\Tenant, usuario: Usuario, obra: Obra} */
    private function entorno(): array
    {
        $ctx = $this->crearEmpresaConUsuario('seguridad');

        $obra = Obra::factory()->create(['tenant_id' => $ctx['empresa']->id]);

        return [
            'empresa' => $ctx['empresa'],
            'usuario' => $ctx['usuario'],
            'obra' => $obra,
        ];
    }
}