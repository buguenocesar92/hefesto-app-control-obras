<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoHallazgo;
use App\Enums\EstadoTarea;
use App\Enums\OrigenTarea;
use App\Http\Requests\Tareas\StoreTareaRequest;
use App\Http\Resources\TareaResource;
use App\Models\Hallazgo;
use App\Models\Obra;
use App\Models\Tarea;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tareas: el tablero de lo que quedó comprometido.
 */
class TareaController extends Controller
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    public function index(Request $request, Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $tareas = $obra->tareas()
            ->with(['responsable', 'hallazgoOrigen'])
            ->when($request->query('estado'), fn ($c, $estado) => $c->where('estado', $estado))
            ->when($request->query('vencidas') === 'true', fn ($c) => $c
                ->whereNotNull('fecha_plazo')
                ->whereDate('fecha_plazo', '<', now()->toDateString()))
            ->orderByRaw('fecha_plazo IS NULL DESC')
            ->orderBy('fecha_plazo')
            ->paginate(50);

        return TareaResource::collection($tareas)->response();
    }

    /** Tareas que ya pasaron su plazo y siguen abiertas. */
    public function vencidas(Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $tareas = $obra->tareas()
            ->with('responsable')
            ->whereIn('estado', ['pendiente', 'en_ejecucion'])
            ->whereNotNull('fecha_plazo')
            ->whereDate('fecha_plazo', '<', now()->toDateString())
            ->orderBy('fecha_plazo')
            ->get();

        return TareaResource::collection($tareas)->response();
    }

    public function store(StoreTareaRequest $request, Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $tarea = $this->contexto->ejecutarAislado(
            (string) $obra->tenant_id,
            fn (): Tarea => Tarea::query()->create([
                ...$request->validated(),
                'obra_id' => $obra->id,
                'tenant_id' => $obra->tenant_id,
                'origen' => OrigenTarea::Manual,
                'creado_por_id' => auth()->id(),
            ])
        );

        return (new TareaResource($tarea->load('responsable')))->response()->setStatusCode(201);
    }

    public function update(StoreTareaRequest $request, Tarea $tarea): Response
    {
        $this->authorize('update', $tarea);

        $datos = $request->validated();

        // El origen no se puede cambiar desde aca: una corrección de
        // inspección no se convierte en tarea manual porque le moleste a
        // alguien, porque se perderia el rastro del hallazgo.
        unset($datos['origen'], $datos['origen_hallazgo_id'], $datos['origen_inspeccion_id']);

        $this->contexto->ejecutarAislado((string) $tarea->tenant_id, function () use ($tarea, $datos): void {
            if (($datos['estado'] ?? null) === 'completada') {
                $datos['fecha_cierre'] = now();
            }

            $tarea->update($datos);

            // EL HALLAZGO SE CIERRA CON LA TAREA
            // Si la corrección queda completada, el hallazgo pasa a
            // corregido. Al reverso NO: un hallazgo no se cierra sin que su tarea
            // se haya hecho.
            if (($datos['estado'] ?? null) === 'completada') {
                Hallazgo::query()
                    ->where('tarea_correccion_id', $tarea->id)
                    ->update(['estado' => EstadoHallazgo::Corregido->value]);
            }
        });

        return (new TareaResource($tarea->fresh()))->response();
    }
}