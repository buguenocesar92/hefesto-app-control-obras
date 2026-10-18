<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una inspección con sus hallazgos y las tareas que nacieron de ellos.
 *
 * El hallazgo expone `tarea_correccion` con su `fecha_plazo`: esa pareja es la
 * que convierte una observacion en un compromiso con fecha.
 */
class InspeccionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha_inspeccion' => $this->fecha_inspeccion?->toDateString(),
            'resultado' => $this->resultado->value,
            'numero_acta' => $this->numero_acta,
            'observaciones' => $this->observaciones,
            'tipo' => $this->whenLoaded('tipo', fn () => [
                'id' => $this->tipo->id,
                'nombre' => $this->tipo->nombre,
            ]),
            'inspector' => $this->whenLoaded('inspector', fn () => [
                'id' => $this->inspector->id,
                'nombre' => $this->inspector->nombre,
            ]),
            'hallazgos' => HallazgoResource::collection($this->whenLoaded('hallazgos')),
            'pendientes' => $this->when(
                $this->relationLoaded('hallazgos'),
                fn (): int => $this->hallazgos->filter(fn ($h) => $h->estaPendiente())->count()
            ),
        ];
    }
}