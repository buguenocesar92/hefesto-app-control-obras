<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoHallazgo;
use App\Enums\EstadoTarea;
use App\Enums\GravedadHallazgo;
use App\Models\Concerns\MarcaEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hallazgo de una inspección.
 *
 * CADA HALLAZGO GENERA UNA TAREA DE CORRECCIÓN CON PLAZO. Ese vínculo es el
 * motivo de que la tabla exista separada de la inspección: el hallazgo es el
 * problema, la tarea es el compromiso de resolverlo, y son dos cosas que se
 * pueden quedar en estados distintos.
 *
 * El indice único de `tareas (tenant_id, origen_hallazgo_id)` y el de
 * `hallazgos.tarea_correccion_id` garantizan que el vinculo no se duplique:
 * un hallazgo no genera dos tareas, y una tarea no corrige dos hallazgos.
 */
class Hallazgo extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $fillable = [
        'tenant_id',
        'inspeccion_id',
        'descripcion',
        'gravedad',
        'estado',
        'tarea_correccion_id',
    ];

    protected function casts(): array
    {
        return [
            'gravedad' => GravedadHallazgo::class,
            'estado' => EstadoHallazgo::class,
        ];
    }

    /** @return BelongsTo<Inspección, $this> */
    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class);
    }

    /** @return BelongsTo<Tarea, $this> */
    public function tareaCorreccion(): BelongsTo
    {
        return $this->belongsTo(Tarea::class, 'tarea_correccion_id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoHallazgo::Pendiente || $this->estado === EstadoHallazgo::EnProceso;
    }

    /**
     * Un hallazgo se cierra cuando su tarea de corrección queda completada.
     *
     * El metodo existe para dejar la regla escrita en un solo lugar. Si el
     * hallazgo se pudiera cerrar sin cambiar la tarea, quedaria una corrección
     * "hecha" que nadie ejecuto, que es el modo de falla que el modulo
     * busca evitar.
     */
    public function seCierraPorTareaCompletada(): bool
    {
        return $this->tareaCorreccion !== null
            && $this->tareaCorreccion->estado === EstadoTarea::Completada;
    }
}