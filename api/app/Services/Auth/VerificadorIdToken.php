<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Verificación del token de identidad de Authentik.
 *
 * LA IMPORTANCIA DE VERIFICAR LA FIRMA
 * ------------------------------------
 * El `id_token` viene del navegador del usuario. Si se aceptara su contenido
 * sin comprobar la firma, cualquiera podría escribir `{"sub":"admin",
 * "email":"jefe@obra.cl"}` en el navegador y entrar como jefe. Por eso se
 * valida contra las claves publicas que pública el proveedor, y se comprueban
 * emisor y audiencia.
 *
 * Que la firma valga NO alcanza: también hay que comprobar que el token es
 * para ESTA aplicación y que viene de ESTE Authentik, porque un token valido
 * emitido para otra aplicación del mismo Authentik sirve para su audience y
 * no para la nuestra.
 */
class VerificadorIdToken
{
    private const CACHE_MINUTOS = 60;

    public function __construct(
        private readonly DescubridorOidc $descubridor,
        private readonly string $emisor,
        private readonly string $audiencia,
        private readonly string $algoritmo,
    ) {}

    /**
     * Devuelve los reclamos verificados del token.
     *
     * @return array<string, mixed>
     */
    public function verificar(string $idToken): array
    {
        $reclamos = JWT::decode(
            $idToken,
            JWK::parseKeySet($this->claves(), $this->algoritmo),
            // audience e issuer se comprueban acá, no aparte: si no se pasan
            // a la libreria, la validación no los mira.
            $this->algoritmo
        );

        $reclamos = (array) $reclamos;

        if (($reclamos['aud'] ?? null) !== $this->audiencia) {
            throw new RuntimeException('El token no fue emitido para esta aplicacion.');
        }

        if (($reclamos['iss'] ?? null) !== rtrim($this->emisor, '/')) {
            throw new RuntimeException('El token no viene del proveedor de identidad configurado.');
        }

        if (! isset($reclamos['sub']) || ! isset($reclamos['email'])) {
            throw new RuntimeException('El token no trae los datos minimos para identificar a la persona.');
        }

        return $reclamos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function claves(): array
    {
        return Cache::remember(
            'oidc:jwks:' . sha1($this->emisor),
            self::CACHE_MINUTOS,
            fn (): array => $this->descubridor->jwks()
        );
    }
}