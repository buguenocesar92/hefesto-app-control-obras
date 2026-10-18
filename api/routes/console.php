<?php

declare(strict_types=1);

use App\Enums\EstadoTarea;
use App\Models\Tenant;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Mostrar una cita inspiradora');

/*
|------------------------------------------------------------------------------
| Revision diaria de vencimientos
|------------------------------------------------------------------------------
|
| Esta rutina existe por una sola razon: una tarea con plazo que nadie mira
| deja de ser una tarea con plazo. Revisa, empresa por empresa, las tareas
| vencidas y las que vencen manana, y deja el dato en el log.
|
| POR QUE TOMA LA EMPRESA UNA POR UNA
| -----------------------------------
| Sin el contexto fijo, `public.app_tenant_id()` lanzaria excepcion y esta
| rutina no veria nada. Y NO se puede fijar con un `SET` normal: el scheduler y
| el worker de colas reutilizan la misma conexion para empresas distintas, y un
| `SET` a sesion dejaria el valor pegado. Por eso cada empresa corre en su
| propia transaccion con `ejecutarAislado()`, que descarta el valor al
| terminar. Ver docs/03.
|
*/

Artisan::command('obras:revisar-vencimientos', function (): void {
    $contexto = new ContextoTenant();

    Tenant::query()
        ->where('activo', true)
        ->each(function ($empresa) use ($contexto): void {
            $contexto->ejecutarAislado((string) $empresa->id, function () use ($empresa): void {
                $manana = now()->addDay()->toDateString();

                $vencidas = \App\Models\Tarea::query()
                    ->whereIn('estado', [EstadoTarea::Pendiente->value, EstadoTarea::EnEjecucion->value])
                    ->whereNotNull('fecha_plazo')
                    ->whereDate('fecha_plazo', '<', now()->toDateString())
                    ->count();

                $porVencer = \App\Models\Tarea::query()
                    ->whereIn('estado', [EstadoTarea::Pendiente->value, EstadoTarea::EnEjecucion->value])
                    ->whereNotNull('fecha_plazo')
                    ->whereDate('fecha_plazo', now()->toDateString())
                    ->whereDate('fecha_plazo', $manana)
                    ->count();

                if ($vencidas > 0 || $porVencer > 0) {
                    $this->line(sprintf(
                        '[%s] %d vencidas y %d por vencer manana.',
                        $empresa->razon_social,
                        $vencidas,
                        $porVencer
                    ));
                }
            });
        });
})->purpose('Revisar tareas vencidas y por vencer, empresa por empresa');