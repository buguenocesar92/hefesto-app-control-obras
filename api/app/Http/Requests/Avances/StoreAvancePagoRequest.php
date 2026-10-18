<?php

declare(strict_types=1);

namespace App\Http\Requests\Avances;

use App\Enums\EstadoAvancePago;
use App\Enums\RolUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Emision de un avance de pago.
 *
 * `avance_fisico_pct` es la FOTO del avance fisico al momento de emitir, no el
 * avance fisico de la obra. Se pide porque el estado de cuenta va firmado y
 * tiene que poder auditarse después, aunque las etapas sigan avanzando. Quien
 * lo emite debería tomar el valor de `GET /api/v1/estado-de-cuenta`.
 *
 * `monto_neto` no se acepta desde el cliente: se calcula en el controlador
 * como `monto_bruto - deducciones`. Si lo mandara el navegador, un estado de
 * cuenta podría traer un neto que no cuadra con lo facturado, y eso se
 * descubre cuando ya no se puede corregir.
 */
class StoreAvancePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        if ($usuario === null) {
            return false;
        }

        // El cliente no emite pagos: los recibe.
        return $usuario->rol !== RolUsuario::Cliente;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'obra_id' => ['required', 'uuid'],
            'numero' => ['required', 'integer', 'min:1', 'max:999'],
            'fecha_emision' => ['required', 'date', 'before_or_equal:today'],
            'periodo_desde' => ['nullable', 'date'],
            'periodo_hasta' => ['nullable', 'date', 'after_or_equal:periodo_desde'],
            'avance_fisico_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'monto_bruto' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'deducciones' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'monto_cobrado' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'fecha_cobro' => ['nullable', 'date'],
            'estado' => ['required', Rule::in(EstadoAvancePago::valores())],
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $cobrado = (float) $this->input('monto_cobrado', 0);
            $bruto = (float) $this->input('monto_bruto', 0);
            $deducciones = (float) $this->input('deducciones', 0);

            if ($deducciones > $bruto) {
                $v->errors()->add(
                    'deducciones',
                    'Las deducciones no pueden ser mayores que el monto bruto.'
                );
            }

            // Cobrar más de lo facturado es un error de carga, no un ingreso.
            if ($cobrado > $bruto) {
                $v->errors()->add(
                    'monto_cobrado',
                    'No se puede cobrar mas que el monto bruto de la linea.'
                );
            }

            // Marcar pagado sin fecha de cobro deja el avance financiero sin
            // respaldo documental.
            if ($this->input('estado') === 'pagado' && $this->input('fecha_cobro') === null) {
                $v->errors()->add(
                    'fecha_cobro',
                    'Un pago marcado como cobrado necesita la fecha en que entro el dinero.'
                );
            }
        });
    }
}