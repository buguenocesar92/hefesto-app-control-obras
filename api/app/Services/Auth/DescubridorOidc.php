<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Descubrimiento del proveedor OIDC de Authentik.
 *
 * Se usa el documento de descubrimiento estandar en lugar de escribir la URL
 * a mano: así el mismo codigo sirve para el Authentik de desarrollo y para el
 * de produccion, que casi nunca coinciden. Ver la especificacion OpenID
 * Connect Discovery 1.0.
 *
 * El documento se cachea porque se consulta en cada inicio de sesión y no
 * cambia seguido. Si falla la consulta se levanta la excepción: es preferible
 * que el inicio de sesión falle de forma clara a que se acepte un token sin
 * haber comprobado las claves.
 */
class DescubridorOidc
{
    private const CACHE_MINUTOS = 60;

    public function __construct(private readonly string $emisor) {}

    /** @return array<string, mixed> */
    public function documento(): array
    {
        $clave = 'oidc:descubrimiento:' . sha1($this->emisor);

        $documento = Cache::remember($clave, self::CACHE_MINUTOS, function (): array {
            $respuesta = Http::timeout(10)
                ->accept('application/json')
                ->get(rtrim($this->emisor, '/') . '/.well-known/openid-configuration');

            if (! $respuesta->successful()) {
                throw new RuntimeException(
                    'Authentik no respondio el documento de descubrimiento OIDC ('
                    . $respuesta->status() . '). Revise AUTHENTIK_ISSUER y que el proveedor este arriba.'
                );
            }

            $datos = $respuesta->json();

            return is_array($datos) ? $datos : [];
        });

        return $documento;
    }

    public function urlDeAutorizacion(): string
    {
        return (string) $this->documento()['authorization_endpoint'];
    }

    public function urlDeToken(): string
    {
        return (string) $this->documento()['token_endpoint'];
    }

    public function urlDeJwks(): string
    {
        return (string) $this->documento()['jwks_uri'];
    }

    /**
     * Claves publicas del proveedor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function jwks(): array
    {
        $respuesta = Http::timeout(10)->accept('application/json')->get($this->urlDeJwks());

        if (! $respuesta->successful()) {
            throw new RuntimeException(
                'No se pudieron obtener las claves publicas de Authentik (' . $respuesta->status() . ').'
            );
        }

        $claves = $respuesta->json()['keys'] ?? [];

        return is_array($claves) ? array_values($claves) : [];
    }
}