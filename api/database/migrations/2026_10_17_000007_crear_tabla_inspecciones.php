<?php

declare(strict_types=1);

use App\Enums\EstadoInspeccion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inspecciones.
 *
 * Una inspección conforme También se registra. Es el punto del modulo: la
 * prueba de que la fundación se reviso el jueves no es un mail, es que exista
 * la fila de la inspección con su inspector y su fecha. Si solo se guardaran
 * las inspecciones "con problemas", el sistema no podría demostrar la
 * revisión, que es justo lo que se le pide a una obra.
 *
 * `resultado` guarda el estado global; el detalle de los problemas va en
 * `hallazgos`. Se separan porque un informe de inspección necesita las dos
 * cosas y porque el hallazgo es el que genera la tarea con plazo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspecciones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->foreignUuid('obra_id')->constrained('obras')->cascadeOnDelete();
            $tabla->foreignUuid('tipo_inspeccion_id')->constrained('tipos_inspeccion')->restrictOnDelete();

            // Fecha en que se hizo, no la de digitacion. Una inspección
            // cargada el lunes y hecha el jueves tiene que decir jueves.
            $tabla->date('fecha_inspeccion');

            // Quien inspcciono. Es parte del registro: un hallazgo sin
            // inspector no es verificable.
            $tabla->foreignUuid('inspector_id')->constrained('usuarios')->restrictOnDelete();

            $tabla->string('resultado', 24)->default(EstadoInspeccion::Conforme->value);

            // Número de acta, si la obra lleva acta en papel. Es texto y no
            // un contador porque cada obra numera distinto.
            $tabla->string('numero_acta', 60)->nullable();

            $tabla->text('observaciones')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'fecha_inspeccion']);
            $tabla->index(['tenant_id', 'obra_id', 'resultado']);
            $tabla->index(['tenant_id', 'inspector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspecciones');
    }
};