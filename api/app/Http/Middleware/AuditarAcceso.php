<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra los accesos a datos de obra.
 *
 * Por que existe, siendo que la RLS ya impide que alguien vea una obra
 * ajena: la RLS protege el dato, pero no deja rastro de quien pregunto por
 * que. En una obra con datos de trabajadores y accidents, "quien entro a
 * mirar el registro de accidentes del mes pasado" es una pregunta que hay que
 * poder responder después.
 *
 * Solo se anotan las operaciones de lectura sobre las tablas que más pesan
 * (accidentes, inspecciones, estados de cuenta). Anotar cada SELECT llenaria el
 * registro de ruido.
 */
class AuditarAcceso
{
    private const RUTAS_AUDITADAS = [
        'api/v1/registros-seguridad',
        'api/v1/inspecciones',
        'api/v1/avances-pago',
        'api/v1/estado-de-cuenta',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $usuario = auth()->guard('web')->user();

        if ($usuario !== null && $this->esAuditable($request)) {
            Log::info('acceso a datos de obra', [
                'usuario_id' => $usuario->id,
                'empresa_id' => $usuario->tenant_id,
                'rol' => $usuario->rol->value,
                'ruta' => $request->path(),
                'metodo' => $request->method(),
                'respuesta' => $respuesta->getStatusCode(),
                'ip' => $request->ip(),
            ]);
        }

        return $respuesta;
    }

    private function esAuditable(Request $request): bool
    {
        foreach (self::RUTAS_AUDITADAS as $prefijo) {
            if (str_starts_with($request->path(), $prefijo)) {
                return true;
            }
        }

        return false;
    }
}