<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una linea del estado de cuenta.
 *
 * `avance_fisico_al_emitir_pct` lleva un nombre distinto a propósito del
 * `avance_fisico` del estado de cuenta: el primero es la foto que se guardo al
 * emitir este pago, y el segundo es el avance fisico de la obra hoy. Si se
 * llamaran igual, alguien los confundiria y creeria que el avance de la obra
 * es el del último pago emitido.
 */
class AvancePagoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'estado' => $this->estado->value,
            'fecha_emision' => $this->fecha_emision?->toDateString(),
            'periodo_desde' => $this->periodo_desde?->toDateString(),
            'periodo_hasta' => $this->periodo_hasta?->toDateString(),
            'avance_fisico_al_emitir_pct' => $this->avance_fisico_pct === null
                ? null
                : (float) $this->avance_fisico_pct,
            'monto_bruto' => (float) $this->monto_bruto,
            'deducciones' => (float) $this->deducciones,
            'monto_neto' => (float) $this->monto_neto,
            'monto_cobrado' => (float) $this->monto_cobrado,
            'saldo' => $this->saldo(),
            'fecha_cobro' => $this->fecha_cobro?->toDateString(),
            'observaciones' => $this->observaciones,
        ];
    }
}