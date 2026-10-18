<?php

declare(strict_types=1);

use App\Http\Middleware\AuditarAcceso;
use App\Http\Middleware\ResolverTenant;
use App\Models\AvancePago;
use App\Models\BitacoraObra;
use App\Models\Etapa;
use App\Models\Hallazgo;
use App\Models\Inspeccion;
use App\Models\Obra;
use App\Models\RegistroSeguridad;
use App\Models\Tarea;
use App\Models\TipoInspeccion;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Punto de arranque de la API de Control de Obras.
 *
 * Este archivo reemplaza al que genera `composer create-project laravel/laravel`.
 * El resto del esqueleto (artisan, public/index.php, storage) lo genera
 * Composer. Ver docs/04-Instalación.md, paso 2.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/api/v1/salud',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El orden importa: `ResolverTenant` va primero, antes del `auth` de
        // la ruta, porque resuelve el usuario por su cuenta con el guard
        // `web` y envuelve el resto de la petición en una transacción con la
        // empresa ya fijada.
        $middleware->web(append: [
            ResolverTenant::class,
            AuditarAcceso::class,
        ]);

        $middleware->alias([
            'empresa.resuelta' => ResolverTenant::class,
        ]);
    })
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
    ])
    ->withSingletons(function (): void {
        $app = app();

        $app->singleton(\App\Services\Auth\DescubridorOidc::class, static fn ($app) => new \App\Services\Auth\DescubridorOidc(
            (string) $app['config']->get('authentik.issuer'),
        ));

        $app->singleton(\App\Services\Auth\VerificadorIdToken::class, static fn ($app) => new \App\Services\Auth\VerificadorIdToken(
            $app->make(\App\Services\Auth\DescubridorOidc::class),
            (string) $app['config']->get('authentik.issuer'),
            (string) $app['config']->get('authentik.client_id'),
            (string) $app['config']->get('authentik.algorithm'),
        ));

        $app->singleton(\App\Services\Avance\CalculadorAvanceFisico::class);
        $app->singleton(\App\Services\Avance\CalculadorAvanceFinanciero::class);
        $app->singleton(\App\Services\Avance\EstadoDeCuentaObra::class);
        $app->singleton(\App\Services\Tenancy\ContextoTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Un rechazo de autorizacion dentro de `api/v1` responde 404, no 403.
         *
         * Un 403 confirmaria que el recurso existe. La RLS ya impide que una
         * obra de otra constructora sea visible, así que 404 es además la
         * respuesta correcta en el caso habitual: si no la ves, no existe.
         */
        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'mensaje' => 'No encontrado.',
                ], 404);
            }

            return null;
        });

        /*
         * Un error de validación de la base que viene de la RLS se traduce a
         * un 422 legible en vez de un 500.
         *
         * Sin esto, intentar guardar una fila en nombre de otra empresa
         * devuelve un error 500 con un detalle de PostgreSQL que no le dice
         * nada a quien esta en la obra.
         */
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            if ($request->is('api/v1/*') && $e->getCode() === '42501') {
                return response()->json([
                    'mensaje' => 'La operacion fue rechazada por el aislamiento de empresa. '
                        . 'Revise que el recurso pertenezca a su empresa.',
                ], 422);
            }

            return null;
        });
    })
    ->create();