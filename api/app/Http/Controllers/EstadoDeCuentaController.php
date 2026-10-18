<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Obra;
use App\Services\Avance\EstadoDeCuentaObra;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estado de cuenta de una obra.
 *
 * Es el endpoint que responde la pregunta de la reunion de obra: cuanto va la
 * obra, cuanto se ha cobrado y que queda pendiente.
 *
 * `avance_fisico` y `avance_financiero` van en dos bloques separados, con su
 * fuente declarada. Nunca se devuelve un "avance" único: el promedio de 60%
 * fisico y 45% cobrado no significa nada, y el desfasamiento entre ambos es
 * justamente lo que hay que mirar.
 */
class EstadoDeCuentaController extends Controller
{
    public function __construct(private readonly EstadoDeCuentaObra $estadoDeCuenta) {}

    public function show(Request $request, Obra $obra): Response
    {
        $this->authorize('view', $obra);

        $hoy = $request->query('fecha')
            ? CarbonImmutable::parse((string) $request->query('fecha'))
            : null;

        return response()->json(
            $this->estadoDeCuenta->paraObra($obra, $hoy)
        );
    }
}