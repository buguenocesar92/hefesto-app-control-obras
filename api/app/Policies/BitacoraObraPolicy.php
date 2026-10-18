<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Obra;
use App\Models\Usuario;

/**
 * Reglas de la bitacora: quien puede escribir y quien puede leer.
 *
 * La bitacora es el registro de las decisiones, así que casi todos pueden
 * escribir y el cliente solo lee. Que el cliente pueda escribir en la
 * bitacora permitiria que "el acuerdo" fuera de cualquiera, que es justo el
 * problema que el producto viene a resolver.
 */
class BitacoraObraPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Obra $obra): bool
    {
        return true;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->rol !== RolUsuario::Cliente;
    }

    /** Nadie reescribe la bitacora: un acuerdo no se edita, se deja otro. */
    public function update(Usuario $usuario, Obra $obra): bool
    {
        return $usuario->esAdministrador();
    }

    public function delete(Usuario $usuario, Obra $obra): bool
    {
        return $usuario->esAdministrador();
    }
}