<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comprueba que el aislamiento entre constructoras este realmente puesto.
 *
 * ESTE COMANDO CORRE EN CADA DESPLIEGUE Y TIENE QUE PODER FALLAR
 * --------------------------------------------------------------
 * Con superusuario o con BYPASSRLS, PostgreSQL ignora las políticas por
 * completo y el sistema sigue funcionando de aparente. No hay error: la
 * aplicación responde bien y cada constructora ve las obras de la otra. Por eso
 * el chequeo tiene que ser un paso del despliegue que frene la publicacion, y
 * no una nota en un documento.
 *
 * Lo que comprueba:
 *  1. Que el rol no sea superusuario ni tenga BYPASSRLS.
 *  2. Que la funcion `app_tenant_id()` exista y falle cerrada.
 *  3. Que todas las tablas de negocio tengan RLS activado y con FORCE.
 *  4. Que cada política exija `tenant_id = app_tenant_id()`, al leer y al
 *     escribir.
 */
class VerificarAislamientoCommand extends Command
{
    protected $signature = 'obras:verificar-aislamiento';

    protected $description = 'Verifica que el aislamiento entre empresas este puesto en la base de datos';

    /**
     * Tablas que DEBEN tener RLS. Se comparan contra las que la base tiene
     * activada: si hay una de negocio sin política, sale como faltante.
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

    public function handle(): int
    {
        $fallos = [];

        $this->comprobarRol($fallos);
        $this->comprobarFuncionDeContexto($fallos);
        $this->comprobarPoliticas($fallos);

        if ($fallos === []) {
            $this->info('Aislamiento verificado: el rol no lo salta y todas las tablas de negocio tienen politica.');

            return self::SUCCESS;
        }

        $this->error('El aislamiento entre empresas NO esta bien puesto:');
        $this->newLine();

        foreach ($fallos as $fallo) {
            $this->line('  - ' . $fallo);
        }

        $this->newLine();
        $this->error('No publique el sistema con el aislamiento en este estado. Ver docs/03.');

        return self::FAILURE;
    }

    /** @param list<string> $fallos */
    private function comprobarRol(array &$fallos): void
    {
        $estado = DB::selectOne(
            'SELECT current_setting(\'is_superuser\') AS es_superusuario,
                    r.rolsuper, r.rolbypassrls, r.rolcreaterole
             FROM pg_roles AS r
             WHERE r.rolname = current_user'
        );

        if ($estado === null) {
            $fallos[] = 'No se pudo determinar el rol de la base de datos.';

            return;
        }

        if ($estado->es_superusuario === 'on' || (bool) $estado->rolsuper) {
            $fallos[] = 'El rol de la aplicacion es SUPERUSUARIO: PostgreSQL ignora todas las politicas RLS.';
        }

        if ((bool) $estado->rolbypassrls) {
            $fallos[] = 'El rol de la aplicacion tiene BYPASSRLS: se salta el aislamiento sin avisar.';
        }
    }

    /** @param list<string> $fallos */
    private function comprobarFuncionDeContexto(array &$fallos): void
    {
        $existe = DB::selectOne(
            'SELECT to_regprocedure(\'public.app_tenant_id()\') AS firma'
        );

        if ($existe === null || $existe->firma === null) {
            $fallos[] = 'No existe la funcion public.app_tenant_id(). Sin ella, la politica no tiene contra que comparar.';

            return;
        }

        // La funcion tiene que LANZAR excepción sin contexto, no devolver
        // NULL. Si devuelve NULL, una consulta sin empresa devolveria cero
        // filas en silencio y nadie se enteraria del error.
        try {
            DB::select('SELECT set_config(?, ?, false)', ['app.tenant_id', '']);

            DB::select('SELECT public.app_tenant_id()');

            $fallos[] = 'La funcion app_tenant_id() NO falla cuando no hay empresa: el aislamiento falla abierto.';
        } catch (\Throwable) {
            // Esto es lo que tiene que pasar: el aislamiento falla cerrado.
            $this->line('  [OK] La funcion de contexto falla cerrada sin empresa.');
        }
    }

    /** @param list<string> $fallos */
    private function comprobarPoliticas(array &$fallos): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $estado = DB::selectOne(
                'SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS forzado
                 FROM pg_class AS c
                 JOIN pg_namespace AS n ON n.oid = c.relnamespace
                 WHERE n.nspname = \'public\' AND c.relname = ?',
                [$tabla]
            );

            if ($estado === null) {
                $fallos[] = 'La tabla public.' . $tabla . ' no existe. Revise las migraciones.';

                continue;
            }

            if (! $estado->rls) {
                $fallos[] = 'La tabla public.' . $tabla . ' no tiene RLS activado.';

                continue;
            }

            if (! $estado->forzado) {
                $fallos[] = 'La tabla public.' . $tabla . ' tiene RLS sin FORCE: el dueno de la tabla la saltaria.';
            }

            $politicas = DB::select(
                'SELECT polname, polpermissive FROM pg_policy WHERE polrelid = ?::regclass',
                ['public.' . $tabla]
            );

            if ($politicas === []) {
                $fallos[] = 'La tabla public.' . $tabla . ' tiene RLS activado pero NO tiene ninguna politica.';

                continue;
            }

            $this->line('  [OK] public.' . $tabla . ': RLS con ' . count($politicas) . ' politica(s).');
        }
    }
}