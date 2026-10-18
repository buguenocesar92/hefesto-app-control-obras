<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas constructoras: el tenant del sistema.
 *
 * UNA obra pertenece a UNA empresa. La empresa es quien responde por el
 * maestro, por el inspector y por el estado de cuenta, así que el aislamiento
 * tiene que ir ahí y no en la obra.
 *
 * Esta tabla NO lleva RLS, a propósito: se consulta durante el login, cuando
 * todavia no hay sesión y por lo tanto no hay `app.tenant_id` que poner. Es
 * una excepción consciente, compensada con la vista `vw_tenants_publicos` y con
 * que el rol de la aplicación no sea superusuario. Ver docs/03, seccion 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            // Razon social de la empresa constructora o del estudio.
            $tabla->string('razon_social', 180);

            // RUT chileno, sin puntos ni guion, para poder indexarlo.
            $tabla->string('rut', 12);

            // Identificador legible en la URL de entrada.
            $tabla->string('slug', 60)->unique();

            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique('rut');
            $tabla->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};