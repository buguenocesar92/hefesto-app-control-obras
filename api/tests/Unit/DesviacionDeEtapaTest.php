<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Etapa;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * El atraso de una etapa: la cifra que se discute en la reunion de obra.
 *
 * Son pruebas UNITARIAS y no de caracteristicas porque la fecha de hoy se
 * inyecta: si el calculo usara `now()` por dentro, la prueba dependeria del
 * día en que se corra y dejaria de comprobar nada.
 *
 * Lo que se comprueba:
 *  - Positivo es atraso, negativo es adelanto.
 *  - Sin fecha real, el atraso se mide contra hoy.
 *  - Sin fecha planificada NO hay atraso que medir, y se devuelve null en vez
 *    de cero, porque "no hay con que comparar" y "esta en fecha" son cosas
 *    distintas y la interfaz las muestra distinto.
 */
class DesviacionDeEtapaTest extends TestCase
{
    public function test_una_terminada_despues_es_un_atraso(): void
    {
        $etapa = $this->etapa(
            planificada: '2026-08-15',
            real: '2026-08-20',
        );

        $this->assertSame(5, $etapa->desviacionDias());
        $this->assertTrue($etapa->estaAtrasada());
    }

    public function test_una_terminada_antes_es_un_adelanto(): void
    {
        $etapa = $this->etapa(
            planificada: '2026-08-15',
            real: '2026-08-10',
        );

        $this->assertSame(-5, $etapa->desviacionDias());
        $this->assertFalse($etapa->estaAtrasada(), 'Un adelanto no es un atraso.');
    }

    public function test_una_terminada_en_fecha_no_tiene_desviacion(): void
    {
        $etapa = $this->etapa(
            planificada: '2026-08-15',
            real: '2026-08-15',
        );

        $this->assertSame(0, $etapa->desviacionDias());
        $this->assertFalse($etapa->estaAtrasada());
    }

    public function test_sin_fecha_real_el_atraso_se_mide_contra_hoy(): void
    {
        $hoy = CarbonImmutable::parse('2026-10-17');

        // Planned para ayer y todavia sin fecha real: atraso en curso.
        $corriendo = $this->etapa(planificada: '2026-10-16', real: null);
        $this->assertSame(1, $corriendo->desviacionDias($hoy));
        $this->assertTrue($corriendo->estaAtrasada($hoy));

        // Todavia no ha llegado su fecha: no hay atraso.
        $aTiempo = $this->etapa(planificada: '2026-10-18', real: null);
        $this->assertSame(-1, $aTiempo->desviacionDias($hoy));
        $this->assertFalse($aTiempo->estaAtrasada($hoy));
    }

    public function test_sin_fecha_planificada_no_hay_desviacion(): void
    {
        $etapa = $this->etapa(planificada: null, real: '2026-08-20');

        $this->assertNull($etapa->desviacionDias());
        $this->assertFalse($etapa->estaAtrasada(), 'Sin plan no hay atraso que medir.');
    }

    public function test_el_aporte_al_avance_pondera_por_el_peso_de_la_etapa(): void
    {
        // Peso 20 de la obra, avance 50: aporta 10 puntos porcentuales.
        $etapa = $this->etapa(planificada: '2026-08-15', real: null, peso: 20, avance: 50);

        $this->assertSame(10.0, $etapa->aporteAlAvanceFisico());
    }

    /** Una etapa sin base de datos: solo el modelo y los casts. */
    private function etapa(
        ?string $planificada,
        ?string $real,
        float|int $peso = 20,
        float|int $avance = 0,
    ): Etapa {
        $etapa = new Etapa();

        $etapa->fecha_planificada = $planificada === null ? null : CarbonImmutable::parse($planificada);
        $etapa->fecha_real = $real === null ? null : CarbonImmutable::parse($real);
        $etapa->peso = (string) $peso;
        $etapa->avance_real_pct = (string) $avance;

        return $etapa;
    }
}