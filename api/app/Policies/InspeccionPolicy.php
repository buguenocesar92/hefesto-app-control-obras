<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Hallazgo;
use App\Models\Usuario;

/**
 * Reglas de las inspecciones y sus hallazgos.
 *
 * El cliente NO inspecciona ni registra hallazgos: inspecciona la empresa que
 * ejecuta. Si el cliente pudiera levantar hallazgos, podría "corregir" su
 * propia obra declarando conforme lo que no esta.
 */
class InspeccionPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, \App\Models\Inspeccion $inspeccion): bool
    {
        return true;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->rol->puedeRegistrarSeguridad();
    }

    public function update(Usuario $usuario, Hallazgo $hallazgo): bool
    {
        return $usuario->rol !== RolUsuario::Cliente;
    }
}