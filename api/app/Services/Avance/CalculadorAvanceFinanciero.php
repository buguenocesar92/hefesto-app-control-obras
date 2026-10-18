<?php

declare(strict_types=1);

namespace App\Services\Avance;

use App\Enums\EstadoAvancePago;
use App\Models\AvancePago;
use App\Models\Obra;

/**
 * AVANCE FINANCIERO: cuanta plata ha entrado de verdad.
 *
 * Se calcula Únicamente desde los avances de pago y desde
 * `obras.monto_contratado`. No lee etapas, no lee hallazgos y no guarda nada
 * en la obra.
 *
 * POR QUE SOLO CUENTA LO PAGADO
 * -----------------------------
 * Un estado de cuenta aprobado no es plata. Si el estado `aprobado` contara
 * como cobrado, el informe mostraria una cifra que el cliente nunca vio, y en
 * una reunion de obra eso es exactamente el tipo de sorpresa que hace que
 * deje de creerse en los informes. Por eso `aprobado_no_cobrado` se reporta
 * aparte: es real, pero es plata que todavia no esta en la obra.
 *
 * Esta clase y `CalculadorAvanceFisico` no se llaman entre si. Es deliberado:
 * las dos cifras nunca se mezclan, y su diferencia la calcula
 * `EstadoDeCuentaObra` sin promediarlas.
 */
class CalculadorAvanceFinanciero
{
    public function calcular(Obra $obra): ResumenAvanceFinanciero
    {
        /** @var list<AvancePago> $pagos */
        $pagos = $obra->avancesPago()->get()->all();

        $contratado = (float) $obra->monto_contratado;

        $cobrado = 0.0;
        $porEmitir = 0.0;
        $aprobadoNoCobrado = 0.0;
        $pagadas = 0;

        foreach ($pagos as $pago) {
            $cobrado += $pago->montoParaAvanceFinanciero();

            match ($pago->estado) {
                EstadoAvancePago::Emitido => $porEmitir += (float) $pago->monto_neto,
                EstadoAvancePago::Aprobado => $aprobadoNoCobrado += (float) $pago->monto_neto,
                default => null,
            };

            if ($pago->estado->cuentaComoCobrado()) {
                $pagadas++;
            }
        }

        $cobrado = round($cobrado, 2);

        // Sin monto contratado no hay porcentaje que calcular: la division
        // daría cualquier cosa. Se devuelve null, no cero.
        $porcentaje = $contratado > 0.0 ? round(($cobrado / $contratado) * 100, 2) : null;

        return new ResumenAvanceFinanciero(
            porcentaje: $porcentaje,
            montoContratado: $contratado,
            montoCobrado: $cobrado,
            saldoPorCobrar: round(max($contratado - $cobrado, 0.0), 2),
            porEmitir: round($porEmitir, 2),
            aprobadoNoCobrado: round($aprobadoNoCobrado, 2),
            lineasPagadas: $pagadas,
            lineasTotales: count($pagos),
        );
    }
}