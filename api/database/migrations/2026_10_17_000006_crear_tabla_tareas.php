<?php

declare(strict_types=1);

use App\Enums\EstadoTarea;
use App\Enums\OrigenTarea;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas: el mecanismo que hace que una decisión no se pierda.
 *
 * El caso que el modulo resuelve es concreto. En la reunion se acuerda revisar
 * la fundación el jueves. El maestro lo anota, se enferma, y un mes después
 * nadie sabe si se hizo. En vez de una frase en un cuaderno, la obra tiene una
 * tarea con `fecha_plazo` y `responsable_id`: mientras no este completada,
 * aparece en el tablero.
 *
 * `origen_hallazgo_id` NO lleva aquí su llave foranea a propósito: la tabla
 * `hallazgos` todavia no existe en este punto del despliegue, y la llave se
 * agrega después, en la migración de hallazgos. Es un caso normal de
 * dependencia circular resuelta en dos pasos.
 *
 * El indice único (tenant_id, origen_hallazgo_id) es la garantia de que un
 * hallazgo no genere dos tareas de corrección. En PostgreSQL un indice único
 * admite varias filas nulas, así que las tareas manuales no se ven afectadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->foreignUuid('obra_id')->constrained('obras')->cascadeOnDelete();
            $tabla->foreignUuid('etapa_id')->nullable()->constrained('etapas')->nullOnDelete();

            $tabla->string('origen', 24)->default(OrigenTarea::Manual->value);

            // Se referencia inspecciones ya en este punto, pero NO hallazgos:
            // la llave de hallazgos se agrega en su propia migración.
            $tabla->foreignUuid('origen_inspeccion_id')->nullable()->constrained('inspecciones')->nullOnDelete();
            $tabla->uuid('origen_hallazgo_id')->nullable();

            $tabla->string('titulo', 180);
            $tabla->text('descripcion')->nullable();

            // El responsable es obligatorio en la práctica aunque la columna
            // admita nulo: una tarea sin responsable es exactamente el
            // problema que el modulo quiere resolver.
            $tabla->foreignUuid('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->date('fecha_plazo')->nullable();
            $tabla->timestampTz('fecha_cierre')->nullable();

            $tabla->string('estado', 24)->default(EstadoTarea::Pendiente->value);

            // Quien dejó escrita la tarea, que no siempre es quien la cumple.
            $tabla->foreignUuid('creado_por_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'origen_hallazgo_id']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'fecha_plazo']);
            $tabla->index(['obra_id', 'estado']);
            $tabla->index(['tenant_id', 'responsable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};