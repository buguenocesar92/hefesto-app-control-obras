<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Obra;
use App\Models\Usuario;

/**
 * Reglas dentro de la propia empresa.
 *
 * La RLS ya impide ver obras de otra constructora. Esta Policy decide quien
 * puede VER y quien puede MODIFICAR dentro de la misma, y por que:
 *
 *  - El CLIENTE ve su obra y no la edita. Que pueda cambiar el estado o el
 *    avance de su propia obra sería darle el control del sistema.
 *  - El INSPECTOR ve la obra para poder inspeccionarla, pero no la edita.
 *  - MAESTRO, JEFE DE OBRA y ADMINISTRADOR editan.
 *
 * Cuando una Policy rechaza, la respuesta es 404 y no 403: un 403 confirmaria
 * que la obra existe. Ver bootstrap/app.php.
 */
class ObraPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Obra $obra): bool
    {
        // La pertenencia a la empresa ya la garantiza la RLS. Aca se revisa
        // el rol, no el tenant.
        return $usuario->puedeEditarObra()
            || $usuario->rol->value === 'inspector'
            || $usuario->rol->value === 'cliente';
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->puedeEditarObra();
    }

    public function update(Usuario $usuario, Obra $obra): bool
    {
        return $usuario->puedeEditarObra();
    }

    /**
     * Borrar una obra completa se reserva a la administracion de la empresa.
     *
     * El maestro no borra: si hay algo mal cargado, se corrige. Borrar
     * llevaria el historial de la obra con el.
     */
    public function delete(Usuario $usuario, Obra $obra): bool
    {
        return $usuario->esAdministrador();
    }
}