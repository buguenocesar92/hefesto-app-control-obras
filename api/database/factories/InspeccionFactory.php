<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoInspeccion;
use App\Models\Inspeccion;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Inspección de prueba.
 *
 * @extends Factory<Inspección>
 */
class InspeccionFactory extends Factory
{
    protected $model = Inspeccion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'tipo_inspeccion_id' => \Database\Factories\TipoInspeccionFactory::new(),
            'fecha_inspeccion' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'inspector_id' => \App\Models\Usuario::factory(),
            'resultado' => EstadoInspeccion::Conforme->value,
            'numero_acta' => 'ACTA-' . $this->faker->unique()->numberBetween(1, 9999),
            'observaciones' => null,
        ];
    }

    public function conforme(): static
    {
        return $this->state(fn (): array => ['resultado' => EstadoInspeccion::Conforme->value]);
    }

    public function conHallazgos(): static
    {
        return $this->state(fn (): array => ['resultado' => EstadoInspeccion::ConHallazgos->value]);
    }
}