<?php

declare(strict_types=1);

use App\Enums\EstadoAvancePago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avances de pago: el estado de cuenta al cliente.
 *
 * AVANCE FINANCIERO Y AVANCE FISICO NO SON LO MISMO
 * ---------------------------------------------------
 * Se puede tener 60% de avance fisico y 45% cobrado. Son dos cifras, con dos
 * fuentes distintas y con dos formas de calcularse, y el sistema las reporta
 * SIEMPRE por separado:
 *
 *   - El avance FISICO se calcula desde las etapas (`etapas.peso` y
 *     `etapas.avance_real_pct`). Es obra construida.
 *   - El avance FINANCIERO se calcula desde esta tabla: lo que el cliente
 *     efectivamente pago sobre el monto contratado. Es plata.
 *
 * `avance_fisico_pct` en ESTA tabla es una FOTO del avance fisico al momento
 * de emitir el pago. No es el avance fisico de la obra, y por eso tiene otro
 * nombre y su propia fuente: queda congelado aunque las etapas después cambien,
 * que es exactamente lo que se necesita para poder auditar después por que se
 * emitio ese pago.
 *
 * `estado` distingue aprobado de pagado a propósito. Que el mandante apruebe
 * un estado de cuenta no es lo mismo que el dinero haya entrado, y contar el
 * aprobado como cobrado es la forma habitual de mentir en un informe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avances_pago', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->foreignUuid('obra_id')->constrained('obras')->cascadeOnDelete();

            // Número correlativo dentro de la obra.
            $tabla->unsignedSmallInteger('numero');

            $tabla->date('fecha_emision');
            $tabla->date('periodo_desde')->nullable();
            $tabla->date('periodo_hasta')->nullable();

            // FOTO del avance fisico al emitir. No es el avance fisico de la
            // obra: ese lo calcula `CalculadorAvanceFisico` desde las etapas.
            $tabla->decimal('avance_fisico_pct', 5, 2)->nullable();

            $tabla->decimal('monto_bruto', 16, 2);
            $tabla->decimal('deducciones', 16, 2)->default(0);
            $tabla->decimal('monto_neto', 16, 2);

            // Cuanto de ese neto ha entrado de verdad. Solo este campo entra
            // en el avance financiero.
            $tabla->decimal('monto_cobrado', 16, 2)->default(0);
            $tabla->date('fecha_cobro')->nullable();

            $tabla->string('estado', 16)->default(EstadoAvancePago::Emitido->value);
            $tabla->text('observaciones')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['obra_id', 'numero']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['obra_id', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avances_pago');
    }
};