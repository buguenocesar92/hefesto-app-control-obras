<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una tarea del tablero.
 *
 * `origen` y `hallazgo_origen` viajan siempre: son lo que permite responder
 * "esa observacion que se hizo el mes pasado, en que quedó" sin tener que ir
 * a buscar el acta de inspección.
 */
class TareaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'origen' => $this->origen->value,
            'fecha_plazo' => $this->fecha_plazo?->toDateString(),
            'fecha_cierre' => $this->fecha_cierre?->toIso8601String(),
            'vencida' => $this->estaVencida(),
            'dias_para_vencer' => $this->diasParaVencer(),
            'responsable' => $this->whenLoaded('responsable', fn () => [
                'id' => $this->responsable->id,
                'nombre' => $this->responsable->nombre,
            ]),
            'hallazgo_origen' => $this->whenLoaded('hallazgoOrigen', fn () => [
                'id' => $this->hallazgoOrigen->id,
                'gravedad' => $this->hallazgoOrigen->gravedad->value,
                'estado' => $this->hallazgoOrigen->estado->value,
                'inspeccion_id' => $this->hallazgoOrigen->inspeccion_id,
            ]),
        ];
    }
}