<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoTarea;
use App\Enums\OrigenTarea;
use App\Models\Tarea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tarea de prueba.
 *
 * @extends Factory<Tarea>
 */
class TareaFactory extends Factory
{
    protected $model = Tarea::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'obra_id' => \App\Models\Obra::factory(),
            'origen' => OrigenTarea::Manual->value,
            'titulo' => $this->faker->sentence(4),
            'descripcion' => $this->faker->paragraph(),
            'responsable_id' => \App\Models\Usuario::factory(),
            'fecha_plazo' => $this->faker->dateTimeBetween('-5 days', '+10 days')->format('Y-m-d'),
            'estado' => EstadoTarea::Pendiente->value,
        ];
    }

    public function vencida(): static
    {
        return $this->state(fn (): array => [
            'fecha_plazo' => now()->subDays(3)->toDateString(),
            'estado' => EstadoTarea::Pendiente->value,
        ]);
    }

    public function completada(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoTarea::Completada->value,
            'fecha_cierre' => now(),
        ]);
    }
}