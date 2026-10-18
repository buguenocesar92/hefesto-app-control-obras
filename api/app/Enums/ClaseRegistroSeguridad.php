<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Un accidente y un casi accidente se registran DISTINTOS.
 *
 * No es una distincion de forma. El casi accidente es el dato que permite
 * prevenir antes de que se repita el accidente: si el registro obliga a
 * elegir entre las dos clases al escribir, el casi accidente no se diluye
 * dentro de "no paso nada", que es exactamente como se pierde.
 *
 * `esAccidente()` decide también si se exige lesionado y horas perdidos.
 */
enum ClaseRegistroSeguridad: string
{
    case Accidente = 'accidente';
    case CasiAccidente = 'casi_accidente';

    public function esAccidente(): bool
    {
        return $this === self::Accidente;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $clase): string => $clase->value, self::cases());
    }
}