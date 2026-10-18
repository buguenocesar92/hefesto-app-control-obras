<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\ContextoTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa de la petición para que la RLS de PostgreSQL sepa de quien
 * son las filas.
 *
 * Se aplica a todo el grupo `web` (ver bootstrap/app.php), ANTES que el
 * middleware `auth` de la ruta. Por eso no puede usar `$request->user()`:
 * resuelve el usuario por su cuenta con el guard `web`, que ya lee la sesión.
 *
 * Peticiones sin sesión (login, retorno de Authentik, salud) pasan sin
 * transacción. Si esas rutas tocaran una tabla protegida,
 * `public.app_tenant_id()` lanzaria excepción y la petición fallaria: el
 * aislamiento falla cerrado, nunca abierto.
 *
 * La operación corre DENTRO de una transacción porque el contexto se fija con
 * `set_config(..., true)`, de alcance de transacción. Si el controlador
 * confirmara su propia transacción, el valor se perderia en el COMMIT y la
 * RLS dejaria de filtrar a partir de ese momento. Ver docs/03.
 */
class ResolverTenant
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = auth()->guard('web')->user();

        if ($usuario === null) {
            return $next($request);
        }

        if ($usuario->tenant_id === null) {
            // Una persona sin empresa no puede operar. Se corta antes de
            // tocar la base de datos.
            abort(403, 'La cuenta no esta asociada a ninguna empresa.');
        }

        return $this->contexto->ejecutarAislado(
            (string) $usuario->tenant_id,
            static fn () => $next($request)
        );
    }
}