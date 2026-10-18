<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ClaseRegistroSeguridad;
use App\Enums\EstadoObra;
use App\Enums\RolUsuario;
use App\Http\Requests\Obras\StoreEtapaRequest;
use App\Http\Resources\EtapaResource;
use App\Models\Etapa;
use App\Models\Obra;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Etapas: donde vive el avance fisico.
 *
 * Al guardar una etapa se comprueba que los pesos de la obra sigan dando 100.
 * Si no dan, el avance fisico ponderado deja de significar una parte de la
 * obra, y el sistema lo dice al guardar en vez de dejarlo para que alguien lo
 * descubra en una reunion.
 *
 * El calculo del atraso NO vive aquí: se calcula desde las dos fechas cada
 * vez que se lee. Ver `Etapa::desviacionDias()`.
 */
class EtapaController extends Controller
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    public function index(Obra $obra): Response
    {
        $this->authorize('view', $obra);

        return EtapaResource::collection(
            $obra->etapas()->with('responsable')->get()
        )->response();
    }

    public function store(StoreEtapaRequest $request, Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $datos = $request->validated();

        $etapa = $this->contexto->ejecutarAislado(
            (string) $obra->tenant_id,
            function () use ($obra, $datos): Etapa {
                $orden = $datos['orden'];
                unset($datos['orden']);

                $etapa = Etapa::query()->create([
                    ...$datos,
                    'orden' => $orden,
                    'obra_id' => $obra->id,
                    'tenant_id' => $obra->tenant_id,
                ]);

                $this->advertirSobrePesos($obra);

                return $etapa;
            }
        );

        return (new EtapaResource($etapa))->response()->setStatusCode(201);
    }

    public function update(StoreEtapaRequest $request, Etapa $etapa): Response
    {
        $this->authorize('view', $etapa->obra);

        $this->contexto->ejecutarAislado(
            (string) $etapa->tenant_id,
            fn () => $etapa->update($request->validated())
        );

        return (new EtapaResource($etapa->fresh()))->response();
    }

    /**
     * Revisa la suma de pesos de la obra.
     *
     * No bloquea el guardado: en una obra recien cargada el plan va a estar
     * incompleto a propósito, y obligar a que cuadre en cada etapa dejaria
     * imposible cargar una obra de a poco. Avisa por log, que es donde lo
     * va a ver quien tiene que mirar.
     */
    private function advertirSobrePesos(Obra $obra): void
    {
        $suma = (float) $obra->etapas()->sum('peso');

        if (abs($suma - 100.0) > 0.01) {
            logger()->warning('La suma de pesos de la obra no da 100', [
                'obra_id' => $obra->id,
                'suma_pesos' => $suma,
                'aviso' => 'El avance fisico se calcula proporcionalmente, pero el plan esta incompleto.',
            ]);
        }
    }

    /** Estados posibles, para que la interfaz no los escriba a mano. */
    public function estados(): Response
    {
        return response()->json([
            'estados_obra' => EstadoObra::valores(),
            'roles' => RolUsuario::valores(),
            'clases_registro_seguridad' => ClaseRegistroSeguridad::valores(),
        ]);
    }
}