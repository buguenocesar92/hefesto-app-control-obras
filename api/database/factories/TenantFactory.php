<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Empresa constructora de prueba.
 *
 * Los RUT son del rango 76.000.000, que es el que se usa para datos de
 * prueba justamente para que no se confunda con el RUT de una persona real.
 *
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->company() . ' Constructora';

        return [
            'razon_social' => $nombre,
            'rut' => (string) $this->faker->unique()->numberBetween(76000000, 76999999),
            'slug' => Str::slug($nombre) . '-' . $this->faker->unique()->numberBetween(1, 99999),
            'activo' => true,
        ];
    }
}