<?php

declare(strict_types=1);

namespace App\Http\Requests\Obras;

use App\Enums\EstadoObra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edicion de una obra.
 *
 * La empresa NO se acepta desde el request: la pone el contexto de la sesión.
 * Aceptarla sería abrir la puerta a que alguien cree una obra en nombre de
 * otra constructora, y la RLS lo rechazaria con un error de PostgreSQL que no
 * le dice nada a quien esta en la pantalla.
 */
class StoreObraRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_ ]+$/'],
            'nombre' => ['required', 'string', 'max:180'],
            'cliente_nombre' => ['required', 'string', 'max:180'],
            'cliente_rut' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]{7,8}-?[0-9Kk]$/'],
            'cliente_contacto' => ['nullable', 'string', 'max:160'],
            'direccion' => ['required', 'string', 'max:240'],
            'tipo' => ['required', 'string', 'max:80'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_termino_estimada' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'monto_contratado' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'estado' => ['required', Rule::in(EstadoObra::valores())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo.regex' => 'El codigo de obra solo puede tener letras, numeros, espacios, guion y guion bajo.',
            'cliente_rut.regex' => 'El RUT debe escribirse sin puntos, por ejemplo 76123456.',
            'fecha_termino_estimada.after_or_equal' => 'La fecha de termino no puede ser anterior al inicio.',
            'monto_contratado.required' => 'El monto contratado es obligatorio: sin el no se puede calcular el avance financiero.',
        ];
    }
}