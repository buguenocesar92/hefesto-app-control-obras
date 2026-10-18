<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEtapa;
use App\Models\Concerns\MarcaEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Etapa de la obra.
 *
 * LA CIFRA QUE IMPORTA
 * --------------------
 * `desviacionDias()` es la diferencia entre `fecha_real` y
 * `fecha_planificada`. Es la única medida de atraso que se discute en una
 * reunion de obra, y se CALCULA en el momento en vez de guardarse.
 *
 * Por que se calcula y no se guarda: si el atraso fuera una columna, el día
 * que se reprograma el plan habría que recalcular todas las etapas, y
 * cualquier etapa que se olvidara de recalcular seguiria mostrando un atraso
 * que ya no existe, o dejaria de mostrar uno que si existe. Calculandolo
 * siempre desde las dos fechas, no hay nada que se pueda quedar desactualizado.
 *
 * Cuando la etapa no tiene fecha real todavia, la comparacion es contra la
 * fecha de hoy: es un atraso en curso, no un atraso de la nada.
 */
class Etapa extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $fillable = [
        'tenant_id',
        'obra_id',
        'orden',
        'nombre',
        'descripcion',
        'fecha_planificada',
        'fecha_real',
        'avance_real_pct',
        'peso',
        'estado',
        'responsable_id',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'estado' => EstadoEtapa::class,
            'fecha_planificada' => 'date',
            'fecha_real' => 'date',
            'avance_real_pct' => 'decimal:2',
            'peso' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Obra, $this> */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsable_id');
    }

    /** @return HasMany<Tarea, $this> */
    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    /**
     * Días de atraso. Positivo es atraso, negativo es adelanto, cero es en
     * fecha.
     *
     * Sin fecha planificada no hay con que comparar y se devuelve null: es
     * distinto de "esta en fecha" y la interfaz lo muestra distinto.
     *
     * Sin fecha real se compara contra hoy, porque la etapa todavia no
     * termina y su atraso es un atraso en curso.
     */
    public function desviacionDias(?CarbonImmutable $hoy = null): ?int
    {
        if ($this->fecha_planificada === null) {
            return null;
        }

        $referencia = $this->fecha_real ?? $hoy ?? CarbonImmutable::now();

        // startOfDay evita que la hora del reloj turnover una diferencia de
        // fechas en un día más o un día menos.
        return $referencia->startOfDay()->diffInDays(
            $this->fecha_planificada->startOfDay(),
            false
        );
    }

    /**
     * Etapa que todavia no tiene fecha real y ya paso su fecha planificada.
     */
    public function estaAtrasada(?CarbonImmutable $hoy = null): bool
    {
        if ($this->fecha_real !== null) {
            $desviacion = $this->desviacionDias();

            return $desviacion !== null && $desviacion > 0;
        }

        if ($this->fecha_planificada === null) {
            return false;
        }

        $referencia = $hoy ?? CarbonImmutable::now();

        return $referencia->startOfDay()->greaterThan($this->fecha_planificada->startOfDay());
    }

    /**
     * Aporte de esta etapa al avance fisico de la obra, en puntos
     * porcentuales.
     *
     * Una etapa que pesa 20 en la obra y esta al 50 aporta 10 puntos. Es el
     * único lugar donde se mezcla peso con avance, y vive aquí para que no se
     * pueda duplicar la formula en otro lado.
     */
    public function aporteAlAvanceFisico(): float
    {
        return ((float) $this->peso * (float) $this->avance_real_pct) / 100;
    }
}