<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administracion\StoreUsuarioRequest;
use App\Models\Obra;
use App\Models\TipoInspeccion;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administracion: responsables de obra, tipos de inspección y catalogos.
 *
 * Las personas y los tipos de inspección son de la EMPRESA, no de la obra: se
 * definen una vez y sirven para todas las obras de la constructora. Por eso no
 * cuelgan de `/obras/{obra}`.
 *
 * La empresa sale del contexto de la sesión, nunca del cuerpo de la petición.
 */
class AdministracionController extends Controller
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    /** Responsables de obra de la empresa. */
    public function usuarios(): Response
    {
        $this->authorize('viewAny', Obra::class);

        return response()->json(
            Usuario::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'correo', 'cargo', 'rol', 'activo'])
        );
    }

    public function crearUsuario(StoreUsuarioRequest $request): Response
    {
        $this->authorize('create', Obra::class);

        $usuario = $this->contexto->ejecutarAislado(
            (string) auth()->user()->tenant_id,
            fn (): Usuario => Usuario::query()->create($request->validated())
        );

        return response()->json($usuario, 201);
    }

    /** Tipos de inspección de la empresa. */
    public function tiposInspeccion(): Response
    {
        $this->authorize('viewAny', Obra::class);

        return response()->json(
            TipoInspeccion::query()->orderBy('nombre')->get()
        );
    }

    /** Estados y catálogos, para que la interfaz no los escriba a mano. */
    public function catalogos(): Response
    {
        return response()->json([
            'estados_obra' => \App\Enums\EstadoObra::valores(),
            'estados_etapa' => \App\Enums\EstadoEtapa::valores(),
            'estados_tarea' => \App\Enums\EstadoTarea::valores(),
            'estados_inspeccion' => \App\Enums\EstadoInspeccion::valores(),
            'estados_hallazgo' => \App\Enums\EstadoHallazgo::valores(),
            'gravedades' => \App\Enums\GravedadHallazgo::valores(),
            'clases_registro_seguridad' => \App\Enums\ClaseRegistroSeguridad::valores(),
            'estados_avance_pago' => \App\Enums\EstadoAvancePago::valores(),
            'clases_bitacora' => \App\Enums\ClaseAsuntoBitacora::valores(),
            'roles' => \App\Enums\RolUsuario::valores(),
        ]);
    }
}