<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un hallazgo con su tarea de corrección.
 *
 * Un hallazgo SIN tarea es un hallazgo mal cargado, y sale a la vista en
 * `tiene_tarea_correccion` para que la obra lo sepa y no lo tome por
 * controlado.
 */
class HallazgoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'descripcion' => $this->descripcion,
            'gravedad' => $this->gravedad->value,
            'estado' => $this->estado->value,
            'tiene_tarea_correccion' => $this->tarea_correccion_id !== null,
            'tarea_correccion' => $this->whenLoaded('tareaCorreccion', fn () => [
                'id' => $this->tareaCorreccion->id,
                'titulo' => $this->tareaCorreccion->titulo,
                'estado' => $this->tareaCorreccion->estado->value,
                'fecha_plazo' => $this->tareaCorreccion->fecha_plazo?->toDateString(),
                'vencida' => $this->tareaCorreccion->estaVencida(),
            ]),
            'inspeccion_id' => $this->inspeccion_id,
        ];
    }
}