<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Compensacion 1 del punto ciego de la tabla `tenants`.
 *
 * `tenants` no lleva RLS porque se consulta antes de que exista sesión, y por
 * eso no hay `app.tenant_id` que poner. Eso obliga a que la aplicación NO lea
 * esa tabla cruda: antes del login solo se lee esta vista, que expone tres
 * columnas y solo de las empresas activas.
 *
 * No expone RUT, ni razon social completa, niningun contacto. La aplicación
 * necesita una lista para el desplegable de entrada, y nada más.
 *
 * COMPENSACION 2: el rol con que corre la aplicación no es superusuario ni
 * tiene BYPASSRLS, para que no pueda saltarse las políticas a mano. Lo
 * comprueba `obras:verificar-aislamiento`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_tenants_publicos AS
            SELECT
                t.slug,
                t.razon_social AS nombre,
                t.activo
            FROM public.tenants AS t
            WHERE t.activo = true;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON VIEW public.vw_tenants_publicos IS
            'Lista de entrada: slug, nombre y activo. No expone RUT ni datos de contacto de la empresa.';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS public.vw_tenants_publicos');
    }
};