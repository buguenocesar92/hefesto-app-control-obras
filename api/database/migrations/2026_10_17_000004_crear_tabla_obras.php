<?php

declare(strict_types=1);

use App\Enums\EstadoObra;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Obras: la unidad de negocio. Todo lo demas cuelga de aquí.
 *
 * `codigo` es el identificador que la empresa ya usa (por ejemplo "OB-2026-14")
 * y es único DENTRO de la empresa constructora, no global: dos constructoras
 * pueden numerar igual sin colision. El indice único es (tenant_id, codigo)
 * precisamente por eso.
 *
 * `monto_contratado` vive en la obra y no en el pago porque es el denominador
 * del avance financiero. Se guarda aquí para que el estado de cuenta se pueda
 * calcular sin depender de que se haya emitido ningun pago: una obra con 0%
 * de avance financiero es información valida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->string('codigo', 40);

            $tabla->string('nombre', 180);

            // Cliente de la obra. Datos del mandante, no de un trabajador.
            $tabla->string('cliente_nombre', 180);
            $tabla->string('cliente_rut', 12)->nullable();
            $tabla->string('cliente_contacto', 160)->nullable();

            $tabla->string('direccion', 240);
            $tabla->string('tipo', 80);

            $tabla->date('fecha_inicio');
            $tabla->date('fecha_termino_estimada')->nullable();

            // Denominador del avance financiero, en pesos sin decimales
            // cuando la obra se factura en uf. Se documenta en docs/02 que la
            // unidad de la moneda es la que fije el contrato.
            $tabla->decimal('monto_contratado', 16, 2)->default(0);

            $tabla->string('estado', 24)->default(EstadoObra::Planificacion->value);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'cliente_rut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obras');
    }
};