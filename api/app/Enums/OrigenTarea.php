<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De donde sale una tarea.
 *
 * `correccion_inspeccion` marca las tareas que nacieron de un hallazgo. Es lo
 * que permite responder, en una reunion de obra, la pregunta que siempre se
 * hace: "esa observacion que se hizo el mes pasado, en que quedó".
 */
enum OrigenTarea: string
{
    /** Se creo a mano, desde la reunion de obra o el planning. */
    case Manual = 'manual';

    /** Nacio de un hallazgo de una inspección, con plazo. */
    case CorreccionInspeccion = 'correccion_inspeccion';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $origen): string => $origen->value, self::cases());
    }
}