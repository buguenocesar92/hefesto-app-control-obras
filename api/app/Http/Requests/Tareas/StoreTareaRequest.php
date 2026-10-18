<?php

declare(strict_types=1);

namespace App\Http\Requests\Tareas;

use App\Enums\EstadoTarea;
use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta o edicion de una tarea.
 *
 * CUANDO LA TAREA NACE DE UN HALLAZGO, EL PLAZO Y EL RESPONSABLE SON
 * OBLIGATORIOS Y NO SE PUEDEN CAMBIAR EN SILENCIO
 * ---------------------------------------------------
 * Si una corrección de inspección pudiera quedar sin fecha, se volveria
 * exactamente lo que el modulo quiere evitar: una observacion anotada y
 * nadie encima. Por eso una tarea de origen `correccion_inspeccion` exige
 * `fecha_plazo` y `responsable_id`, y además no se puede cambiar de origen a
 * mano.
 */
class StoreTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        if ($usuario === null) {
            return false;
        }

        // El cliente no crea tareas: ve el tablero, no lo alimenta.
        return $usuario->rol !== RolUsuario::Cliente;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'obra_id' => ['required', 'uuid'],
            'etapa_id' => ['nullable', 'uuid'],
            'titulo' => ['required', 'string', 'max:180'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'responsable_id' => ['nullable', 'uuid'],
            'fecha_plazo' => ['nullable', 'date'],
            'estado' => ['required', Rule::in(EstadoTarea::valores())],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $tarea = $this->route('tarea');

            $origen = $tarea !== null && method_exists($tarea, 'nacioDeInspeccion')
                ? ($tarea->nacioDeInspeccion() ? 'correccion_inspeccion' : 'manual')
                : 'manual';

            if ($origen !== 'correccion_inspeccion') {
                return;
            }

            if ($this->input('fecha_plazo') === null && $this->input('estado') !== 'completada') {
                $v->errors()->add(
                    'fecha_plazo',
                    'Una correccion de inspeccion no puede quedar sin fecha de plazo mientras siga abierta.'
                );
            }

            if ($this->input('responsable_id') === null && $this->input('estado') !== 'completada') {
                $v->errors()->add(
                    'responsable_id',
                    'Una correccion de inspeccion necesita un responsable mientras siga abierta.'
                );
            }
        });
    }
}