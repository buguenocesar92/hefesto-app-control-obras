<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ClaseAsuntoBitacora;
use App\Models\BitacoraObra;
use App\Models\Obra;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bitacora de obra: el registro de las decisiones.
 *
 * El motivo de que exista esta pantalla y no una libreta es que un acuerdo
 * necesita fecha y autor. El autor sale de la sesión, nunca del cuerpo de la
 * petición: si se aceptara desde el cliente, el "quien acordo" sería tan
 * editable como el acuerdo mismo.
 */
class BitacoraController extends Controller
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    public function index(Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $asuntos = $obra->bitacora()
            ->with('autor')
            ->paginate(50);

        return response()->json($asuntos);
    }

    public function store(Request $request, Obra $obra): Response
    {
        $this->authorize('create', $obra);

        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:now'],
            'clase' => ['required', Rule::in(ClaseAsuntoBitacora::valores())],
            'asunto' => ['required', 'string', 'max:180'],
            'cuerpo' => ['nullable', 'string', 'max:10000'],
            'tarea_relacionada_id' => ['nullable', 'uuid'],
        ], [
            'fecha.before_or_equal' => 'No se puede registrar un acuerdo con fecha futura.',
            'asunto.required' => 'El acuerdo necesita un asunto: en tres meses es lo unico que se va a leer.',
        ]);

        $asunto = $this->contexto->ejecutarAislado(
            (string) $obra->tenant_id,
            fn (): BitacoraObra => BitacoraObra::query()->create([
                ...$datos,
                'obra_id' => $obra->id,
                'tenant_id' => $obra->tenant_id,
                'autor_id' => auth()->id(),
            ])
        );

        return response()->json($asunto->load('autor'), 201);
    }
}