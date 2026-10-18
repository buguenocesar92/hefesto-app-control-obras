<?php

declare(strict_types=1);

use App\Enums\ClaseAsuntoBitacora;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora de obra: el registro de las decisiones.
 *
 * Este es el modulo más barato de escribir y el que define al producto. Una
 * obra se controla mal casi siempre por lo mismo: las decisiones se conversan
 * y no quedan escritas. El maestro sabe que hay que revisar la fundación el
 * jueves; se enferma; nadie lo anoto; y un mes después nadie sabe si se hizo.
 *
 * Por eso cada fila tiene `fecha` y `autor_id`. No es una tabla de notas: es
 * el lugar donde "se acordo" queda escrito con quien lo acordo y cuando. Un
 * acuerdo sin autor es una opinion, y después no sirve para nada.
 *
 * `tarea_relacionada_id` es opcional y es el enlace con el tablero: la
 * decisión escrita y la tarea con plazo son el mismo hecho visto desde dos
 * lugares, y conviene poder saltar de una a otra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_obra', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->foreignUuid('obra_id')->constrained('obras')->cascadeOnDelete();

            // Fecha en que se acordo, no la de digitacion.
            $tabla->timestampTz('fecha');

            $tabla->string('clase', 16)->default(ClaseAsuntoBitacora::Observacion->value);
            $tabla->string('asunto', 180);
            $tabla->text('cuerpo')->nullable();

            // Quien acordo. Obligatorio: es el dato que convierte la bitacora
            // en evidencia y no en un chiste de obra.
            $tabla->foreignUuid('autor_id')->constrained('usuarios')->restrictOnDelete();

            $tabla->foreignUuid('tarea_relacionada_id')->nullable()->constrained('tareas')->nullOnDelete();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'obra_id', 'fecha']);
            $tabla->index(['tenant_id', 'clase']);
            $tabla->index(['tenant_id', 'autor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_obra');
    }
};