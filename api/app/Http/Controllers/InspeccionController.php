<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoHallazgo;
use App\Enums\EstadoTarea;
use App\Http\Requests\Inspecciones\StoreInspeccionRequest;
use App\Http\Resources\InspeccionResource;
use App\Models\Hallazgo;
use App\Models\Inspeccion;
use App\Models\Obra;
use App\Services\Inspecciones\GeneradorTareasCorreccion;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inspecciones y hallazgos.
 *
 * EL FLUJO COMPLETO, EN UNA SOLA TRANSACCIÓN
 * ------------------------------------------
 * Se guarda la inspección, se guardan sus hallazgos y se genera la tarea de
 * corrección de cada hallazgo. Todo dentro de la misma transacción y con la
 * empresa ya fijada.
 *
 * Por que todo junto: si la inspección quedara guardada y la tarea fallara,
 * la obra tendria un hallazgo sin nadie responsable de corregirlo, que es el
 * escenario exacto que este modulo existe para evitar. O se registra todo, o
 * no se registra nada.
 */
class InspeccionController extends Controller
{
    public function __construct(
        private readonly ContextoTenant $contexto,
        private readonly GeneradorTareasCorreccion $generador,
    ) {}

    public function index(Request $request, Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $inspecciones = $obra->inspecciones()
            ->with(['tipo', 'inspector', 'hallazgos.tareaCorreccion'])
            ->orderByDesc('fecha_inspeccion')
            ->paginate(25);

        return InspeccionResource::collection($inspecciones)->response();
    }

    public function store(StoreInspeccionRequest $request, Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $datos = $request->validated();
        $hallazgos = $datos['hallazgos'] ?? [];
        unset($datos['hallazgos']);

        $respuesta = $this->contexto->ejecutarAislado(
            (string) $obra->tenant_id,
            function () use ($obra, $datos, $hallazgos): array {
                $inspeccion = Inspeccion::query()->create([
                    ...$datos,
                    'obra_id' => $obra->id,
                    'tenant_id' => $obra->tenant_id,
                ]);

                $generadas = [];

                foreach ($hallazgos as $item) {
                    $hallazgo = Hallazgo::query()->create([
                        'tenant_id' => $obra->tenant_id,
                        'inspeccion_id' => $inspeccion->id,
                        'descripcion' => $item['descripcion'],
                        'gravedad' => $item['gravedad'],
                        'estado' => EstadoHallazgo::Pendiente,
                    ]);

                    // Cada hallazgo nace con su tarea de corrección CON plazo.
                    // Si el inspector no indico responsable, la tarea se crea
                    // a nombre del propio inspector: es la persona que conoce
                    // el problema y que esta en la obra, y deja de quedar sin
                    // dueno.
                    $responsable = $item['responsable_id'] ?? $inspeccion->inspector_id;

                    $tarea = $this->generador->paraHallazgo(
                        $hallazgo,
                        (string) $responsable
                    );

                    $generadas[] = [
                        'hallazgo_id' => $hallazgo->id,
                        'tarea_id' => $tarea->id,
                        'fecha_plazo' => $tarea->fecha_plazo?->toDateString(),
                    ];
                }

                return [
                    'inspeccion' => $inspeccion->load(['tipo', 'inspector', 'hallazgos.tareaCorreccion']),
                    'tareas_generadas' => $generadas,
                ];
            }
        );

        return (new InspeccionResource($respuesta['inspeccion']))
            ->additional(['tareas_generadas' => $respuesta['tareas_generadas']])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Cierra un hallazgo por el camino largo: completando su tarea.
     *
     * No hay endpoint para "cerrar el hallazgo": un hallazgo se cierra cuando
     * la tarea queda completada, y la tarea se completa en su propio
     * endpoint. Así queda una sola forma de hacer las cosas y el historial
     * muestra siempre que hubo una corrección con fecha y responsable.
     */
    public function cerrarHallazgo(Hallazgo $hallazgo): Response
    {
        $this->authorize('update', $hallazgo);

        if (! $hallazgo->estaPendiente()) {
            return response()->json([
                'mensaje' => 'El hallazgo ya no esta pendiente.',
            ], 409);
        }

        $tarea = $hallazgo->tareaCorreccion;

        if ($tarea === null) {
            return response()->json([
                'mensaje' => 'Este hallazgo no tiene tarea de correccion. No se puede cerrar como corregido.',
            ], 409);
        }

        $this->contexto->ejecutarAislado((string) $hallazgo->tenant_id, function () use ($tarea): void {
            $tarea->update([
                'estado' => EstadoTarea::Completada->value,
                'fecha_cierre' => now(),
            ]);
        });

        return (new HallazgoResource($hallazgo->fresh()))->response();
    }
}