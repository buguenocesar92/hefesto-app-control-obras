<?php

declare(strict_types=1);

namespace App\Services\Avance;

use App\Models\Etapa;
use App\Models\Obra;
use Carbon\CarbonImmutable;

/**
 * AVANCE FISICO: lo que se ha construido.
 *
 * Se calcula Únicamente desde las etapas. No mira pagos, no mira montos y no
 * guarda nada en la obra, porque un avance guardado se queda viejo en el
 * momento en que alguien actualiza una etapa.
 *
 * LA FORMULA
 * ----------
 * Cada etapa tiene un `peso` (que parte de la obra representa) y un
 * `avance_real_pct` (que parte de esa etapa esta hecha). El aporte de una
 * etapa al avance de la obra es `peso * avance / 100`, en puntos porcentuales:
 * una etapa que pesa 20 y esta al 50 aporta 10 puntos.
 *
 * Se divide por la suma real de pesos en vez de asumir que da 100. Si el plan
 * esta mal cargado, el resultado sigue siendo proporcional en vez de
 * inflado, y `sumaPesos` queda a la vista para que el error se note.
 */
class CalculadorAvanceFisico
{
    public function calcular(Obra $obra, ?CarbonImmutable $hoy = null): ResumenAvanceFisico
    {
        /** @var list<Etapa> $etapas */
        $etapas = $obra->etapas()->get()->all();

        $sumaPesos = 0.0;
        $puntos = 0.0;
        $terminadas = 0;
        $atrasadas = 0;
        $mayorAtraso = null;

        foreach ($etapas as $etapa) {
            $peso = (float) $etapa->peso;

            $sumaPesos += $peso;
            $puntos += $etapa->aporteAlAvanceFisico();

            if ($etapa->fecha_real !== null) {
                $terminadas++;
            }

            if ($etapa->estaAtrasada($hoy)) {
                $atrasadas++;
            }

            $desviacion = $etapa->desviacionDias($hoy);

            if ($desviacion !== null && $desviacion > 0) {
                $mayorAtraso = $mayorAtraso === null ? $desviacion : max($mayorAtraso, $desviacion);
            }
        }

        // Sin pesos cargados no hay avance que calcular. Se devuelve null y
        // no cero, para que la interfaz pueda decir "sin plan cargado" en vez
        // de "0%".
        $porcentaje = $sumaPesos > 0.0 ? round(($puntos / $sumaPesos) * 100, 2) : null;

        return new ResumenAvanceFisico(
            porcentaje: $porcentaje,
            sumaPesos: round($sumaPesos, 2),
            etapasTotal: count($etapas),
            etapasTerminadas: $terminadas,
            etapasAtrasadas: $atrasadas,
            mayorAtrasoDias: $mayorAtraso,
        );
    }
}