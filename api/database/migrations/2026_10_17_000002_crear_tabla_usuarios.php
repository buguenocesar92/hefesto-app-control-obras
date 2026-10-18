<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personas de la empresa: maestros de obra, inspectores, jefes de obra,
 * administradores y clientes.
 *
 * DATOS PERSONALES (Ley 19.628)
 * ------------------------------
 * RUT y teléfono son datos personales de trabajadores. Se guardan porque la
 * obra los necesita para emitir documentos y para identificar a quien
 * reporto un accidente, pero el principio que se aplica es el de finalidad:
 * se piden porque existe un uso concreto y no "porque siempre se piden". El
 * correo es el único campo obligatorio de contacto.
 *
 * No se guarda foto, documento de identidad, dirección particular, fecha de
 * nacimiento ni información de salud. Un accidente se describe por la lesión
 * (texto) y por las horas perdidas, no por el historial clinico de la persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->string('nombre', 120);
            $tabla->string('correo', 160);

            // Datos personales opcionales: se llenan solo si la obra los usa.
            $tabla->string('rut', 12)->nullable();
            $tabla->string('telefono', 30)->nullable();

            // Cargo en la obra. Es texto libre a propósito: los cargos cambian
            // más rapido que las migraciones.
            $tabla->string('cargo', 80)->nullable();

            $tabla->string('rol', 24)->default(RolUsuario::Maestro->value);
            $tabla->boolean('activo')->default(true);
            $tabla->timestampTz('ultimo_acceso_en')->nullable();

            $tabla->timestampsTz();

            // El correo es único DENTRO de la empresa: dos constructoras
            // pueden tener a un maestro con el mismo correo sin colision.
            $tabla->unique(['tenant_id', 'correo']);
            $tabla->index(['tenant_id', 'rol']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};