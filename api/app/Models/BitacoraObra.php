<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClaseAsuntoBitacora;
use App\Models\Concerns\MarcaEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitacora de obra: el registro de las decisiones.
 *
 * Es el modulo más barato de escribir y el que define al producto. Una obra se
 * controla mal casi siempre porque las decisiones se conversan y no quedan
 * escritas. Aquí cada acuerdo queda con `fecha` y `autor_id`, y eso es lo que
 * lo convierte en evidencia y no en un chiste de obra.
 *
 * Un acuerdo sin autor es una opinion, y tres meses después no sirve para
 * nada: por eso `autor_id` no es opcional.
 */
class BitacoraObra extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $table = 'bitacora_obra';

    protected $fillable = [
        'tenant_id',
        'obra_id',
        'fecha',
        'clase',
        'asunto',
        'cuerpo',
        'autor_id',
        'tarea_relacionada_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'clase' => ClaseAsuntoBitacora::class,
        ];
    }

    /** @return BelongsTo<Obra, $this> */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'autor_id');
    }

    /** @return BelongsTo<Tarea, $this> */
    public function tareaRelacionada(): BelongsTo
    {
        return $this->belongsTo(Tarea::class, 'tarea_relacionada_id');
    }
}