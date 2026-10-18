<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Seguridad\StoreRegistroSeguridadRequest;
use App\Http\Resources\RegistroSeguridadResource;
use App\Models\Obra;
use App\Models\RegistroSeguridad;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Accidentes y casi accidentes.
 *
 * Los dos se registran por la misma puerta y se DEVUELVEN SEPARADOS: el
 * listado trae `filtro=clase` y el resumen cuenta cuantos hay de cada clase,
 * sin sumarlos.
 *
 * Un casi accidente que se diluye dentro de "no paso nada" es un accidente
 * que se va a repetir. Que el sistema obligue a elegir la clase al escribir es
 * la medida más barata y más efectiva para que no se diluyan.
 */
class RegistroSeguridadController extends Controller
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    public function index(Request $request, Obra $obra): SymfonyResponse
    {
        $this->authorize('view', $obra);

        $registros = $obra->registrosSeguridad()
            ->with('reportadoPor')
            ->when(
                $request->query('clase'),
                fn ($consulta, $clase) => $consulta->where('clase', $clase)
            )
            ->orderByDesc('fecha_ocurrencia')
            ->paginate(25);

        // El resumen viene con el listado, separado por clase, para que la
        // pantalla no tenga que armar el total a mano.
        $todos = $obra->registrosSeguridad()
            ->selectRaw('clase, COUNT(*) AS total')
            ->groupBy('clase')
            ->pluck('total', 'clase');

        return RegistroSeguridadResource::collection($registros)
            ->additional([
                'resumen' => [
                    'accidentes' => (int) ($todos['accidente'] ?? 0),
                    'casi_accidentes' => (int) ($todos['casi_accidente'] ?? 0),
                    'nota' => 'Los accidentes y los casi accidentes no se suman: son clases distintas.',
                ],
            ])
            ->response();
    }

    public function store(StoreRegistroSeguridadRequest $request, Obra $obra): SymfonyResponse
    {
        $this->authorize('view', $obra);

        $datos = $request->validated();

        $registro = $this->contexto->ejecutarAislado(
            (string) $obra->tenant_id,
            fn (): RegistroSeguridad => RegistroSeguridad::query()->create([
                ...$datos,
                'obra_id' => $obra->id,
                'tenant_id' => $obra->tenant_id,
            ])
        );

        return (new RegistroSeguridadResource($registro->load('reportadoPor')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Borrado del dato personal de un accidente ya cerrado.
     *
     * Ley 19.628. Deja el hecho, la lesión en texto y las horas perdidas, y
     * quita nombre y RUT. Se puede pedir en cualquier momento por el titular
     * del dato; por eso esta como endpoint propio y no como una tarea de
     * mantenimiento de la base.
     */
    public function anonimizar(RegistroSeguridad $registro): SymfonyResponse
    {
        $this->authorize('update', $registro);

        if (! $registro->tieneDatosPersonales()) {
            return response()->json([
                'mensaje' => 'El registro ya estaba anonimizado.',
            ], 409);
        }

        $this->contexto->ejecutarAislado(
            (string) $registro->tenant_id,
            static fn () => $registro->anonizar()
        );

        return (new RegistroSeguridadResource($registro->fresh()))->response();
    }
}