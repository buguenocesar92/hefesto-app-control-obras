<?php

declare(strict_types=1);

namespace Tests\Feature\Avances;

use App\Enums\EstadoAvancePago;
use App\Models\AvancePago;
use App\Models\Etapa;
use App\Models\Obra;
use App\Services\Avance\CalculadorAvanceFinanciero;
use App\Services\Avance\CalculadorAvanceFisico;
use App\Services\Avance\EstadoDeCuentaObra;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AVANCE FISICO Y AVANCE FINANCIERO SON DOS CIFRAS DISTINTAS
 *
 * Se puede tener 60% de avance fisico y 45% cobrado. El motivo de que este
 * sistema las separe no es puramente técnico: si se promedian, el número
 * resultante no describe ni la obra ni el dinero, y un informe que no describe
 * ninguna de las dos cosas sirve para lo único que no se debe, que es dar por
 * cierto un avance que nadie verifico.
 *
 * Lo que comprueban estas pruebas:
 *  1. El avance fisico se pondera por el peso de cada etapa.
 *  2. El avance financiero cuenta SOLO lo pagado, no lo aprobado.
 *  3. Los dos se reportan en bloques separados, con su fuente declarada.
 *  4. Nadie promedia los dos: la diferencia es una resta, no un punto medio.
 */
class AvanceFisicoYFinancieroTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_avance_fisico_se_pondera_por_el_peso_de_cada_etapa(): void
    {
        $obra = $this->obraConEtapas([
            // peso 40 al 50%  = 20 puntos
            ['peso' => 40, 'avance' => 50],
            // peso 30 al 100% = 30 puntos
            ['peso' => 30, 'avance' => 100],
            // peso 30 al 0%   =  0 puntos
            ['peso' => 30, 'avance' => 0],
        ]);

        $resumen = (new CalculadorAvanceFisico())->calcular($obra);

        // 20 + 30 + 0 = 50%
        $this->assertSame(50.0, $resumen->porcentaje);
        $this->assertSame(100.0, $resumen->sumaPesos);
        $this->assertSame(3, $resumen->etapasTotal);
    }

    public function test_el_avance_financial_cuenta_solo_lo_pagado(): void
    {
        $obra = $this->obraConPagos(2400.00, [
            ['neto' => 540.00, 'cobrado' => 540.00, 'estado' => EstadoAvancePago::Pagado],
            ['neto' => 540.00, 'cobrado' => 0, 'estado' => EstadoAvancePago::Aprobado],
            ['neto' => 540.00, 'cobrado' => 540.00, 'estado' => EstadoAvancePago::Pagado],
            ['neto' => 200.00, 'cobrado' => 0, 'estado' => EstadoAvancePago::Emitido],
        ]);

        $resumen = (new CalculadorAvanceFinanciero())->calcular($obra);

        // Cobrado: 540 + 540 = 1080 de 2400 = 45%.
        // El pago APROBADO de 540 NO cuenta: aprobado no es cobrado.
        $this->assertSame(45.0, $resumen->porcentaje);
        $this->assertSame(1080.00, $resumen->montoCobrado);
        $this->assertSame(1320.00, $resumen->saldoPorCobrar);

        // Pero el aprobado y el por emitir se reportan aparte: son reales, no
        // son cobrados.
        $this->assertSame(540.00, $resumen->aprobadoNoCobrado);
        $this->assertSame(200.00, $resumen->porEmitir);
        $this->assertSame(2, $resumen->lineasPagadas);
        $this->assertSame(4, $resumen->lineasTotales);
    }

    public function test_una_obra_puede_tener_73_fisico_y_45_cobrado_y_son_dos_cifras(): void
    {
        $obra = $this->obraConEtapas([
            ['peso' => 30, 'avance' => 100],   // 30 puntos
            ['peso' => 35, 'avance' => 100],   // 35 puntos
            ['peso' => 20, 'avance' => 40],    //  8 puntos
            ['peso' => 15, 'avance' => 0],     //  0 puntos
        ], monto: 2400.00);

        $this->obraConPagos(2400.00, [
            ['neto' => 540.00, 'cobrado' => 540.00, 'estado' => EstadoAvancePago::Pagado],
            ['neto' => 540.00, 'cobrado' => 0, 'estado' => EstadoAvancePago::Aprobado],
            ['neto' => 540.00, 'cobrado' => 540.00, 'estado' => EstadoAvancePago::Pagado],
        ], obra: $obra);

        $fisico = (new CalculadorAvanceFisico())->calcular($obra);
        $financiero = (new CalculadorAvanceFinanciero())->calcular($obra);

        $this->assertSame(73.0, $fisico->porcentaje);
        $this->assertSame(45.0, $financiero->porcentaje);

        // Y no se promedian: el promedio daría 59, que no es ni una cosa ni la
        // otra.
        $this->assertNotSame(
            round(($fisico->porcentaje + $financiero->porcentaje) / 2, 2),
            $fisico->porcentaje
        );
    }

    public function test_el_estado_de_cuenta_reporta_los_dos_avances_por_separado_con_su_fuente(): void
    {
        $obra = $this->obraConEtapas([
            ['peso' => 50, 'avance' => 60],
            ['peso' => 50, 'avance' => 60],
        ], monto: 1000.00);

        $this->obraConPagos(1000.00, [
            ['neto' => 450.00, 'cobrado' => 450.00, 'estado' => EstadoAvancePago::Pagado],
        ], obra: $obra);

        $servicio = new EstadoDeCuentaObra(
            new CalculadorAvanceFisico(),
            new CalculadorAvanceFinanciero()
        );

        $cuenta = $servicio->paraObra($obra->fresh());

        // 60% fisico contra 45% cobrado, en dos bloques distintos.
        $this->assertSame(60.0, $cuenta['avance_fisico']['porcentaje']);
        $this->assertSame(45.0, $cuenta['avance_financiero']['porcentaje']);

        // Cada bloque declara de donde sale su número.
        $this->assertSame('etapas', $cuenta['avance_fisico']['fuente']);
        $this->assertSame('avances_pago', $cuenta['avance_financiero']['fuente']);

        // La diferencia es una RESTA, no un promedio.
        $this->assertSame(15.0, $cuenta['diferencia_puntos_porcentuales']);

        // Y no existe ningun campo con un avance "único".
        $this->assertArrayNotHasKey('avance', $cuenta);
        $this->assertArrayNotHasKey('avance_total', $cuenta);
    }

    public function test_sin_monto_contratado_el_avance_financiero_es_null_y_no_cero(): void
    {
        $obra = $this->obraConEtapas([
            ['peso' => 100, 'avance' => 50],
        ], monto: 0);

        $resumen = (new CalculadorAvanceFinanciero())->calcular($obra);

        // Un 0% cobrado sobre un contrato de cero es un 0% sin significado.
        $this->assertNull($resumen->porcentaje);
    }

    public function test_sin_etapas_con_peso_el_avance_fisico_es_null_y_no_cero(): void
    {
        $obra = $this->obraConEtapas([], monto: 1000.00);

        $resumen = (new CalculadorAvanceFisico())->calcular($obra->fresh());

        $this->assertNull($resumen->porcentaje);
        $this->assertSame(0, $resumen->etapasTotal);
    }

    public function test_el_calculo_del_avance_fisico_no_usa_los_pagos(): void
    {
        $obra = $this->obraConEtapas([
            ['peso' => 100, 'avance' => 20],
        ], monto: 1000.00);

        // Cobrar el 100% de la obra no construye nada.
        $this->obraConPagos(1000.00, [
            ['neto' => 1000.00, 'cobrado' => 1000.00, 'estado' => EstadoAvancePago::Pagado],
        ], obra: $obra);

        $fisico = (new CalculadorAvanceFisico())->calcular($obra->fresh());
        $financiero = (new CalculadorAvanceFinanciero())->calcular($obra->fresh());

        $this->assertSame(20.0, $fisico->porcentaje, 'El avance fisico no puede depender de los pagos.');
        $this->assertSame(100.0, $financiero->porcentaje);
    }

    public function test_el_calculo_del_avance_financiero_no_usa_las_etapas(): void
    {
        // Una obra terminada, sin un solo pago emitido.
        $obra = $this->obraConEtapas([
            ['peso' => 100, 'avance' => 100],
        ], monto: 1000.00);

        $financiero = (new CalculadorAvanceFinanciero())->calcular($obra->fresh());

        $this->assertSame(0.0, $financiero->porcentaje, 'Terminar la obra no es cobrarla.');
        $this->assertSame(1000.00, $financiero->saldoPorCobrar);
    }

    /**
     * @param  list<array{peso: int|float, avance: int|float}>  $etapas
     */
    private function obraConEtapas(array $etapas, float $monto = 0.0, ?Obra $obra = null): Obra
    {
        $obra ??= $this->crearObra($monto);

        $orden = 1;

        foreach ($etapas as $etapa) {
            Etapa::query()->create([
                'tenant_id' => $obra->tenant_id,
                'obra_id' => $obra->id,
                'orden' => $orden,
                'nombre' => 'Etapa ' . $orden,
                'avance_real_pct' => $etapa['avance'],
                'peso' => $etapa['peso'],
                'estado' => 'pendiente',
            ]);

            $orden++;
        }

        return $obra->fresh();
    }

    /**
     * @param  list<array{neto: float, cobrado: float, estado: EstadoAvancePago}>  $pagos
     */
    private function obraConPagos(float $contratado, array $pagos, ?Obra $obra = null): Obra
    {
        $obra ??= $this->crearObra($contratado);

        $numero = 1;

        foreach ($pagos as $pago) {
            AvancePago::query()->create([
                'tenant_id' => $obra->tenant_id,
                'obra_id' => $obra->id,
                'numero' => $numero,
                'fecha_emision' => now()->subMonths(2)->toDateString(),
                'monto_bruto' => $pago['neto'],
                'deducciones' => 0,
                'monto_neto' => $pago['neto'],
                'monto_cobrado' => $pago['cobrado'],
                'fecha_cobro' => $pago['cobrado'] > 0 ? now()->subMonth()->toDateString() : null,
                'estado' => $pago['estado']->value,
            ]);

            $numero++;
        }

        return $obra->fresh();
    }

    private function crearObra(float $monto): Obra
    {
        $ctx = $this->crearEmpresaConUsuario('avances');

        return Obra::factory()->create([
            'tenant_id' => $ctx['empresa']->id,
            'monto_contratado' => $monto,
        ]);
    }
}