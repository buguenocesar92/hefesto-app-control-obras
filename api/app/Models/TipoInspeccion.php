<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\MarcaEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de inspección: catalogo administrable de la empresa.
 *
 * Es una tabla y no un enum porque el tipo de inspección lo define la obra,
 * no el software.
 */
class TipoInspeccion extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $table = 'tipos_inspeccion';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Inspección, $this> */
    public function inspecciones(): HasMany
    {
        return $this->hasMany(Inspeccion::class);
    }
}