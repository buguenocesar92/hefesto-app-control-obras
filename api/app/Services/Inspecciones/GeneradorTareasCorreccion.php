<?php

declare(strict_types=1);

namespace App\Services\Inspecciones;

use App\Enums\EstadoHallazgo;
use App\Enums\EstadoTarea;
use App\Enums\OrigenTarea;
use App\Models\Hallazgo;
use App\Models\Inspeccion;
use App\Models\Tarea;
use App\Services\Tenancy\ContextoTenant;
use Carbon\CarbonImmutable;

/**
 * Genera la tarea de corrección de un hallazgo.
 *
 * ESTE ES EL VÍNCULO QUE HACE QUE UNA INSPECCIÓN SIRVA DE ALGO
 * --------------------------------------------------------
 * Una inspección sin más que un resultado en papel es un documento que nadie
 * vuelve a abrir. El hallazgo es el problema, y el problema no se resuelve por
 * que este anotado: se resuelve porque hay alguien con una fecha.
 *
 * Por eso generar el hallazgo y NO generar la tarea están en la misma
 * operación de base de datos, y por eso la tarea nace SIEMPRE con `fecha_plazo`
 * y `responsable_id`. Un hallazgo con plazo corto que se puede cumplir es un
 * hallazgo real; un hallazgo sin plazo es una observacion decorativa.
 *
 * IDEMPOTENCIA
 * ------------
 * Si el hallazgo ya tiene tarea, se devuelve la misma en vez de crear otra.
 * Aparte de ser lo correcto para quien pulse dos veces, el indice único
 * `tareas (tenant_id, origen_hallazgo_id)` lo garantiza en la base: el
 * servicio puede fallar por una carrera y la base no lo deja pasar.
 */
class GeneradorTareasCorreccion
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * Crea (o devuelve) la tarea de corrección del hallazgo.
     *
     * @param  string  $responsableId  Usuario que se hace cargo. Obligatorio:
     *                                 una tarea sin responsable es el problema
     *                                 que este modulo viene a resolver.
     * @param  int|null  $diasPlazo  Días habiles de plazo. Si es null se usa
     *                               el que sugiere la gravedad del hallazgo.
     * @throws \InvalidArgumentException si el hallazgo no tiene inspección o
     *                                   si el responsable no es un id uuid.
     */
    public function paraHallazgo(
        Hallazgo $hallazgo,
        string $responsableId,
        ?int $diasPlazo = null
    ): Tarea {
        if ($hallazgo->inspeccion_id === null) {
            throw new \InvalidArgumentException(
                'Un hallazgo sin inspeccion no puede generar tarea de correccion.'
            );
        }

        // Toda la operación corre aislada por empresa. El tenant viaja con el
        // hallazgo, así que el servicio funciona igual desde la API, desde el
        // worker de colas y desde la semilla.
        return $this->contexto->ejecutarAislado(
            (string) $hallazgo->tenant_id,
            function () use ($hallazgo, $responsableId, $diasPlazo): Tarea {
                $existente = $this->tareaExistente($hallazgo);

                if ($existente !== null) {
                    return $existente;
                }

                $dias = $diasPlazo ?? $hallazgo->gravedad->diasPlazoSugeridos();

                if ($dias < 1) {
                    throw new \InvalidArgumentException(
                        'El plazo de una correccion debe ser de al menos un dia habil. '
                        . 'Si la gravedad no corresponde, ajuste el hallazgo.'
                    );
                }

                $inspeccion = Inspeccion::query()->findOrFail($hallazgo->inspeccion_id);

                $plazo = $this->fechaPlazo(
                    CarbonImmutable::instance($hallazgo->created_at ?? CarbonImmutable::now()),
                    $dias
                );

                $tarea = Tarea::query()->create([
                    'tenant_id' => $hallazgo->tenant_id,
                    'obra_id' => $inspeccion->obra_id,
                    'origen' => OrigenTarea::CorreccionInspeccion,
                    'origen_inspeccion_id' => $inspeccion->id,
                    'origen_hallazgo_id' => $hallazgo->id,
                    'titulo' => $this->titulo($hallazgo),
                    'descripcion' => $this->descripcion($hallazgo, $inspeccion),
                    'responsable_id' => $responsableId,
                    'fecha_plazo' => $plazo->toDateString(),
                    'estado' => EstadoTarea::Pendiente,
                    'creado_por_id' => $inspeccion->inspector_id,
                ]);

                // Los dos lados del vinculo se escriben juntos. Si se
                // escribieran por separado, un corte de luz podría dejar un
                // hallazgo con tarea a la que no apunta y nadie se entera.
                $hallazgo->forceFill([
                    'tarea_correccion_id' => $tarea->id,
                    'estado' => EstadoHallazgo::EnProceso,
                ])->save();

                return $tarea;
            }
        );
    }

    /**
     * Fecha de vencimiento del plazo, contando solo días habiles.
     *
     * Un hallazgo del viernes con plazo de 3 días no vence el lunes a la
     * mañana: vence el miércoles. Es la diferencia entre un plazo que la obra
     * respeta y uno que todos los incumplen sin querer.
     */
    public function fechaPlazo(CarbonImmutable $desde, int $diasHabiles): CarbonImmutable
    {
        $fecha = $desde->startOfDay();
        $contados = 0;

        while ($contados < $diasHabiles) {
            $fecha = $fecha->addDay();

            // Sabado y domingo no son días habiles. El sistema no sabe los
            // feriados del Chile: si la obra tiene días festivos, se ajusta
            // el plazo al cargar el hallazgo.
            if ($fecha->isWeekend()) {
                continue;
            }

            $contados++;
        }

        return $fecha;
    }

    private function tareaExistente(Hallazgo $hallazgo): ?Tarea
    {
        $porHallazgo = Tarea::query()
            ->where('origen_hallazgo_id', $hallazgo->id)
            ->first();

        if ($porHallazgo !== null) {
            return $porHallazgo;
        }

        // Respaldo por el lado inverso del vinculo, por si el hallazgo se
        // creo en una versión anterior que solo dejó uno de los dos lados.
        if ($hallazgo->tarea_correccion_id !== null) {
            return Tarea::query()->find($hallazgo->tarea_correccion_id);
        }

        return null;
    }

    private function titulo(Hallazgo $hallazgo): string
    {
        $descripcion = trim(preg_replace('/\s+/', ' ', $hallazgo->descripcion) ?? '');

        $corta = mb_substr($descripcion, 0, 140);

        return 'Corregir hallazgo (' . $hallazgo->gravedad->value . '): ' . $corta;
    }

    private function descripcion(Hallazgo $hallazgo, Inspeccion $inspeccion): string
    {
        $lineas = [
            'Hallazgo: ' . $hallazgo->descripcion,
            'Gravedad: ' . $hallazgo->gravedad->value,
            'Plazo sugerido por gravedad: ' . $hallazgo->gravedad->diasPlazoSugeridos() . ' dias habiles.',
            'Origen: inspeccion del ' . $inspeccion->fecha_inspeccion?->format('Y-m-d'),
        ];

        if ($inspeccion->numero_acta !== null) {
            $lineas[] = 'Acta: ' . $inspeccion->numero_acta;
        }

        return implode(PHP_EOL, $lineas);
    }
}