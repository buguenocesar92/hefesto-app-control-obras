<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolUsuario;
use App\Models\Concerns\MarcaEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persona de la empresa: maestro, inspector, jefe de obra, administrador o
 * cliente.
 *
 * Guarda datos personales (nombre, correo, RUT, teléfono) con la intencion de
 * finalidad de la Ley 19.628: se piden porque la obra los usa, no por
 * costumbre. Ver docs/07.
 */
class Usuario extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $fillable = [
        'tenant_id',
        'nombre',
        'correo',
        'rut',
        'telefono',
        'cargo',
        'rol',
        'activo',
        'ultimo_acceso_en',
    ];

    protected $hidden = [
        'rut',
        'telefono',
    ];

    protected function casts(): array
    {
        return [
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return HasMany<Tarea, $this> */
    public function tareasAsignadas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'responsable_id');
    }

    public function esAdministrador(): bool
    {
        return $this->rol === RolUsuario::Administrador;
    }

    /**
     * El cliente no edita la obra: la consulta.
     */
    public function puedeEditarObra(): bool
    {
        return match ($this->rol) {
            RolUsuario::Administrador, RolUsuario::JefeObra, RolUsuario::Maestro => true,
            RolUsuario::Inspector, RolUsuario::Cliente => false,
        };
    }
}