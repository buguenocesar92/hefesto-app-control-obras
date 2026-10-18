<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un avance de pago.
 *
 * "Aprobado" y "pagado" están separados a propósito: que el mandante apruebe
 * un estado de cuenta no significa que el dinero haya entrado. Si se
 * colapsaran en un solo estado, el avance financiero de la obra sería una
 * cifra optimista que el cliente nunca habría visto.
 */
enum EstadoAvancePago: string
{
    case Emitido = 'emitido';
    case Aprobado = 'aprobado';
    case Pagado = 'pagado';
    case Rechazado = 'rechazado';

    /** Solo lo que esta pagado cuenta como avance financiero. */
    public function cuentaComoCobrado(): bool
    {
        return $this === self::Pagado;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}