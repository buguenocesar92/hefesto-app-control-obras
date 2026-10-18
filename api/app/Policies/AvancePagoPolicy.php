<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\AvancePago;
use App\Models\Usuario;

/**
 * Reglas de los avances de pago.
 *
 * El cliente ve el estado de cuenta pero no lo emite. Que el cliente pudiera
 * marcar un pago como cobrado significaria que el avance financiero lo
 * declara el mismo cliente que lo recibe, y la cifra dejaria de ser un control.
 */
class AvancePagoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, AvancePago $pago): bool
    {
        return true;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->rol !== RolUsuario::Cliente;
    }

    public function update(Usuario $usuario, AvancePago $pago): bool
    {
        return $usuario->rol !== RolUsuario::Cliente;
    }

    public function delete(Usuario $usuario, AvancePago $pago): bool
    {
        return $usuario->esAdministrador();
    }
}