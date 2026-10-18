<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RolUsuario;
use App\Models\Tenant;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta de una empresa constructora y de su primer administrador.
 *
 * POR QUE EXISTE ESTE COMANDO Y NO UN FORMULARIO
 * ----------------------------------------------
 * El alta de una empresa tiene que pasar SIEMPRE por el contexto de empresa:
 * `usuarios` tiene RLS y sin `app.tenant_id` no se puede escribir ni una fila.
 * Un formulario web llega con el contexto puesto, así que podría; un comando
 * de consola no. Por eso el alta se hace aca, que envuelve la escritura en una
 * transacción con la empresa ya fijada.
 *
 * También evita que el primer usuario se cree solo: la primera cuenta de una
 * instancia recien desplegada la define el operador del servidor, no el que
 * llega primero por la puerta.
 */
class CrearEmpresaCommand extends Command
{
    protected $signature = 'obras:crear-empresa
        {--razon= : Razon social de la empresa constructora}
        {--rut= : RUT sin puntos ni guion}
        {--slug= : Identificador en la URL de entrada}
        {--admin-nombre= : Nombre del primer administrador}
        {--admin-correo= : Correo del primer administrador}
        {--admin-rol=administrador : Rol del primer administrador}';

    protected $description = 'Crea una empresa constructora y su primer administrador';

    public function handle(): int
    {
        $razon = (string) ($this->option('razon') ?: $this->ask('Razon social'));
        $rut = (string) ($this->option('rut') ?: $this->ask('RUT sin puntos ni guion'));
        $slug = (string) ($this->option('slug') ?: Str::slug($razon));
        $adminNombre = (string) ($this->option('admin-nombre') ?: $this->ask('Nombre del administrador'));
        $adminCorreo = (string) ($this->option('admin-correo') ?: $this->ask('Correo del administrador'));

        $rolAdmin = (string) $this->option('admin-rol');

        if (! in_array($rolAdmin, RolUsuario::valores(), true)) {
            $this->error('El rol debe ser uno de: ' . implode(', ', RolUsuario::valores()));

            return self::FAILURE;
        }

        if (! preg_match('/^[0-9]{7,8}-?[0-9Kk]$/', $rut)) {
            $this->error('El RUT no tiene el formato esperado. Ejemplo: 76123456');

            return self::FAILURE;
        }

        // `tenants` NO tiene RLS, así que se puede escribir aca. `usuarios` si
        // lo tiene, y por eso el alta de la persona va dentro de la
        // transacción con el contexto ya fijado.
        $empresa = Tenant::query()->create([
            'razon_social' => $razon,
            'rut' => $rut,
            'slug' => $slug,
            'activo' => true,
        ]);

        DB::transaction(function () use ($empresa, $adminNombre, $adminCorreo, $rolAdmin): void {
            DB::select('SELECT set_config(?, ?, true)', ['app.tenant_id', $empresa->id]);

            Usuario::query()->create([
                'tenant_id' => $empresa->id,
                'nombre' => $adminNombre,
                'correo' => mb_strtolower(trim($adminCorreo)),
                'rol' => $rolAdmin,
                'activo' => true,
            ]);
        });

        $this->info('Empresa creada: ' . $empresa->razon_social . ' (' . $slug . ')');
        $this->info('Administrador: ' . $adminCorreo);
        $this->newLine();
        $this->comment('Verifique el aislamiento antes de publicar el sistema:');
        $this->line('  php artisan obras:verificar-aislamiento');

        return self::SUCCESS;
    }
}