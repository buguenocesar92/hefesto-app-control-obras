<?php

declare(strict_types=1);

namespace App\Services\Avance;

use App\Models\Obra;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Estado de cuenta de una obra: fisico y financiero, POR SEPARADO.
 *
 * LA REGLA DEL PRODUCTO
 * --------------------
 * Se puede tener 60% de avance fisico y 45% cobrado. Las dos cifras van en
 * pantalla, con su fuente a la vista, y no se promedian en ninguna parte.
 *
 * Por que no se promedian: el promedio de 60 y 45 da 52,5%, y ese 52,5% no
 * significa ninguna de las dos cosas. Es una obra que se construye a un ritmo
 * y se cobra a otro, y ese desfasamiento es justo lo que hay que mirar en una
 * reunion de obra: si el fisico va muy por delante del financiero, la obra
 * esta trabajando con plata que todavia no llego, y eso se tiene que ver.
 *
 * `diferencia_puntos_porcentuales` es la resta, no el promedio, y se deja
 * explicita para que nadie tenga que suponerla.
 *
 * Las dos cifras vienen de clases distintas y no se llaman entre si:
 * `CalculadorAvanceFisico` lee etapas, `CalculadorAvanceFinanciero` lee
 * pagos. Ver sus comentarios para el por que.
 */
class EstadoDeCuentaObra
{
    public function __construct(
        private readonly CalculadorAvanceFisico $fisico,
        private readonly CalculadorAvanceFinanciero $financiero,
    ) {}

    /**
     * @return array{
     *     obra: array<string, mixed>,
     *     avance_fisico: array<string, int|float|null>,
     *     avance_financiero: array<string, int|float|null>,
     *     diferencia_puntos_porcentuales: float|null,
     *     pagos: list<array<string, mixed>>,
     *     pendientes: array<string, int>
     * }
     */
    public function paraObra(Obra $obra, ?CarbonImmutable $hoy = null): array
    {
        $fisico = $this->fisico->calcular($obra, $hoy);
        $financiero = $this->financiero->calcular($obra);

        return [
            'obra' => $this->datosObra($obra),
            'avance_fisico' => $fisico->aArray(),
            'avance_financiero' => $financiero->aArray(),
            'diferencia_puntos_porcentuales' => $this->diferencia($fisico, $financiero),
            'pagos' => $this->pagos($obra),
            'pendientes' => $this->pendientes($obra, $hoy),
        ];
    }

    /**
     * Fisico menos financiero, en puntos porcentuales.
     *
     * Positivo significa que la obra va por delante de lo cobrado: se ha
     * construido más de lo que se ha cobrado. Es una situacion legitima y
     * habitual, no una alerta de error.
     */
    private function diferencia(ResumenAvanceFisico $fisico, ResumenAvanceFinanciero $financiero): ?float
    {
        if ($fisico->porcentaje === null || $financiero->porcentaje === null) {
            return null;
        }

        return round($fisico->porcentaje - $financiero->porcentaje, 2);
    }

    /** @return array<string, mixed> */
    private function datosObra(Obra $obra): array
    {
        return [
            'id' => $obra->id,
            'codigo' => $obra->codigo,
            'nombre' => $obra->nombre,
            'cliente_nombre' => $obra->cliente_nombre,
            'direccion' => $obra->direccion,
            'estado' => $obra->estado->value,
            'fecha_inicio' => $obra->fecha_inicio?->toDateString(),
            'monto_contratado' => (float) $obra->monto_contratado,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function pagos(Obra $obra): array
    {
        return $obra->avancesPago()
            ->get()
            ->map(fn ($pago): array => [
                'id' => $pago->id,
                'numero' => $pago->numero,
                'fecha_emision' => $pago->fecha_emision?->toDateString(),
                'estado' => $pago->estado->value,
                'monto_neto' => (float) $pago->monto_neto,
                'monto_cobrado' => (float) $pago->monto_cobrado,
                'saldo' => $pago->saldo(),
                // FOTO del fisico al emitir, que NO es el fisico de hoy.
                'avance_fisico_al_emitir_pct' => $pago->avance_fisico_pct === null
                    ? null
                    : (float) $pago->avance_fisico_pct,
            ])
            ->all();
    }

    /**
     * Lo que esta sin hacer y sin corregir.
     *
     * Va junto al estado de cuenta porque las tres cosas se preguntan en la
     * misma reunion: cuanto va, cuanto se ha cobrado, y que quedó pendiente.
     *
     * @return array<string, int>
     */
    private function pendientes(Obra $obra, ?CarbonImmutable $hoy): array
    {
        $referencia = $hoy ?? CarbonImmutable::now();

        /** @var Collection<int, mixed> $vencidas */
        $vencidas = $obra->tareas()
            ->whereIn('estado', ['pendiente', 'en_ejecucion'])
            ->whereNotNull('fecha_plazo')
            ->whereDate('fecha_plazo', '<', $referencia->toDateString())
            ->get();

        return [
            'tareas_vencidas' => $vencidas->count(),
            'tareas_sin_vencer' => $obra->tareas()->whereIn('estado', ['pendiente', 'en_ejecucion'])->count(),
            'hallazgos_pendientes' => $obra->inspecciones()
                ->with('hallazgos')
                ->get()
                ->flatMap(fn ($inspeccion) => $inspeccion->hallazgos)
                ->filter(fn ($hallazgo) => $hallazgo->estaPendiente())
                ->count(),
        ];
    }
}