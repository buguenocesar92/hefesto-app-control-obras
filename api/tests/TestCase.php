<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fija la empresa del contexto para el resto de la prueba.
     *
     * POR QUÉ ACÁ SE USA ALCANCE DE SESIÓN Y EN PRODUCCIÓN NO
     * -------------------------------------------------------
     * En la aplicación real el contexto se fija con `set_config(..., true)`,
     * de alcance de Transacción, y por eso `ContextoTenant::establecer()`
     * exige estar dentro de una transacción: es la única forma de garantizar
     * que la conexión no arrastre el contexto de otra empresa cuando la
     * reutiliza el worker de colas.
     *
     * En una prueba se necesita lo contrario: dejar la empresa fija durante
     * TODA la prueba, sin envolver cada asercion en su propia transacción. Por
     * eso este ayudante usa alcance de sesión, a propósito y de forma aislada.
     * Lo que se prueba en `AislamientoDeDatosTest` es justamente que el
     * alcance de transacción NO sobrevive al cierre, que es lo que importa en
     * produccion.
     */
    protected function fijarEmpresa(string $empresaId): void
    {
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, $empresaId]);
    }

    /**
     * Crea una empresa y su primer administrador, con el contexto ya fijado.
     *
     * @return array{id: string, empresa: \App\Models\Tenant, usuario: \App\Models\Usuario}
     */
    protected function crearEmpresaConUsuario(string $slug = 'empresa'): array
    {
        $empresa = \App\Models\Tenant::query()->create([
            'razon_social' => 'Constructora ' . strtoupper($slug),
            'rut' => '76' . random_int(1000000, 9999999),
            'slug' => $slug . '-' . random_int(1000, 99999),
            'activo' => true,
        ]);

        $this->fijarEmpresa((string) $empresa->id);

        $usuario = \App\Models\Usuario::query()->create([
            'tenant_id' => $empresa->id,
            'nombre' => 'Persona de prueba',
            'correo' => 'persona@' . $slug . '.cl',
            'rol' => \App\Enums\RolUsuario::JefeObra->value,
            'activo' => true,
        ]);

        return [
            'id' => (string) $empresa->id,
            'empresa' => $empresa,
            'usuario' => $usuario,
        ];
    }
}