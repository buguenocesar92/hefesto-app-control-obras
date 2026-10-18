<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un hallazgo.
 *
 * El hallazgo no se cierra por separado de la tarea de corrección: se cierra
 * cuando la tarea queda `completada`. Si se cerrara el hallazgo sin cambiar la
 * tarea, quedaria una corrección "hecha" que nadie ejecuto.
 */
enum EstadoHallazgo: string
{
    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case Corregido = 'corregido';
    case Descartado = 'descartado';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}