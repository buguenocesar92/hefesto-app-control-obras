<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa constructora: el tenant.
 *
 * NO lleva RLS. Se lee antes del login, cuando no hay sesión y por lo tanto no
 * hay empresa en la conexión. La compensacion es que nadie lee esta tabla en
 * crudo: el login usa la vista `vw_tenants_publicos`, que expone slug, nombre
 * y activo. Ver docs/03, seccion 4.
 */
class Tenant extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'tenants';

    protected $fillable = [
        'razon_social',
        'rut',
        'slug',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    /** @return HasMany<Obra, $this> */
    public function obras(): HasMany
    {
        return $this->hasMany(Obra::class);
    }

    /**
     * Indica que no se leen los datos crudos de la tabla de empresas.
     *
     * Es una ayuda para que quede a la vista en el codigo que la vista
     * pública de entrada es la via admitida antes del login.
     */
    public static function soloParaListasDeEntrada(): string
    {
        return 'vw_tenants_publicos';
    }
}