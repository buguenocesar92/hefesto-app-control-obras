<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Tarea;
use App\Models\Usuario;

/**
 * Reglas de las tareas.
 *
 * El cliente ve el tablero pero no lo alimenta: no crea, no completa y no
 * borra. Ve lo que esta pendiente porque es su derecho a saber en que se
 * invierte su plata, no porque pueda cambiarlo.
 */
class TareaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Tarea $tarea): bool
    {
        return true;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->rol !== RolUsuario::Cliente;
    }

    public function update(Usuario $usuario, Tarea $tarea): bool
    {
        return $usuario->rol !== RolUsuario::Cliente;
    }

    public function delete(Usuario $usuario, Tarea $tarea): bool
    {
        return $usuario->esAdministrador();
    }
}