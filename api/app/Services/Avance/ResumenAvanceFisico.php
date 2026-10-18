<?php

declare(strict_types=1);

namespace App\Services\Avance;

/**
 * Resultado del calculo del avance FISICO de una obra.
 *
 * Es una clase propia, y no un `float`, por una razon concreta: el avance
 * fisico y el financiero tienen que poder estar en la misma pantalla sin
 * confundirse. Al ser dos tipos distintos, mezclarlos es un error de PHP y no
 * un dato que salga mal en un informe.
 *
 * `porcentaje` sale de las etapas. No tiene nada que ver con el dinero.
 */
final readonly class ResumenAvanceFisico
{
    /**
     * @param  float|null  $porcentaje  Ponderado por peso de etapa. Null si la
     *                                   obra no tiene etapas con peso, porque
     *                                   un 0 en ese caso sería un dato falso.
     * @param  float  $sumaPesos  Suma de los pesos declarados. Si no da 100, la
     *                            obra tiene su plan mal cargado.
     * @param  int  $etapasTerminadas  Etapas con fecha real informada.
     * @param  int  $etapasAtrasadas  Etapas cuya fecha real (o la ausencia de
     *                               ella) ya paso la fecha planificada.
     * @param  int|null  $mayorAtrasoDias  Mayor atraso entre etapas.
     */
    public function __construct(
        public ?float $porcentaje,
        public float $sumaPesos,
        public int $etapasTotal,
        public int $etapasTerminadas,
        public int $etapasAtrasadas,
        public ?int $mayorAtrasoDias,
    ) {}

    /** @return array<string, int|float|null> */
    public function aArray(): array
    {
        return [
            'porcentaje' => $this->porcentaje,
            'fuente' => 'etapas',
            'unidad' => 'porcentaje_puntaje',
            'suma_pesos' => $this->sumaPesos,
            'etapas_total' => $this->etapasTotal,
            'etapas_terminadas' => $this->etapasTerminadas,
            'etapas_atrasadas' => $this->etapasAtrasadas,
            'mayor_atraso_dias' => $this->mayorAtrasoDias,
        ];
    }
}