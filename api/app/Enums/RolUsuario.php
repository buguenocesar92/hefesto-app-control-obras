<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles de la empresa.
 *
 * El rol NO decide el aislamiento entre empresas: eso lo hace la RLS de
 * PostgreSQL (ver docs/03). El rol solo ordena lo que se puede hacer DENTRO
 * de la propia empresa.
 */
enum RolUsuario: string
{
    /** Administracion de la empresa: responsables, tipos de inspección, estados. */
    case Administrador = 'administrador';

    /** Jefe de obra: responde por el avance de una obra concreta. */
    case JefeObra = 'jefe_obra';

    /** Maestro de obra: ejecuta etapas y deja las fechas reales. */
    case Maestro = 'maestro';

    /** Inspector: realiza inspecciones y levanta hallazgos. */
    case Inspector = 'inspector';

    /** Cliente: ve el estado de cuenta de SU obra, y no lo edita. */
    case Cliente = 'cliente';

    /**
     * Roles que pueden registrar hallazgos y accidents.
     *
     * El cliente no puede: no es parte de la ejecucion de la obra.
     *
     * @return list<string>
     */
    public function puedeRegistrarSeguridad(): bool
    {
        return match ($this) {
            self::Administrador, self::JefeObra, self::Maestro, self::Inspector => true,
            self::Cliente => false,
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $rol): string => $rol->value, self::cases());
    }
}