<?php

declare(strict_types=1);

namespace App\Http\Requests\Obras;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta o edicion de una etapa.
 *
 * `fecha_planificada` y `fecha_real` son las dos columnas del modulo: la
 * diferencia entre ambas es el atraso. Ninguna de las dos es obligatoria
 * porque hay etapas que todavia ni siquiera están planificadas, y un dato
 * inventado para completarla es peor que un dato faltante.
 *
 * El peso va de 0 a 100 y es la parte que obliga a que la suma por obra de
 * 100: es lo que hace que el avance fisico sea ponderado y no un promedio
 * simple de etapas.
 */
class StoreEtapaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null && $usuario->puedeEditarObra();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'orden' => ['required', 'integer', 'min:1', 'max:999'],
            'nombre' => ['required', 'string', 'max:180'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'fecha_planificada' => ['nullable', 'date'],
            'fecha_real' => ['nullable', 'date'],
            'avance_real_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'peso' => ['required', 'numeric', 'min:0', 'max:100'],
            'estado' => ['required', 'string', 'in:pendiente,en_ejecucion,terminada'],
            'responsable_id' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avance_real_pct.max' => 'El avance de la etapa va de 0 a 100.',
            'peso.max' => 'El peso de la etapa va de 0 a 100, y la suma de los pesos de una obra debe dar 100.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $planificada = $this->input('fecha_planificada');
            $real = $this->input('fecha_real');

            // Una etapa terminada antes de arrancar es un dato que hay que
            // rechazar: no hay fecha planificada con que compararla, así que
            // el atraso no se podría calcular nunca.
            if ($real !== null && $planificada === null && $this->input('estado') === 'terminada') {
                $v->errors()->add(
                    'fecha_planificada',
                    'No se puede marcar una etapa como terminada sin fecha planificada: sin ella no se puede medir el atraso.'
                );
            }
        });
    }
}