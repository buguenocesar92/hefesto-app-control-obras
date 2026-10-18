<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspecciones;

use App\Enums\EstadoInspeccion;
use App\Enums\GravedadHallazgo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de una inspección con sus hallazgos.
 *
 * LA INSPECCIÓN Y SUS HALLAZGOS SE GUARDAN JUNTOS
 * ----------------------------------------------
 * Escribir una inspección conforme sin hallazgos es valido, y es lo que más
 * veces va a pasar. Un hallazgo sin inspección, en cambio, no tiene fecha, ni
 * inspector, ni tipo de inspección: es una anotacion suelta, que es
 * justamente lo que el modulo vino a eliminar.
 *
 * Por eso se validan los hallazgos anidados dentro del mismo request y se
 * guardan en la misma transacción.
 */
class StoreInspeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null && $usuario->rol->puedeRegistrarSeguridad();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'obra_id' => ['required', 'uuid'],
            'tipo_inspeccion_id' => ['required', 'uuid'],
            'fecha_inspeccion' => ['required', 'date', 'before_or_equal:today'],
            'inspector_id' => ['required', 'uuid'],
            'resultado' => ['required', Rule::in(EstadoInspeccion::valores())],
            'numero_acta' => ['nullable', 'string', 'max:60'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'hallazgos' => ['array', 'max:100'],
            'hallazgos.*.descripcion' => ['required_with:hallazgos', 'string', 'max:2000'],
            'hallazgos.*.gravedad' => ['required_with:hallazgos', Rule::in(GravedadHallazgo::valores())],
            'hallazgos.*.responsable_id' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fecha_inspeccion.before_or_equal' => 'La fecha de la inspeccion no puede ser futura.',
            'hallazgos' => 'Una inspeccion admite hasta 100 hallazgos.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $hallazgos = $this->input('hallazgos', []);

            // Declarar la inspección CONFORME y a la vez mandar hallazgos es
            // una contradiccion que, si pasa, deja el informe mintiendo.
            if (is_array($hallazgos) && count($hallazgos) > 0 && $this->input('resultado') === 'conforme') {
                $v->errors()->add(
                    'resultado',
                    'Una inspeccion con hallazgos no puede declararse conforme.'
                );
            }
        });
    }
}