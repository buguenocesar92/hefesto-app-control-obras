<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Punto de entrada de la semilla.
 *
 * Las empresas NO se crean con `CrearEmpresaCommand` sino aca, para que un
 * entorno de demostracion se arme completo con un solo comando. En un
 * servidor real, el alta de una empresa SIEMPRE es con
 * `obras:crear-empresa`, porque el primer usuario debe definirlo el operador
 * del servidor y no una semilla que cualquiera puede correr.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ObraDemoSeeder::class);
    }
}