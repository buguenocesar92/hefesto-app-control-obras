<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una etapa con su atraso calculado.
 *
 * `desviacion_dias` se calcula al vuelo y NO se guarda. Si se guardara, el día
 * que se reprograma el plan habría que recalcular todas las etapas, y la que se
 * olvidara quedaria mostrando un atraso que ya no existe.
 *
 * `atrasada` va separada de `desviacion_dias` porque no es lo mismo: una etapa
 * puede tener desviacion positiva (terminó después) o estar `atrasada` y no
 * tener fecha real todavia (sigue corriendo y ya paso el plazo).
 */
class EtapaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orden' => $this->orden,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado->value,
            'fecha_planificada' => $this->fecha_planificada?->toDateString(),
            'fecha_real' => $this->fecha_real?->toDateString(),
            'desviacion_dias' => $this->desviacionDias(),
            'atrasada' => $this->estaAtrasada(),
            'avance_real_pct' => (float) $this->avance_real_pct,
            'peso' => (float) $this->peso,
            'responsable' => $this->whenLoaded('responsable', fn () => [
                'id' => $this->responsable->id,
                'nombre' => $this->responsable->nombre,
            ]),
        ];
    }
}