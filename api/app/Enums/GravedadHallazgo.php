<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Gravedad de un hallazgo y, con ella, el plazo de la corrección.
 *
 * `diasPlazo()` es el corazon del vinculo inspección -> tarea. Si no hubiera
 * plazo, el hallazgo sería una observacion anotada que nadie persigue: la
 * inspección se archiva y el problema sigue en la obra.
 *
 * Los plazos son una SUGERENCIA por defecto, no una imposicion normativa:
 * quien realiza la inspección puede ampliar el plazo al generar la tarea, y el
 * motivo queda en la bitacora. Lo que no se permite es generar la tarea sin
 * fecha de plazo.
 */
enum GravedadHallazgo: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Critica = 'critica';

    /**
     * Días habiles sugeridos para corregir, contados desde la fecha del
     * hallazgo. No son días calendario: el lunes siguiente a un hallazgo del
     * viernes no vence el domingo.
     */
    public function diasPlazoSugeridos(): int
    {
        return match ($this) {
            self::Baja => 15,
            self::Media => 7,
            self::Alta => 3,
            self::Critica => 1,
        };
    }

    /** Un hallazgo critico no se puede dejar para más tarde. */
    public function esUrgente(): bool
    {
        return $this === self::Alta || $this === self::Critica;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $gravedad): string => $gravedad->value, self::cases());
    }
}