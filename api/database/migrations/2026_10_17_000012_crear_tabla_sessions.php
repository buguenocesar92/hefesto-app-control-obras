<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones en base de datos.
 *
 * NO lleva `tenant_id` y por eso NO entra en la lista de tablas protegidas
 * por RLS. Las tablas de sesión son de la infraestructura, no de negocio: una
 * sesión no es un dato de la empresa, y filtrarla por tenant haría que un
 * usuario que cambia de empresa cerrara la sesión sola, que no es el
 * comportamiento que se quiere.
 *
 * El contenido de la sesión (el identificador del usuario) si queda protegido
 * por lasPolicies y por el middleware: la RLS protege FILAS, y aquí lo que se
 * protege es la API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $tabla): void {
            $tabla->string('id')->primary();

            // Nullable: hay sesiones de visitante sin usuario autenticado.
            $tabla->foreignUuid('user_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->string('ip_address', 45)->nullable();
            $tabla->text('user_agent')->nullable();
            $tabla->longText('payload');
            $tabla->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};