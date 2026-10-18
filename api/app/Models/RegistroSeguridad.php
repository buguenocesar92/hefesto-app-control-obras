<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClaseRegistroSeguridad;
use App\Models\Concerns\MarcaEmpresa;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Accidente o casi accidente.
 *
 * UN CASI ACCIDENTE NO ES UN ACCIDENTE. Es la misma tabla porque se analiza en
 * la misma reunion, pero con clase propia, y los informes los cuentan por
 * separado. Sumarlos sería la forma facil de perder el dato que permite
 * prevenir antes de que se repita.
 *
 * `anonizado` existe para la Ley 19.628: cuando el hecho ya se cerro y la
 * información ya se entrego a quien corresponde, el nombre y el RUT de la
 * persona se pueden borrar sin perder el registro del accidente. Lo que se
 * conserva es el hecho, la lesión descrita en texto y las horas perdidas.
 */
class RegistroSeguridad extends Model
{
    use HasFactory;
    use HasUuids;
    use MarcaEmpresa;

    protected $table = 'registros_seguridad';

    protected $fillable = [
        'tenant_id',
        'obra_id',
        'clase',
        'fecha_ocurrencia',
        'lugar',
        'descripcion',
        'lesionado',
        'horas_perdidas',
        'persona_afectada_nombre',
        'persona_afectada_rut',
        'anonizado',
        'medidas_correctivas',
        'reportado_por_id',
        'tarea_correccion_id',
    ];

    protected function casts(): array
    {
        return [
            'clase' => ClaseRegistroSeguridad::class,
            'fecha_ocurrencia' => 'datetime',
            'lesionado' => 'boolean',
            'horas_perdidas' => 'decimal:2',
            'anonizado' => 'boolean',
        ];
    }

    /** @return BelongsTo<Obra, $this> */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'reportado_por_id');
    }

    /** @return BelongsTo<Tarea, $this> */
    public function tareaCorreccion(): BelongsTo
    {
        return $this->belongsTo(Tarea::class, 'tarea_correccion_id');
    }

    public function esAccidente(): bool
    {
        return $this->clase === ClaseRegistroSeguridad::Accidente;
    }

    public function esCasiAccidente(): bool
    {
        return $this->clase === ClaseRegistroSeguridad::CasiAccidente;
    }

    /**
     * Un casi accidente sin medidas correctivas esta incompleto.
     *
     * Se muestra en la interfaz y en el informe, porque un casi accidente sin
     * medida es solo un susto anotado, y el susto anotado no previene nada.
     */
    public function faltaMedidasCorrectivas(): bool
    {
        return $this->medidas_correctivas === null || trim($this->medidas_correctivas) === '';
    }

    /**
     * Borrado del dato personal del hecho ya cerrado.
     *
     * No borra el registro: deja el accidente, la lesión en texto y las horas
     * perdidas, y quita nombre y RUT. Es la forma de cumplir el principio de
     * finalidad sin perder la evidencia.
     */
    public function anonimizar(): void
    {
        $this->forceFill([
            'persona_afectada_nombre' => null,
            'persona_afectada_rut' => null,
            'anonizado' => true,
        ])->save();
    }

    /** Datos personales presentes todavia. */
    public function tieneDatosPersonales(): bool
    {
        return ! $this->anonizado
            && ($this->persona_afectada_nombre !== null || $this->persona_afectada_rut !== null);
    }

    public function fechaOcurrencia(): ?CarbonInterface
    {
        return $this->fecha_ocurrencia;
    }
}