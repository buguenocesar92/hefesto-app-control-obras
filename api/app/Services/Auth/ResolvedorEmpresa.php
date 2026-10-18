<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resuelve que persona es y de que empresa, a partir del token verificado.
 *
 * POR QUE SE NECESITA EL SLUG DE LA EMPRESA
 * ----------------------------------------
 * `usuarios.correo` es único DENTRO de una empresa, no globalmente: es normal
 * que dos constructoras distintas tengan a alguien con el mismo correo. Por
 * eso el inicio de sesión va con el slug de la empresa elegida, y la busqueda
 * usa las dos cosas a la vez.
 *
 * El slug se lee de `vw_tenants_publicos` y no de `tenants` cruda, porque en
 * ese momento todavia no hay sesión y por lo tanto no hay empresa en la
 * conexión: leer la tabla entera a mano antes del login abriria el RUT y el
 * nombre completo de todas las empresas registradas.
 *
 * Y SI EL CORREO NO ESTA REGISTRADO, NO SE CREA NADIE
 * --------------------------------------------------
 * El acceso se da por invitacion del administrador de la empresa, no por
 * registro abierto. Si el correo no existe en `usuarios`, el inicio de sesión
 * falla: una obra con los datos de sus trabajadores no es algo que se abra al
 * que llega con un correo cualquiera.
 */
class ResolvedorEmpresa
{
    /**
     * @param  array<string, mixed>  $reclamos  Reclamos del token ya verificado.
     */
    public function resolver(string $slug, array $reclamos): Usuario
    {
        $correo = mb_strtolower(trim((string) $reclamos['email']));

        // Vista pública: tres columnas, solo empresas activas.
        $empresa = DB::table('vw_tenants_publicos')
            ->where('slug', $slug)
            ->where('activo', true)
            ->first();

        if ($empresa === null) {
            throw new RuntimeException('La empresa indicada no existe o esta inactiva.');
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            throw new RuntimeException('La empresa indicada no existe.');
        }

        $usuario = Usuario::query()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(correo) = ?', [$correo])
            ->first();

        if ($usuario === null) {
            throw new RuntimeException(
                'El correo no esta registrado en ' . $tenant->razon_social
                . '. Pida al administrador de la empresa que lo registre.'
            );
        }

        if (! $usuario->activo) {
            throw new RuntimeException('La cuenta esta desactivada.');
        }

        return $usuario;
    }

    /** @return list<array<string, mixed>> Empresas habilitadas para la pantalla de entrada. */
    public function empresasPublicas(): array
    {
        return DB::table('vw_tenants_publicos')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['slug', 'nombre'])
            ->map(static fn ($fila): array => [
                'slug' => $fila->slug,
                'nombre' => $fila->nombre,
            ])
            ->all();
    }
}