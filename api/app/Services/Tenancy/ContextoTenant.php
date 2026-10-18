<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contexto de empresa de la petición.
 *
 * La RLS de PostgreSQL (migración 2026_10_17_000014) lee la empresa desde la
 * variable de sesión `app.tenant_id`. Esta clase es la Única que la escribe.
 * Si alguien la fija por otro lado, el comportamiento deja de estar
 * garantizado, por eso `establecer()` es el único punto de entrada.
 *
 * ALCANCE DE Transacción
 * ----------------------
 * Se usa `set_config(..., true)`: el valor desaparece al confirmar o revertir
 * la transacción.
 *
 * Con un `SET` normal, de alcance de sesión, el valor sobrevivia a la
 * petición. Y el worker de colas REUTILIZA la misma conexión para trabajos de
 * empresas distintas: un `SET` a sesión significa que un trabajo de la
 * Constructora A podría leer los datos de la Constructora B. Ese es
 * exactamente el fallo que este diseno evita, y por eso `establecer()` EXIGE
 * estar dentro de una transacción y lanza excepción si no lo esta.
 *
 * Mismo diseno que `apps/portal-autoservicio`: ver docs/03.
 */
class ContextoTenant
{
    /** Variable de sesión de PostgreSQL que lee la política RLS. */
    public const VARIABLE = 'app.tenant_id';

    /**
     * Fija la empresa de la petición en la conexión actual.
     *
     * @throws RuntimeException si no hay una transacción abierta.
     */
    public function establecer(string $empresaId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'No se puede fijar el contexto de empresa fuera de una transaccion. '
                . 'Use App\Services\Tenancy\ContextoTenant::ejecutarAislado().'
            );
        }

        DB::select('SELECT set_config(?, ?, true)', [self::VARIABLE, $empresaId]);
    }

    /**
     * Ejecuta una operación dentro de una transacción con la empresa fija. Al
     * salir, PostgreSQL descarta el valor de la variable.
     *
     * @template T
     *
     * @param  Closure(): T  $operación
     * @return T
     */
    public function ejecutarAislado(string $empresaId, Closure $operacion): mixed
    {
        return DB::transaction(function () use ($empresaId, $operacion) {
            $this->establecer($empresaId);

            return $operacion();
        });
    }

    /**
     * Lee la variable de sesión sin pasar por `public.app_tenant_id()`, que
     * lanza excepción cuando no hay contexto.
     *
     * Si devuelve null, ninguna política RLS de la base va a devolver filas.
     */
    public function empresaIdEnConexion(): ?string
    {
        $fila = DB::selectOne('SELECT current_setting(?, true) AS valor', [self::VARIABLE]);

        $valor = $fila->valor ?? null;

        return $valor === null || $valor === '' ? null : $valor;
    }

    /** Identificador de la empresa autenticada, si lo hay. */
    public function empresaIdSolicitada(): ?string
    {
        return auth()->user()?->tenant_id;
    }
}