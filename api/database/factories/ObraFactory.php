<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoObra;
use App\Models\Obra;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Obra de prueba.
 *
 * @extends Factory<Obra>
 */
class ObraFactory extends Factory
{
    protected $model = Obra::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $inicio = $this->faker->dateTimeBetween('-6 months', '-1 month');

        return [
            'tenant_id' => Tenant::factory(),
            'codigo' => 'OB-' . $this->faker->unique()->numberBetween(1000, 9999),
            'nombre' => $this->faker->randomElement([
                'Edificio de oficinas',
                'Ampliacion de bodega',
                'Vivienda colectiva',
                'Local comercial',
            ]),
            'cliente_nombre' => $this->faker->company(),
            'cliente_rut' => (string) $this->faker->numberBetween(76000000, 76999999),
            'direccion' => $this->faker->streetAddress() . ', Santiago',
            'tipo' => $this->faker->randomElement(['Edificacion', 'Remodelacion', 'Infraestructura']),
            'fecha_inicio' => $inicio,
            'fecha_termino_estimada' => (clone $inicio)->modify('+9 months'),
            // Monto en UF, que es como se contratan las obras en Chile. La
            // unidad la fija el contrato; el sistema no la asume.
            'monto_contratado' => $this->faker->randomFloat(2, 1200, 5000),
            'estado' => EstadoObra::EnEjecucion->value,
        ];
    }
}