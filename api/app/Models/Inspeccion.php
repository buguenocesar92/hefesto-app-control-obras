<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoInspeccion;
use App\Models\Concerns\MarcaEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inspección de una obra.
 *
 * Una inspección CONFORME también se registra, y esa es la idea: la prueba de
 * que la fundación se reviso el jueves es que exista esta fila con su fecha y
 * su inspector. Guardar solo las que salieron mal sería no poder demostrar la
 * revisión.
 */
class Inspeccion extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $fillable = [
        'tenant_id',
        'obra_id',
        'tipo_inspeccion_id',
        'fecha_inspeccion',
        'inspector_id',
        'resultado',
        'numero_acta',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inspeccion' => 'date',
            'resultado' => EstadoInspeccion::class,
        ];
    }

    /** @return BelongsTo<Obra, $this> */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /** @return BelongsTo<TipoInspeccion, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoInspeccion::class, 'tipo_inspeccion_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'inspector_id');
    }

    /** @return HasMany<Hallazgo, $this> */
    public function hallazgos(): HasMany
    {
        return $this->hasMany(Hallazgo::class);
    }

    /** Hallazgos que todavia no están corregidos. */
    public function hallazgosPendientes(): HasMany
    {
        return $this->hallazgos()
            ->whereIn('estado', ['pendiente', 'en_proceso']);
    }

    public function tieneHallazgosPendientes(): bool
    {
        return $this->hallazgosPendientes()->exists();
    }
}