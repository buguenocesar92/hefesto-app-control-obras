<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoEtapa;
use App\Models\Etapa;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Etapa de prueba.
 *
 * `peso` es el tanto por ciento de la obra que representa la etapa. Las
 * pruebas que verifican el avance fisico dependen de que los pesos sumen 100.
 *
 * @extends Factory<Etapa>
 */
class EtapaFactory extends Factory
{
    protected $model = Etapa::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $planificada = $this->faker->dateTimeBetween('-5 months', '+2 months');

        return [
            'obra_id' => Obra::factory(),
            'orden' => $this->faker->unique()->numberBetween(1, 99),
            'nombre' => $this->faker->randomElement([
                'Excavacion y fundaciones',
                'Muro de contencion',
                'Estructura de hormigon',
                'Instalacion electrica',
                'Instalacion sanitaria',
                'Tabiquería interior',
                'Piso y terminaciones',
            ]),
            'fecha_planificada' => $planificada,
            'fecha_real' => null,
            'avance_real_pct' => 0,
            'peso' => 20,
            'estado' => EstadoEtapa::Pendiente->value,
        ];
    }

    /** Etapa terminada, con fecha real. */
    public function terminada(): static
    {
        return $this->state(function (): array {
            $real = $this->faker->dateTimeBetween('-4 months', 'now');

            return [
                'fecha_real' => $real,
                'avance_real_pct' => 100,
                'estado' => EstadoEtapa::Terminada->value,
            ];
        });
    }

    /** Etapa a medio avance. */
    public function enCurso(int $avance = 50): static
    {
        return $this->state(fn (): array => [
            'avance_real_pct' => $avance,
            'estado' => EstadoEtapa::EnEjecucion->value,
        ]);
    }
}