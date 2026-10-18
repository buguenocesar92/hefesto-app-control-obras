<?php

declare(strict_types=1);

use App\Enums\EstadoHallazgo;
use App\Enums\GravedadHallazgo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hallazgos de una inspección.
 *
 * UN HALLAZGO GENERA UNA TAREA DE CORRECCIÓN CON PLAZO. Sin ese vínculo, la
 * inspección es un papel que nadie vuelve a abrir. El vinculo va en los dos
 * sentidos: el hallazgo apunta a su tarea (`tarea_correccion_id`) y la tarea
 * apunta al hallazgo que la origino. Los dos referencian filas de tablas
 * distintas, y por eso la llave de `tareas.origen_hallazgo_id` se agrega al
 * final de ESTA migración, ya que `tareas` existe pero `hallazgos` todavia no.
 *
 * `tarea_correccion_id` es único: un hallazgo no puede tener dos tareas de
 * corrección. Es la garantia que hace que "cuantas observaciones siguen
 * pendientes" sea una pregunta con respuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hallazgos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->foreignUuid('inspeccion_id')->constrained('inspecciones')->cascadeOnDelete();

            $tabla->text('descripcion');

            // La gravedad determina el plazo sugerido de la corrección. Ver
            // `GravedadHallazgo::diasPlazoSugeridos()`.
            $tabla->string('gravedad', 12)->default(GravedadHallazgo::Media->value);

            $tabla->string('estado', 16)->default(EstadoHallazgo::Pendiente->value);

            // Referencia directa a la tarea de corrección. Único: un hallazgo,
            // una tarea.
            $tabla->foreignUuid('tarea_correccion_id')->nullable()
                ->unique()
                ->constrained('tareas')->nullOnDelete();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'gravedad']);
            $tabla->index(['inspeccion_id', 'estado']);
        });

        // Segundo paso de la dependencia circular: ahora que `hallazgos`
        // existe, `tareas` puede apuntarle.
        Schema::table('tareas', function (Blueprint $tabla): void {
            $tabla->foreign('origen_hallazgo_id')
                ->references('id')
                ->on('hallazgos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $tabla): void {
            $tabla->dropForeign(['origen_hallazgo_id']);
        });

        Schema::dropIfExists('hallazgos');
    }
};