<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha de una obra.
 *
 * Expone el monto contratado porque es el denominador del avance financiero,
 * pero NO expone datos personales del cliente más allá del nombre y el RUT:
 * el RUT es un dato de persona y aparece solo donde hace falta para facturar.
 */
class ObraResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'cliente_nombre' => $this->cliente_nombre,
            'cliente_rut' => $this->cliente_rut,
            'cliente_contacto' => $this->cliente_contacto,
            'direccion' => $this->direccion,
            'tipo' => $this->tipo,
            'estado' => $this->estado->value,
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'fecha_termino_estimada' => $this->fecha_termino_estimada?->toDateString(),
            'monto_contratado' => (float) $this->monto_contratado,
            'empresa' => $this->whenLoaded('empresa', fn () => [
                'razon_social' => $this->empresa->razon_social,
            ]),
            'conteo' => [
                'etapas' => $this->whenCounted('etapas'),
                'tareas_pendientes' => $this->whenLoaded('tareas', fn () => $this->tareas->count()),
            ],
            'creado_en' => $this->created_at?->toIso8601String(),
        ];
    }
}