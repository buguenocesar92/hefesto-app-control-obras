<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoObra;
use App\Http\Requests\Obras\StoreObraRequest;
use App\Http\Resources\ObraResource;
use App\Models\Obra;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Obras: alta, edicion y lectura.
 *
 * La empresa de toda fila sale del contexto de la sesión, nunca del cuerpo
 * de la petición. Por eso los `store` no reciben `tenant_id`: ver
 * `StoreObraRequest`.
 */
class ObraController extends Controller
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    public function index(Request $request): SymfonyResponse
    {
        $this->authorize('viewAny', Obra::class);

        $obras = Obra::query()
            ->when($request->query('estado'), fn ($consulta, $estado) => $consulta->where('estado', $estado))
            ->when($request->query('q'), function ($consulta, $texto): void {
                $consulta->where(function ($interna) use ($texto): void {
                    $interna->where('nombre', 'ilike', '%' . $texto . '%')
                        ->orWhere('codigo', 'ilike', '%' . $texto . '%');
                });
            })
            ->orderBy('codigo')
            ->paginate(25);

        return ObraResource::collection($obras)->response();
    }

    public function show(Obra $obra): SymfonyResponse
    {
        $this->authorize('view', $obra);

        return (new ObraResource($obra->load('empresa')))->response();
    }

    public function store(StoreObraRequest $request): SymfonyResponse
    {
        $this->authorize('create', Obra::class);

        $datos = $request->validated();

        // La empresa la pone el contexto de la sesión. El indice único
        // (tenant_id, codigo) impide que dos obras de la misma constructora
        // compartan codigo.
        $obra = $this->contexto->ejecutarAislado(
            (string) auth()->user()->tenant_id,
            fn (): Obra => Obra::query()->create($datos)
        );

        return (new ObraResource($obra))->response()->setStatusCode(201);
    }

    public function update(StoreObraRequest $request, Obra $obra): SymfonyResponse
    {
        $this->authorize('update', $obra);

        $obra->update($request->validated());

        return (new ObraResource($obra->refresh()))->response();
    }

    /**
     * Baja logica de la obra.
     *
     * Se marca terminada en vez de borrar: el historial de una obra tiene que
     * sobrevivir a que termine, porque es lo que se revisa en un reclamo.
     */
    public function destroy(Obra $obra): Response
    {
        $this->authorize('delete', $obra);

        $obra->update(['estado' => EstadoObra::Terminada->value]);

        return response()->noContent();
    }
}