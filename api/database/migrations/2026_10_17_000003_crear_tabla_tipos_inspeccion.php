<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo administrable: tipos de inspección.
 *
 * Es una tabla y no un enum porque el tipo de inspección lo define la obra,
 * no el software. "Inspección de fundaciones", "control de hormigón" y
 * "recepción de la instalación" son cosas que la empresa define según su
 * obra, y agregar una no debe requerir un despliegue.
 *
 * Los estados de la obra, de las etapas y de las tareas SI son enums, porque
 * son parte del modelo y no pueden quedar a merced de que alguien escriba un
 * estado nuevo que el sistema no conoce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_inspeccion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->string('nombre', 100);
            $tabla->text('descripcion')->nullable();

            // Al desactivar un tipo se conserva el historico: las
            // inspecciones ya realizadas siguen apuntando a el.
            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_inspeccion');
    }
};