<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoTarea;
use App\Enums\OrigenTarea;
use App\Models\Concerns\MarcaEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tarea con plazo y responsable.
 *
 * Es la pieza que hace que una decisión conversada no se pierda. "Hay que
 * revisar la fundación el jueves" deja de ser una frase y pasa a ser una fila
 * con `fecha_plazo` y `responsable_id`: mientras no este completada, aparece.
 *
 * Una tarea con `origen = correccion_inspeccion` no se creo a mano: nacio de
 * un hallazgo, y se puede volver al hallazgo y a la inspección que lo produjo.
 * Ese camino de vuelta es el que hace posible responder, meses después, la
 * pregunta de que paso con la observacion.
 */
class Tarea extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $fillable = [
        'tenant_id',
        'obra_id',
        'etapa_id',
        'origen',
        'origen_inspeccion_id',
        'origen_hallazgo_id',
        'titulo',
        'descripcion',
        'responsable_id',
        'fecha_plazo',
        'fecha_cierre',
        'estado',
        'creado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'origen' => OrigenTarea::class,
            'estado' => EstadoTarea::class,
            'fecha_plazo' => 'date',
            'fecha_cierre' => 'datetime',
        ];
    }

    /** @return BelongsTo<Obra, $this> */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /** @return BelongsTo<Etapa, $this> */
    public function etapa(): BelongsTo
    {
        return $this->belongsTo(Etapa::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_id');
    }

    /** @return BelongsTo<Inspección, $this> */
    public function inspeccionOrigen(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class, 'origen_inspeccion_id');
    }

    /** @return BelongsTo<Hallazgo, $this> */
    public function hallazgoOrigen(): BelongsTo
    {
        return $this->belongsTo(Hallazgo::class, 'origen_hallazgo_id');
    }

    /** @return HasOne<Hallazgo, $this> */
    public function hallazgo(): HasOne
    {
        return $this->hasOne(Hallazgo::class, 'origen_hallazgo_id');
    }

    /**
     * Vencida: tiene fecha de plazo, ya paso y no esta terminada.
     *
     * Sin fecha de plazo nunca esta vencida. Es la única categoria de tarea
     * que el tablero no puede usar para presionar al equipo: por eso el
     * generador de correcciones exige plazo siempre.
     */
    public function estaVencida(?CarbonImmutable $hoy = null): bool
    {
        if ($this->fecha_plazo === null) {
            return false;
        }

        if (in_array($this->estado, [EstadoTarea::Completada, EstadoTarea::Anulada], true)) {
            return false;
        }

        $referencia = $hoy ?? CarbonImmutable::now();

        return $referencia->startOfDay()->greaterThan($this->fecha_plazo->startOfDay());
    }

    /** Días que faltan para el plazo. Negativo cuando ya vencio. */
    public function diasParaVencer(?CarbonImmutable $hoy = null): ?int
    {
        if ($this->fecha_plazo === null) {
            return null;
        }

        $referencia = $hoy ?? CarbonImmutable::now();

        return $referencia->startOfDay()->diffInDays($this->fecha_plazo->startOfDay(), false);
    }

    public function nacioDeInspeccion(): bool
    {
        return $this->origen === OrigenTarea::CorreccionInspeccion;
    }
}