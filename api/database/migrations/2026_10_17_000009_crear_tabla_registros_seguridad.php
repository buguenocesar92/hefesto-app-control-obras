<?php

declare(strict_types=1);

use App\Enums\ClaseRegistroSeguridad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accidentes y CASI ACCIDENTES, en la misma tabla y con la misma obligacion
 * de registro.
 *
 * Un casi accidente no es un accidente. Se registra en la misma tabla porque
 * se analiza en la misma reunion, pero es una clase propia
 * (`clase = casi_accidente`) y por eso los informes los cuentan por separado:
 * sumarlos sería la forma facil de perder justamente el dato que permite
 * prevenir antes de que se repita.
 *
 * PROPIEDAD DEL DATO, QUE SE VALIDA EN LA CAPA DE PETICIÓN
 * ------------------------------------------------------
 * Un accidente exige `lesionado = true` y `horas_perdidas > 0`. Un casi
 * accidente no puede tener horas perdidas: si las tiene, es un accidente y
 * debe registrarse como tal. La regla vive en el Request, no en un trigger,
 * para que el mensaje de error sea util para quien esta en la obra.
 *
 * DATOS PERSONALES (Ley 19.628)
 * -----------------------------
 * La persona afectada se identifica por nombre y RUT, porque el registro de
 * accidentes lo exige la normativa sectorial. `anonizado` existe para cumplir
 * el principio de finalidad: cuando el accidente ya se cerro y la obra ya
 * entrego la información a quien corresponde, los nombres se pueden borrar
 * sin perder el registro del hecho. Ver docs/07.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_seguridad', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->foreignUuid('obra_id')->constrained('obras')->cascadeOnDelete();

            // La clase se elige al escribir. No hay un estado "no paso nada":
            // o es accidente, o es casi accidente.
            $tabla->string('clase', 20)->default(ClaseRegistroSeguridad::CasiAccidente->value);

            // Momento en que ocurrio. Es timestamptz y no date porque un
            // accidente se registra con hora.
            $tabla->timestampTz('fecha_ocurrencia');
            $tabla->string('lugar', 180)->nullable();

            $tabla->text('descripcion');

            // Solo tiene sentido en un accidente.
            $tabla->boolean('lesionado')->default(false);
            $tabla->decimal('horas_perdidas', 7, 2)->default(0);

            $tabla->string('persona_afectada_nombre', 120)->nullable();
            $tabla->string('persona_afectada_rut', 12)->nullable();

            // Cuando es true, el nombre y el RUT deben venir vacios. Lo
            // comprueba `StoreRegistroSeguridadRequest`.
            $tabla->boolean('anonizado')->default(false);

            // Que se hizo para que no se repita. Un casi accidente sin
            // medida correctiva es solo un susto anotado.
            $tabla->text('medidas_correctivas')->nullable();

            $tabla->foreignUuid('reportado_por_id')->constrained('usuarios')->restrictOnDelete();

            // Un accidente puede generar también una tarea de corrección,
            // con el mismo mecanismo que los hallazgos de inspección.
            $tabla->foreignUuid('tarea_correccion_id')->nullable()->constrained('tareas')->nullOnDelete();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'clase']);
            $tabla->index(['tenant_id', 'fecha_ocurrencia']);
            $tabla->index(['obra_id', 'clase']);
            $tabla->index(['tenant_id', 'reportado_por_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_seguridad');
    }
};