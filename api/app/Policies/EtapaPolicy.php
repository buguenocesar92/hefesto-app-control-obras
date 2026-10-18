<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Etapa;
use App\Models\Obra;
use App\Models\Usuario;

/**
 * Reglas de las etapas.
 *
 * Quien puede tocar una etapa es quien ejecuta la obra: maestro, jefe de obra
 * y administracion. El cliente no, porque ver el avance y modificar el plan son
 * cosas distintas, y el plan lo escribe la constructora.
 */
class EtapaPolicy
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
        return $usuario->puedeEditarObra();
    }

    public function update(Usuario $usuario, Etapa $etapa): bool
    {
        return $usuario->puedeEditarObra();
    }

    /**
     * Las etapas no se borran.
     *
     * Se marcan terminadas. Borrar una etapa terminada saca del historial la
     * fecha real de algo que ocurrio de verdad, y el atraso de la obra dejaria
     * de poder reconstruirse.
     */
    public function delete(Usuario $usuario, Etapa $etapa): bool
    {
        return false;
    }
}