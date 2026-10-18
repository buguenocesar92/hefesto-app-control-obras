<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una inspección.
 *
 * "Conforme" significa que se hizo y no salio nada. Es un estado de verdad
 * registro: se escribe aunque no haya hallazgos, porque la prueba de que la
 * fundación se reviso el jueves es justamente que exista el registro de esa
 * revisión.
 */
enum EstadoInspeccion: string
{
    case Conforme = 'conforme';
    case ConHallazgos = 'con_hallazgos';
    case Rechazada = 'rechazada';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}