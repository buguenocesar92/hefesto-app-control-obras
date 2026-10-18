<?php

declare(strict_types=1);

namespace App\Services\Avance;

/**
 * Resultado del calculo del avance FINANCIERO de una obra.
 *
 * Viene de los pagos. No lee una sola etapa. Una obra puede tener 60% de
 * avance fisico y 45% cobrado: son dos cifras, y que no coincidan es
 * información, no un error que haya que promediar.
 *
 * `porcentaje` es null cuando `monto_contratado` es cero. En ese caso no se
 * inventa un 0: un 0% cobrado sobre un contrato de cero es un 0% sin
 * significado, y mostrarlo sería mentir con apariencia de dato.
 */
final readonly class ResumenAvanceFinanciero
{
    public function __construct(
        public ?float $porcentaje,
        public float $montoContratado,
        public float $montoCobrado,
        public float $saldoPorCobrar,
        public float $porEmitir,
        public float $aprobadoNoCobrado,
        public int $lineasPagadas,
        public int $lineasTotales,
    ) {}

    /** @return array<string, int|float|null> */
    public function aArray(): array
    {
        return [
            'porcentaje' => $this->porcentaje,
            'fuente' => 'avances_pago',
            'unidad' => 'porcentaje_cobrado',
            'monto_contratado' => $this->montoContratado,
            'monto_cobrado' => $this->montoCobrado,
            'saldo_por_cobrar' => $this->saldoPorCobrar,
            'por_emitir' => $this->porEmitir,
            'aprobado_no_cobrado' => $this->aprobadoNoCobrado,
            'lineas_pagadas' => $this->lineasPagadas,
            'lineas_totales' => $this->lineasTotales,
        ];
    }
}