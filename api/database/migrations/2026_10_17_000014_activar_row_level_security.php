<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AISLAMIENTO ENTRE EMPRESAS: Row Level Security (RLS) de PostgreSQL.
 *
 * Esta migración es el corazon del producto. Es el mismo patron documentado en
 * `apps/portal-autoservicio/docs/03-MULTITENANCY.md`: columna `tenant_id` más
 * RLS, con `WITH CHECK`, `FORCE ROW LEVEL SECURITY` y el contexto viajando con
 * `set_config(..., true)`, de alcance de transacción. Ver docs/03.
 *
 * MECANISMO
 * ---------
 * 1. `public.app_tenant_id()` lee el valor de la variable de sesión
 *    `app.tenant_id`. Si no esta definida, LANZA un error. No devuelve NULL ni
 *    una cadena vacia: el aislamiento falla cerrado, nunca abierto.
 * 2. Cada tabla de negocio tiene una política que exige
 *    `tenant_id = app_tenant_id()`, al leer (USING) y al escribir
 *    (WITH CHECK).
 * 3. `FORCE ROW LEVEL SECURITY` hace que la política aplique también al dueno
 *    de la tabla. Sin FORCE, el dueno la saltaria, y en desarrollo el rol de la
 *    aplicación suele ser justamente el dueno.
 * 4. La variable se fija con `set_config('app.tenant_id', ..., true)`, o sea
 *    con alcance de Transacción. Al confirmar o revertir, PostgreSQL la
 *    descarta sola. Por eso un `SET` normal, que dejaria el valor pegado a la
 *    sesión, no sirve: el worker de colas reutiliza la misma conexión para
 *    trabajos de empresas distintas y leeria los datos de la empresa anterior.
 *
 * LA TABLA `tenants` NO TIENE RLS, A PROPÓSITO
 * --------------------------------------------
 * Se consulta durante el login, cuando todavia no hay sesión. Compensaciones:
 * la vista `vw_tenants_publicos`, que expone solo tres columnas, y el rol de
 * la aplicación, que no es superusuario. Ver docs/03, seccion 4.
 *
 * REQUISITO DE DESPLIEGUE
 * -----------------------
 * El rol que usa la aplicación NO puede ser superusuario ni tener BYPASSRLS.
 * Con superusuario, PostgreSQL ignora las políticas por completo y todo lo
 * de arriba es decorativo. El comando `obras:verificar-aislamiento` lo
 * comprueba y hay que correrlo en cada despliegue.
 */
return new class extends Migration
{
    /**
     * Tablas con datos de negocio, y por lo tanto con RLS.
     *
     * `sessions` NO esta aquí a propósito: no tiene `tenant_id` (ver su
     * migración). Agregarla sin columna fallaria de forma explicita en
     * `verificarColumnaTenant()`, que es mejor que dejar una tabla de
     * negocio sin política.
     *
     * @var list<string>
     */
    private array $tablasProtegidas = [
        'usuarios',
        'tipos_inspeccion',
        'obras',
        'etapas',
        'tareas',
        'inspecciones',
        'hallazgos',
        'registros_seguridad',
        'avances_pago',
        'bitacora_obra',
    ];

    public function up(): void
    {
        // 1. Funcion de contexto. Falla ruidosamente si no hay tenant.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                valor text;
            BEGIN
                valor := current_setting('app.tenant_id', true);

                IF valor IS NULL OR valor = '' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'insufficient_privilege',
                        MESSAGE = 'No hay contexto de empresa en la conexion (app.tenant_id no esta definida). Se aborta la operacion para no exponer datos de otra constructora.',
                        HINT = 'La peticion no paso por App\Http\Middleware\ResolverTenant, o la operacion se ejecuto fuera de una transaccion.';
                END IF;

                RETURN valor::uuid;
            END
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.app_tenant_id() IS
            'Devuelve la empresa de la peticion. Lanza excepcion si no hay contexto: el aislamiento falla cerrado.';
        SQL);

        // 2. Políticas y activacion, tabla por tabla.
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                'SELECT to_regclass(?) AS tabla',
                ['public.' . $tabla]
            );

            // to_regclass devuelve NULL si la tabla todavia no existe.
            if ($existe === null || $existe->tabla === null) {
                continue;
            }

            DB::unprepared('ALTER TABLE public.' . $tabla . ' ENABLE ROW LEVEL SECURITY');
            DB::unprepared('ALTER TABLE public.' . $tabla . ' FORCE ROW LEVEL SECURITY');

            $nombre = 'politica_aislamiento_' . $tabla;

            DB::unprepared(<<<SQL
                DROP POLICY IF EXISTS {$nombre} ON public.{$tabla};

                CREATE POLICY {$nombre} ON public.{$tabla}
                    FOR ALL
                    TO PUBLIC
                    USING (tenant_id = public.app_tenant_id())
                    WITH CHECK (tenant_id = public.app_tenant_id());
            SQL);
        }

        // 3. Falla ruidosamente si alguna tabla de la lista no tiene
        //    `tenant_id`. Sin esto, un error de tipeo dejaria una tabla de
        //    datos de negocio sin política y la migración pasaria igual.
        $this->verificarColumnaTenant();
    }

    public function down(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne('SELECT to_regclass(?) AS tabla', ['public.' . $tabla]);

            if ($existe === null || $existe->tabla === null) {
                continue;
            }

            DB::unprepared('DROP POLICY IF EXISTS politica_aislamiento_' . $tabla . ' ON public.' . $tabla);
            DB::unprepared('ALTER TABLE public.' . $tabla . ' NO FORCE ROW LEVEL SECURITY');
            DB::unprepared('ALTER TABLE public.' . $tabla . ' DISABLE ROW LEVEL SECURITY');
        }

        DB::unprepared('DROP FUNCTION IF EXISTS public.app_tenant_id()');
    }

    /**
     * Falla ruidosamente si alguna tabla de la lista no tiene `tenant_id`.
     * Un nombre mal escrito dejaria una tabla de negocio sin protección y la
     * migración pasaria sin avisar.
     */
    private function verificarColumnaTenant(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                'SELECT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = \'public\' AND table_name = ? AND column_name = \'tenant_id\'
                 ) AS tiene_columna',
                [$tabla]
            );

            if ($existe === null || ! $existe->tiene_columna) {
                throw new RuntimeException(
                    'La tabla public.' . $tabla . ' no tiene columna tenant_id y no puede protegerse con RLS. '
                    . 'Revise la migracion antes de continuar.'
                );
            }
        }
    }
};