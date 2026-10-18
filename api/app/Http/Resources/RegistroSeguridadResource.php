<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un accidente o casi accidente.
 *
 * DATOS PERSONALES (Ley 19.628)
 * ------------------------------
 * Nombre y RUT de la persona afectada solo se exponen a quien esta autorizado
 * a verlos, y el recurso marca `anonizado` para que la interfaz muestre que
 * no hay dato personal, en vez de mostrar un campo vacio que parece un error
 * de carga.
 */
class RegistroSeguridadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clase' => $this->clase->value,
            'es_accidente' => $this->esAccidente(),
            'fecha_ocurrencia' => $this->fecha_ocurrencia?->toIso8601String(),
            'lugar' => $this->lugar,
            'descripcion' => $this->descripcion,
            'lesionado' => $this->lesionado,
            'horas_perdidas' => (float) $this->horas_perdidas,
            'medidas_correctivas' => $this->medidas_correctivas,
            'falta_medidas_correctivas' => $this->faltaMedidasCorrectivas(),
            'anonizado' => $this->anonizado,
            'persona_afectada' => $this->anonizado
                ? null
                : [
                    'nombre' => $this->persona_afectada_nombre,
                    'rut' => $this->persona_afectada_rut,
                ],
            'reportado_por' => $this->whenLoaded('reportadoPor', fn () => [
                'id' => $this->reportadoPor->id,
                'nombre' => $this->reportadoPor->nombre,
            ]),
        ];
    }
}