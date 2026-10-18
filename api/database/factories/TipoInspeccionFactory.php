<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TipoInspeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tipo de inspección de prueba.
 *
 * @extends Factory<TipoInspeccion>
 */
class TipoInspeccionFactory extends Factory
{
    protected $model = TipoInspeccion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Inspeccion de fundaciones',
                'Control de hormigon',
                'Revision de instalaciones',
                'Recepcion de obra gruesa',
                'Control de seguridad',
            ]),
            'descripcion' => null,
            'activo' => true,
        ];
    }
}