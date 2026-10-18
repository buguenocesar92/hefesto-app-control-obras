<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Marca automática de `tenant_id`.
 *
 * LA RLS YA AISLA. ESTO ES LA SEGUNDA CAPA
 * ----------------------------------------
 * La política de PostgreSQL impide leer y escribir filas de otra empresa.
 * Este trait no reemplaza esa garantia: la refuerza, y sobre todo evita el
 * error de LOGICA más comun, que es olvidar el `tenant_id` al crear una fila y
 * que la base lo rechace con una excepción que no le dice nada a quien esta en
 * la obra.
 *
 * Al crear una fila:
 *  - si el usuario esta autenticado y la fila no trae `tenant_id`, se
 *    completa sola con la empresa del usuario;
 *  - si la fila trae un `tenant_id` DISTINTO al del usuario, se lanza una
 *    excepción aquí, con un mensaje que si se entiende, en vez de dejar que
 *    la RLS rechace la fila con un error de PostgreSQL.
 *
 * Por que NO es un GlobalScope: un GlobalScope de `tenant_id` dependeria de
 * que haya un usuario autenticado. En las pruebas de aislamiento, en los
 * comandos de consola y en el worker de colas no lo hay, y el scope filtraria
 * todo sin avisar. El aislamiento real es la RLS; esto es la red de
 * seguridad del `INSERT`.
 */
trait MarcaEmpresa
{
    public static function bootMarcaEmpresa(): void
    {
        static::creating(function (Model $modelo): void {
            $usuario = auth()->user();

            $empresaDelUsuario = $usuario?->tenant_id;

            $empresaDeLaFila = $modelo->getAttribute('tenant_id');

            if ($empresaDeLaFila === null && $empresaDelUsuario !== null) {
                $modelo->setAttribute('tenant_id', $empresaDelUsuario);

                return;
            }

            if ($empresaDeLaFila !== null && $empresaDelUsuario !== null && $empresaDeLaFila !== $empresaDelUsuario) {
                throw new RuntimeException(
                    'Se intento guardar una fila de ' . static::class
                    . ' con una empresa distinta a la del usuario autenticado. '
                    . 'Si esto aparece en una operacion legitima, el problema es una consulta sin filtro '
                    . 'por empresa; no es un error de permisos.'
                );
            }
        });
    }
}