<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RegistroSeguridad;
use App\Models\Usuario;

/**
 * Reglas de los registros de seguridad.
 *
 * El cliente no registra accidentes ni casi accidentes. Si pudiera hacerlo,
 * podría que el historial de seguridad de la obra no refleje la realidad, y ese
 * registro existe justamente para lo contrario.
 */
class RegistroSeguridadPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, RegistroSeguridad $registro): bool
    {
        return true;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->rol->puedeRegistrarSeguridad();
    }

    /**
     * Anonizar un accidente lo puede pedir cualquiera de la empresa.
     *
     * La Ley 19.628 obliga a atender la solicitud de supresion del titular
     * del dato, y no tiene sentido que eso dependa de que un cargo tenga
     * permiso para escribir en la obra.
     */
    public function update(Usuario $usuario, RegistroSeguridad $registro): bool
    {
        return true;
    }

    /**
     * Los accidentes no se borran: se anonimizan.
     *
     * Borrar el registro entero haría desaparecer el hecho, que es lo único
     * que sirve para que no se repita.
     */
    public function delete(Usuario $usuario, RegistroSeguridad $registro): bool
    {
        return false;
    }
}