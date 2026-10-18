<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una etapa.
 *
 * "Atrasada" NO es un estado que alguien pone a mano cuando la obra se atrasa:
 * se calcula comparando la fecha planificada con la fecha real. Si se guardara
 * como estado, dejaria de ser cierto el día que la fecha planificada se
 * reprograma, y el atraso sería invisible. Ver `DesviacionDeEtapa`.
 */
enum EstadoEtapa: string
{
    case Pendiente = 'pendiente';
    case EnEjecucion = 'en_ejecucion';
    case Terminada = 'terminada';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $estado): string => $estado->value, self::cases());
    }
}