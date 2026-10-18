<?php

declare(strict_types=1);

use App\Enums\EstadoEtapa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapas de la obra: donde vive el avance fisico.
 *
 * LA CIFRA QUE IMPORTA
 * --------------------
 * `fecha_planificada` y `fecha_real` son las dos columnas del modulo. La
 * diferencia entre ambas, en días, es la única medida de atraso que se
 * discute en una reunion de obra. No hay columna "atraso": el atraso se
 * CALCULA, porque si se guardara, dejaria de ser cierto el día en que se
 * reprograma el plan y nadie avisaria de que el plan ya no es el que vale.
 *
 * `peso` es el tanto por ciento de la obra que representa la etapa, y la suma
 * de los pesos de una obra da 100. Es lo que permite calcular el avance
 * fisico ponderado: una etapa de 20% de la obra que esta al 50% aporta 10%.
 * El indice único (obra_id, orden) impide que dos etapas se numeren igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->foreignUuid('obra_id')->constrained('obras')->cascadeOnDelete();

            // Orden dentro de la obra. Es parte del indice único porque dos
            // etapas no pueden ocupar la misma posición en el mismo proyecto.
            $tabla->unsignedSmallInteger('orden');

            $tabla->string('nombre', 180);
            $tabla->text('descripcion')->nullable();

            $tabla->date('fecha_planificada')->nullable();
            $tabla->date('fecha_real')->nullable();

            // Avance real dentro de la etapa, de 0 a 100.
            $tabla->decimal('avance_real_pct', 5, 2)->default(0);

            // Peso de la etapa dentro de la obra, de 0 a 100. La suma por
            // obra debe dar 100; lo comprueba `avances:comprobar-pesos`.
            $tabla->decimal('peso', 5, 2)->default(0);

            $tabla->string('estado', 24)->default(EstadoEtapa::Pendiente->value);
            $tabla->foreignUuid('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->timestampsTz();

            $tabla->unique(['obra_id', 'orden']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'fecha_planificada']);
            $tabla->index(['obra_id', 'fecha_real']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etapas');
    }
};