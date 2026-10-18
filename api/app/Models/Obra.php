<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoObra;
use App\Models\Concerns\MarcaEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Obra: la unidad de negocio. Todo lo demas cuelga de aquí.
 *
 * La obra NO guarda su avance. Ni el fisico ni el financiero, y a propósito.
 * Los dos se CALCULAN, cada uno desde su propia fuente, y por eso no se
 * pueden mezclar ni promediar: se puede tener 60% de avance fisico y 45%
 * cobrado, y esa diferencia es información, no un error que corregir.
 * Ver `App\Services\Avance\EstadoDeCuentaObra`.
 */
class Obra extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $fillable = [
        'tenant_id',
        'codigo',
        'nombre',
        'cliente_nombre',
        'cliente_rut',
        'cliente_contacto',
        'direccion',
        'tipo',
        'fecha_inicio',
        'fecha_termino_estimada',
        'monto_contratado',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoObra::class,
            'fecha_inicio' => 'date',
            'fecha_termino_estimada' => 'date',
            'monto_contratado' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** @return HasMany<Etapa, $this> */
    public function etapas(): HasMany
    {
        return $this->hasMany(Etapa::class)->orderBy('orden');
    }

    /** @return HasMany<Tarea, $this> */
    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    /** @return HasMany<Inspección, $this> */
    public function inspecciones(): HasMany
    {
        return $this->hasMany(Inspeccion::class);
    }

    /** @return HasMany<RegistroSeguridad, $this> */
    public function registrosSeguridad(): HasMany
    {
        return $this->hasMany(RegistroSeguridad::class);
    }

    /** @return HasMany<AvancePago, $this> */
    public function avancesPago(): HasMany
    {
        return $this->hasMany(AvancePago::class)->orderBy('numero');
    }

    /** @return HasMany<BitacoraObra, $this> */
    public function bitacora(): HasMany
    {
        return $this->hasMany(BitacoraObra::class)->orderByDesc('fecha');
    }

    /**
     * Tareas sin terminar, ordenadas por la más vencida primero.
     *
     * Es la consulta que se muestra al abrir la obra: lo que hay que hacer
     * hoy.
     */
    public function tareasPendientes(): HasMany
    {
        return $this->tareas()
            ->whereIn('estado', ['pendiente', 'en_ejecucion'])
            ->orderByRaw('fecha_plazo IS NULL DESC')
            ->orderBy('fecha_plazo');
    }
}