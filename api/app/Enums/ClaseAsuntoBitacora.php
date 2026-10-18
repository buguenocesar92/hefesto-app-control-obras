<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de asunto de la bitacora de obra.
 *
 * La bitacora es el modulo más barato y el más importante: es donde una
 * decisión dejada de palabra queda escrita, con fecha y responsable. "Se
 * acordo", sin fecha ni autor, es lo que hace que en tres meses nadie sepa si
 * la fundación se reviso.
 */
enum ClaseAsuntoBitacora: string
{
    /** Decisión tomada, con el nombre de quien decide. */
    case Decision = 'decision';

    /** Acuerdo de reunion de obra entre dos o más partes. */
    case Acuerdo = 'acuerdo';

    /** Observacion sin decisión asociada todavia. */
    case Observacion = 'observacion';

    /** Reclamo o reserva de derechos del cliente o del contratista. */
    case Reclamo = 'reclamo';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $clase): string => $clase->value, self::cases());
    }
}