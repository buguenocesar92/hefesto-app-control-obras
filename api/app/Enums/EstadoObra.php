<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una obra.
 *
 * "Detenida" existe a propósito: una obra parada es un estado normal y tenerlo
 * como valor propio evita que alguien la cierre para que deje de aparecer en
 * los informes de atraso.
 */
enum EstadoObra: string
{
    case Planificacion = 'planificacion';
    case EnEjecucion = 'en_ejecucion';
    case Detenida = 'detenida';
    case Terminada = 'terminada';
    case Entregada = 'entregada';

    public function estaEnCurso(): bool
    {
        return $this === self::EnEjecucion;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}