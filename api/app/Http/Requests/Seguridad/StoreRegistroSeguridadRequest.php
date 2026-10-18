<?php

declare(strict_types=1);

namespace App\Http\Requests\Seguridad;

use App\Enums\ClaseRegistroSeguridad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de un accidente o un casi accidente.
 *
 * UN CASI ACCIDENTE NO ES UN ACCIDENTE, Y LA DIFERENCIA SE VALIDA
 * -------------------------------------------------------------
 * Si la aplicación permitiera registrar un accidente sin lesionado ni horas
 * perdidas, ni los informes ni la planilla de Accidentabilidad del Chile
 * servirian para nada. Si permitiera un casi accidente con horas perdidas,
 * habría un accidente registrado como "casi", y esa es exactamente la forma
 * de perder el dato que permite prevenir.
 *
 * Por eso la clase no es un dato libre: se elige, y cada clase tiene reglas
 * propias. El mensaje de error esta escrito para quien esta en la obra, no
 * para quien desarrollo el sistema.
 *
 * DATOS PERSONALES (Ley 19.628)
 * -----------------------------
 * Nombre y RUT de la persona afectada son opcionales, y si el registro se
 * marca como `anonizado`, quedan prohibidos: un registro anonimizado con
 * nombre cargado es un registro que dice que se borro algo que sigue ahí.
 */
class StoreRegistroSeguridadRequest extends FormRequest
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
            'clase' => ['required', Rule::in(ClaseRegistroSeguridad::valores())],
            'fecha_ocurrencia' => ['required', 'date'],
            'lugar' => ['nullable', 'string', 'max:180'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'lesionado' => ['boolean'],
            'horas_perdidas' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'persona_afectada_nombre' => ['nullable', 'string', 'max:120'],
            'persona_afectada_rut' => ['nullable', 'string', 'max:12'],
            'anonizado' => ['boolean'],
            'medidas_correctivas' => ['nullable', 'string', 'max:5000'],
            'tarea_correccion_id' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'clase.in' => 'El registro debe ser un accidente o un casi accidente.',
            'descripcion.required' => 'Describa lo que ocurrio, aunque sea en una linea.',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $clase = $this->input('clase');
            $lesionado = (bool) $this->input('lesionado', false);
            $horas = (float) $this->input('horas_perdidas', 0);
            $anonizado = (bool) $this->input('anonizado', false);

            if ($clase === ClaseRegistroSeguridad::Accidente->value) {
                if (! $lesionado) {
                    $v->errors()->add(
                        'lesionado',
                        'Un accidente con lesionado va marcado como lesionado. Si no hubo lesion, corresponde un casi accidente.'
                    );
                }

                if ($horas <= 0) {
                    $v->errors()->add(
                        'horas_perdidas',
                        'Un accidente con lesionado requiere las horas perdidas. Sin ellas no se puede reportar.'
                    );
                }
            }

            if ($clase === ClaseRegistroSeguridad::CasiAccidente->value) {
                if ($horas > 0) {
                    $v->errors()->add(
                        'horas_perdidas',
                        'Un casi accidente no tiene horas perdidas: si las hay, es un accidente y debe registrarse como tal.'
                    );
                }

                if ($lesionado) {
                    $v->errors()->add(
                        'lesionado',
                        'Un casi accidente no tiene lesionado. Si hubo lesion, es un accidente.'
                    );
                }
            }

            if ($anonizado) {
                if ($this->filled('persona_afectada_nombre') || $this->filled('persona_afectada_rut')) {
                    $v->errors()->add(
                        'anonizado',
                        'Un registro anonimizado no puede traer nombre ni RUT de la persona afectada.'
                    );
                }
            }
        });
    }
}