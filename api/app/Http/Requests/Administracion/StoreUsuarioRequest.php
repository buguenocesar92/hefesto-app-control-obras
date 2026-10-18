<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edicion de una persona de la empresa.
 *
 * SOLO LA ADMINISTRACION DA DE ALTA PERSONAS
 * ------------------------------------------
 * El acceso se concede por invitacion, no por registro abierto. Una obra con
 * los nombres, RUT y telefonos de sus trabajadores no es algo que se abra al
 * que llega con un correo cualquiera.
 *
 * `rol` se acepta solo entre los valores del enum: un rol desconocido dejaria a
 * la persona fuera de todas las Policies y el sistema la trataria como si no
 * tuviera permisos, que es distinto de "sin permisos".
 */
class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null && $usuario->rol === RolUsuario::Administrador;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email:rfc', 'max:160'],
            'rut' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]{7,8}-?[0-9Kk]$/'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'cargo' => ['nullable', 'string', 'max:80'],
            'rol' => ['required', 'string', 'in:' . implode(',', RolUsuario::valores())],
            'activo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'correo.required' => 'El correo es obligatorio: es con el que la persona entra al sistema.',
            'rut.regex' => 'El RUT debe escribirse sin puntos, por ejemplo 76123456.',
            'rol.in' => 'El rol debe ser uno de los valores permitidos.',
        ];
    }
}