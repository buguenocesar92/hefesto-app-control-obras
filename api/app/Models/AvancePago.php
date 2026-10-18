<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAvancePago;
use App\Models\Concerns\MarcaEmpresa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avance de pago: una linea del estado de cuenta al cliente.
 *
 * AVANCE FINANCIERO NO ES AVANCE FISICO
 * -------------------------------------
 * Esta tabla es la fuente Única del avance financiero, y no toca las etapas.
 * Lo que el cliente ha pagado se calcula sumando `monto_cobrado` de las lineas
 * que están en estado `pagado`, dividido por `obras.monto_contratado`.
 *
 * `avance_fisico_pct` es una FOTO del avance fisico al momento de emitir. Se
 * congela aunque las etapas después cambien, porque es lo que permite auditar
 * después por que se emitio ese pago. No es el avance fisico de la obra, y por
 * eso tiene otro nombre y otra fuente.
 */
class AvancePago extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $fillable = [
        'tenant_id',
        'obra_id',
        'numero',
        'fecha_emision',
        'periodo_desde',
        'periodo_hasta',
        'avance_fisico_pct',
        'monto_bruto',
        'deducciones',
        'monto_neto',
        'monto_cobrado',
        'fecha_cobro',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha_emision' => 'date',
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'fecha_cobro' => 'date',
            'avance_fisico_pct' => 'decimal:2',
            'monto_bruto' => 'decimal:2',
            'deducciones' => 'decimal:2',
            'monto_neto' => 'decimal:2',
            'monto_cobrado' => 'decimal:2',
            'estado' => EstadoAvancePago::class,
        ];
    }

    /** @return BelongsTo<Obra, $this> */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * Parte de este pago que todavia no ha entrado.
     *
     * Un pago aprobado y no cobrado tiene saldo igual al neto completo: es
     * dinero que el cliente reconocio pero que la obra todavia no tiene.
     */
    public function saldo(): float
    {
        return (float) $this->monto_neto - (float) $this->monto_cobrado;
    }

    public function estaCobrado(): bool
    {
        return $this->estado->cuentaComoCobrado();
    }

    /**
     * Cobrado y con la clase de registro que corresponde.
     *
     * Se separa de `estaCobrado()` a propósito: el avance financiero solo
     * cuenta la plata que esta marcada como cobrada, nunca la que esta
     * aprobada.
     */
    public function montoParaAvanceFinanciero(): float
    {
        return $this->estaCobrado() ? (float) $this->monto_cobrado : 0.0;
    }
}