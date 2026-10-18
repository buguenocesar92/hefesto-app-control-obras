<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una tarea.
 *
 * Una tarea con plazo es el mecanismo que evita que una decisión se pierda:
 * mientras no este en `completada`, sigue visible. "Anulada" existe para dejar
 * constancia de que se dejó de hacer, en vez de borrarla.
 */
enum EstadoTarea: string
{
    case Pendiente = 'pendiente';
    case EnEjecucion = 'en_ejecucion';
    case Completada = 'completada';
    case Anulada = 'anulada';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}