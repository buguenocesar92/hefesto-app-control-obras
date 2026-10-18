<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Avances\StoreAvancePagoRequest;
use App\Http\Resources\AvancePagoResource;
use App\Models\AvancePago;
use App\Models\Obra;
use App\Services\Avance\CalculadorAvanceFisico;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Avances de pago.
 *
 * EL NETO SE CALCULA ACA, NO SE RECIBE
 * ------------------------------------
 * `monto_neto` sale de `monto_bruto - deducciones` dentro del controlador. Si
 * lo mandara el navegador, un estado de cuenta podría traer un neto que no
 * cuadra con lo facturado, y eso se descubre cuando ya no se puede corregir.
 *
 * NOTA SOBRE EL AVANCE FISICO DE ESTA TABLA
 * -----------------------------------------
 * `avance_fisico_pct` que se guarda aca es la foto del avance fisico al
 * momento de emitir. El calculador lo completa si no viene, para que el estado
 * de cuenta no quede con un hueco, pero NO es el avance fisico de la obra:
 * ese se calcula aparte, desde las etapas. Que sean dos cosas distintas es lo
 * que permite auditar después por que se emitio ese pago.
 */
class AvancePagoController extends Controller
{
    public function __construct(
        private readonly ContextoTenant $contexto,
        private readonly CalculadorAvanceFisico $avanceFisico,
    ) {}

    public function index(Obra $obra): Response
    {
        $this->authorize('view', $obra);

        return AvancePagoResource::collection(
            $obra->avancesPago()->paginate(25)
        )->response();
    }

    public function store(StoreAvancePagoRequest $request, Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $datos = $request->validated();

        $bruto = (float) $datos['monto_bruto'];
        $deducciones = (float) ($datos['deducciones'] ?? 0);

        unset($datos['deducciones']);

        $datos['monto_neto'] = round($bruto - $deducciones, 2);

        // Si no viene la foto del avance fisico, se toma la de hoy. Es una
        // foto: si no se guarda aca, el estado de cuenta no queda auditable.
        if (($datos['avance_fisico_pct'] ?? null) === null) {
            $datos['avance_fisico_pct'] = $this->avanceFisico->calcular($obra)->porcentaje;
        }

        $pago = $this->contexto->ejecutarAislado(
            (string) $obra->tenant_id,
            fn (): AvancePago => AvancePago::query()->create([
                ...$datos,
                'obra_id' => $obra->id,
                'tenant_id' => $obra->tenant_id,
            ])
        );

        return (new AvancePagoResource($pago))->response()->setStatusCode(201);
    }
}