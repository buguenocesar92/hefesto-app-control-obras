<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\Tenant;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Persona de la empresa de prueba.
 *
 * IMPORTANTE PARA LAS PRUEBAS DE AISLAMIENTO
 * ------------------------------------------
 * La empresa NO se fija en la fabrica: la fija el contexto de la sesión, que
 * es lo que la RLS exige. Hardcodearla aca haría que las pruebas de aislamiento
 * probaran la fabrica y no el aislamiento.
 *
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => $this->faker->name(),
            'correo' => $this->faker->unique()->safeEmail(),
            // Datos personales minimos: en pruebas no se cargan ni RUT ni
            // teléfono, para que nadie los confunda con datos de una persona.
            'cargo' => $this->faker->randomElement(['Maestro de obra', 'Inspector', 'Jefe de obra']),
            'rol' => $this->faker->randomElement(RolUsuario::valores()),
            'activo' => true,
        ];
    }

    public function maestro(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Maestro->value]);
    }

    public function inspector(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Inspector->value]);
    }

    public function jefeDeObra(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::JefeObra->value]);
    }

    public function cliente(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Cliente->value]);
    }
}