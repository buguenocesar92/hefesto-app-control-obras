<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AvancePago;
use App\Models\BitacoraObra;
use App\Models\Etapa;
use App\Models\Inspeccion;
use App\Models\Obra;
use App\Models\RegistroSeguridad;
use App\Models\Tarea;
use App\Policies\AvancePagoPolicy;
use App\Policies\BitacoraObraPolicy;
use App\Policies\EtapaPolicy;
use App\Policies\InspeccionPolicy;
use App\Policies\ObraPolicy;
use App\Policies\RegistroSeguridadPolicy;
use App\Policies\TareaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registro explicito de las Policies.
 *
 * ESTA LISTA ES DELIBERADA
 * ------------------------
 * En este esqueleto las Policies NO se descubren solas. Eso obliga a
 * declararlas una por una, y esa obligacion es una ventaja: agregar un modelo
 * nuevo obliga a pensar ahí mismo en quien puede verlo, en vez de que quede
 * desprotegido porque nadie se acordo del archivo.
 *
 * Si alguna vez se cambia a descubrimiento automático, esta lista se vuelve el
 * único lugar donde el sistema se contradiria a si mismo. Por eso el comentario
 * de arriba y el de abajo dicen lo mismo.
 */
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Obra::class, ObraPolicy::class);
        Gate::policy(Etapa::class, EtapaPolicy::class);
        Gate::policy(Tarea::class, TareaPolicy::class);
        Gate::policy(Inspeccion::class, InspeccionPolicy::class);
        Gate::policy(RegistroSeguridad::class, RegistroSeguridadPolicy::class);
        Gate::policy(AvancePago::class, AvancePagoPolicy::class);
        Gate::policy(BitacoraObra::class, BitacoraObraPolicy::class);
    }
}