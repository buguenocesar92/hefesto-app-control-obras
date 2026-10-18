<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ClaseAsuntoBitacora;
use App\Enums\ClaseRegistroSeguridad;
use App\Enums\EstadoAvancePago;
use App\Enums\EstadoEtapa;
use App\Enums\EstadoHallazgo;
use App\Enums\EstadoInspeccion;
use App\Enums\EstadoObra;
use App\Enums\EstadoTarea;
use App\Enums\GravedadHallazgo;
use App\Enums\OrigenTarea;
use App\Enums\RolUsuario;
use App\Models\AvancePago;
use App\Models\BitacoraObra;
use App\Models\Etapa;
use App\Models\Hallazgo;
use App\Models\Inspeccion;
use App\Models\Obra;
use App\Models\RegistroSeguridad;
use App\Models\Tarea;
use App\Models\Tenant;
use App\Models\TipoInspeccion;
use App\Models\Usuario;
use App\Services\Inspecciones\GeneradorTareasCorreccion;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Seeder;

/**
 * Dos empresas y dos obras de ejemplo, con datos que dejan ver el modulo sin
 * tener que inventar nada.
 *
 * POR QUE LA SEMILLA USA EL CONTEXTO DE EMPRESA
 * ---------------------------------------------
 * Casi todas las tablas tienen RLS, y `FORCE ROW LEVEL SECURITY` hace que la
 * política se aplique también al dueno. Como la semilla corre con el mismo rol
 * que la aplicación, sin fijar `app.tenant_id` NO SE Podría ESCRIBIR NI UNA
 * FILA. Por eso cada empresa se siembra dentro de `ejecutarAislado()`, que
 * envuelve la escritura en una transacción con la empresa ya fijada.
 *
 * `tenants` NO tiene RLS, así que esa parte se escribe directamente.
 *
 * QUE CONTIENE
 * ------------
 *  - Dos empresas, para que el aislamiento se vea desde el primer arranque.
 *  - Obra 1: etapas con fecha planificada y real, y UNA atrasada a propósito.
 *  - Obra 1: una inspección CONFORME y una con hallazgos PENDIENTES, cada
 *    hallazgo con su tarea de corrección y su fecha de plazo.
 *  - Obra 1: un accidente con lesión y un casi accidente. No se suman.
 *  - Obra 1: pagos que dejan el avance FINANCIERO en 45%, mientras el avance
 *    FISICO de la misma obra es 73%.
 *  - Obra 2: recien planificada, sin etapas, para ver el estado vacio.
 *
 * Los nombres, RUT y correos son de ejemplo. No hay ninguna persona real aquí.
 */
class ObraDemoSeeder extends Seeder
{
    public function run(): void
    {
        $contexto = new ContextoTenant();

        // `tenants` no tiene RLS: se escribe sin contexto.
        $constructoraUno = Tenant::query()->create([
            'razon_social' => 'Constructora Los Robles Ltda.',
            'rut' => '76123456',
            'slug' => 'los-robles',
            'activo' => true,
        ]);

        $constructoraDos = Tenant::query()->create([
            'razon_social' => 'Constructora Cordillera SpA',
            'rut' => '76876543',
            'slug' => 'cordillera',
            'activo' => true,
        ]);

        $contexto->ejecutarAislado(
            (string) $constructoraUno->id,
            fn () => $this->sembrarLosRobles((string) $constructoraUno->id)
        );

        $contexto->ejecutarAislado(
            (string) $constructoraDos->id,
            fn () => $this->sembrarCordillera((string) $constructoraDos->id)
        );
    }

    private function sembrarLosRobles(string $empresaId): void
    {
        $jefe = Usuario::query()->create([
            'tenant_id' => $empresaId,
            'nombre' => 'Patricia Nunez Rojas',
            'correo' => 'p.nunez@los-robles.cl',
            'cargo' => 'Jefe de obra',
            'rol' => RolUsuario::JefeObra->value,
            'activo' => true,
        ]);

        $maestro = Usuario::query()->create([
            'tenant_id' => $empresaId,
            'nombre' => 'Carlos Mendez Fuentes',
            'correo' => 'c.mendez@los-robles.cl',
            'cargo' => 'Maestro de obra',
            'rol' => RolUsuario::Maestro->value,
            'activo' => true,
        ]);

        $inspector = Usuario::query()->create([
            'tenant_id' => $empresaId,
            'nombre' => 'Ana Fuentes Lira',
            'correo' => 'a.fuentes@los-robles.cl',
            'cargo' => 'Inspector de obra',
            'rol' => RolUsuario::Inspector->value,
            'activo' => true,
        ]);

        // Catalogo de inspecciones: pertenece a la empresa, no a la obra.
        $tipoFundaciones = TipoInspeccion::query()->create([
            'tenant_id' => $empresaId,
            'nombre' => 'Inspeccion de fundaciones',
            'descripcion' => 'Revision de fecha, nivel y armadura antes de iniciar la cadena.',
            'activo' => true,
        ]);

        $tipoHormigon = TipoInspeccion::query()->create([
            'tenant_id' => $empresaId,
            'nombre' => 'Control de hormigon',
            'descripcion' => 'Control de armadura, resistencia y curado en cada tanda.',
            'activo' => true,
        ]);

        // ------------------------------------------------------------------
        // OBRA 1: la que tiene historia.
        // ------------------------------------------------------------------
        $obraUno = Obra::query()->create([
            'tenant_id' => $empresaId,
            'codigo' => 'OB-2026-14',
            'nombre' => 'Ampliacion bodega Santa Rosa',
            'cliente_nombre' => 'Agricola Santa Rosa Ltda.',
            'cliente_rut' => '77444455',
            'cliente_contacto' => 'contacto@santarosa.cl',
            'direccion' => 'Ruta 5 Sur, kilometro 42, Colchane',
            'tipo' => 'Ampliacion',
            'fecha_inicio' => '2026-06-01',
            'fecha_termino_estimada' => '2026-12-20',
            'monto_contratado' => 2400.00,
            'estado' => EstadoObra::EnEjecucion->value,
        ]);

        /*
         * ETAPAS. Los pesos suman 100 y el avance fisico ponderado da 73:
         *
         *   peso 30 al 100%  = 30 puntos
         *   peso 35 al 100%  = 35 puntos
         *   peso 20 al  40%  =  8 puntos
         *   peso 15 al   0%  =  0 puntos
         *                       -------
         *                        73%
         *
         * La etapa 4 esta atrasada a propósito: paso su fecha planificada y
         * no tiene fecha real. Es la que muestra el modulo en pantalla.
         */
        Etapa::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'orden' => 1,
            'nombre' => 'Excavacion y fundaciones',
            'fecha_planificada' => '2026-06-20',
            'fecha_real' => '2026-06-24',
            'avance_real_pct' => 100,
            'peso' => 30,
            'estado' => EstadoEtapa::Terminada->value,
            'responsable_id' => $maestro->id,
        ]);

        Etapa::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'orden' => 2,
            'nombre' => 'Estructura de hormigon armado',
            'fecha_planificada' => '2026-08-15',
            'fecha_real' => '2026-08-20',
            'avance_real_pct' => 100,
            'peso' => 35,
            'estado' => EstadoEtapa::Terminada->value,
            'responsable_id' => $maestro->id,
        ]);

        Etapa::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'orden' => 3,
            'nombre' => 'Muro de contencion y cubierta',
            'fecha_planificada' => '2026-10-15',
            'fecha_real' => null,
            'avance_real_pct' => 40,
            'peso' => 20,
            'estado' => EstadoEtapa::EnEjecucion->value,
            'responsable_id' => $maestro->id,
        ]);

        Etapa::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'orden' => 4,
            'nombre' => 'Instalacion electrica interior',
            'fecha_planificada' => '2026-10-05',
            'fecha_real' => null,
            'avance_real_pct' => 0,
            'peso' => 15,
            'estado' => EstadoEtapa::Pendiente->value,
            'responsable_id' => $maestro->id,
        ]);

        // ------------------------------------------------------------------
        // Inspecciones.
        // ------------------------------------------------------------------

        // Una inspección CONFORME también se registra: es la prueba de que la
        // revisión quedó hecha, con fecha y con inspector.
        Inspeccion::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'tipo_inspeccion_id' => $tipoFundaciones->id,
            'fecha_inspeccion' => '2026-06-18',
            'inspector_id' => $inspector->id,
            'resultado' => EstadoInspeccion::Conforme->value,
            'numero_acta' => 'ACTA-001',
            'observaciones' => 'Fundaciones conformes. Sin observaciones.',
        ]);

        $inspeccionConHallazgos = Inspeccion::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'tipo_inspeccion_id' => $tipoHormigon->id,
            'fecha_inspeccion' => '2026-08-12',
            'inspector_id' => $inspector->id,
            'resultado' => EstadoInspeccion::ConHallazgos->value,
            'numero_acta' => 'ACTA-004',
            'observaciones' => 'Se detectan dos observaciones en la tanda de columnas.',
        ]);

        $hallazgoLeve = Hallazgo::query()->create([
            'tenant_id' => $empresaId,
            'inspeccion_id' => $inspeccionConHallazgos->id,
            'descripcion' => 'Falta compactacion en el trasdoso del muro sur, en un tramo de 4 metros.',
            'gravedad' => GravedadHallazgo::Baja->value,
            'estado' => EstadoHallazgo::Pendiente->value,
        ]);

        $hallazgoGrave = Hallazgo::query()->create([
            'tenant_id' => $empresaId,
            'inspeccion_id' => $inspeccionConHallazgos->id,
            'descripcion' => 'Armadura de fundacion expuesta, sin proteccion contra la humedad.',
            'gravedad' => GravedadHallazgo::Alta->value,
            'estado' => EstadoHallazgo::Pendiente->value,
        ]);

        // ESTE es el vinculo que sostiene el modulo: cada hallazgo nace con
        // su tarea de corrección y con fecha de plazo.
        $generador = new GeneradorTareasCorreccion(new ContextoTenant());

        $generador->paraHallazgo($hallazgoLeve, (string) $maestro->id);
        $generador->paraHallazgo($hallazgoGrave, (string) $maestro->id);

        // ------------------------------------------------------------------
        // UNA TAREA MANUAL, de reunion de obra.
        // El caso que el producto viene a resolver: quedó escrito, con fecha y
        // responsable, en vez de quedar conversado.
        // ------------------------------------------------------------------
        Tarea::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'origen' => OrigenTarea::Manual->value,
            'titulo' => 'Confirmar fecha de entrega de acero para cubierta',
            'descripcion' => 'Se acordo en reunion: el proveedor entrega el dia 20. Confirmar antes del dia 18.',
            'responsable_id' => $jefe->id,
            'fecha_plazo' => now()->addDays(3)->toDateString(),
            'estado' => EstadoTarea::Pendiente->value,
            'creado_por_id' => $jefe->id,
        ]);

        // ------------------------------------------------------------------
        // BITACORA: las decisiones, escritas.
        // ------------------------------------------------------------------
        BitacoraObra::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'fecha' => now()->subDays(12)->setTime(9, 30),
            'clase' => ClaseAsuntoBitacora::Decision->value,
            'asunto' => 'Revision de fundacion antes de iniciar la cadena',
            'cuerpo' => 'Se acordo revisar la fundacion antes de la cadena. El inspector deja acta ese dia.',
            'autor_id' => $jefe->id,
        ]);

        BitacoraObra::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'fecha' => now()->subDays(4)->setTime(11, 0),
            'clase' => ClaseAsuntoBitacora::Acuerdo->value,
            'asunto' => 'Reprogramacion de la instalacion electrica',
            'cuerpo' => 'La instalacion electrica se corre dos semanas por atraso en la entrega de tabiqueria.',
            'autor_id' => $jefe->id,
        ]);

        // ------------------------------------------------------------------
        // SEGURIDAD: un accidente y un casi accidente, POR SEPARADO.
        // ------------------------------------------------------------------

        // Accidente: con lesión y horas perdidas, como corresponde.
        RegistroSeguridad::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'clase' => ClaseRegistroSeguridad::Accidente->value,
            'fecha_ocurrencia' => now()->subDays(20)->setTime(16, 20),
            'lugar' => 'Sector de fundaciones',
            'descripcion' => 'Caida de 1,8 metros al retirar un puntal. Lesion en el antebrazo. '
                . 'No hubo perdida de conciencia ni necesidad de hospitalizacion.',
            'lesionado' => true,
            'horas_perdidas' => 24,
            // Viene anonimizado desde el origen: la semilla no carga datos
            // personales de nadie.
            'persona_afectada_nombre' => null,
            'persona_afectada_rut' => null,
            'anonizado' => true,
            'medidas_correctivas' => 'Se instalan lineas de vida para trabajos en altura y se capacita al equipo.',
            'reportado_por_id' => $jefe->id,
        ]);

        // Casi accidente: mismo sector y mismo riesgo, sin lesión. ES OTRO
        // REGISTRO, y por eso se cuenta aparte.
        RegistroSeguridad::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'clase' => ClaseRegistroSeguridad::CasiAccidente->value,
            'fecha_ocurrencia' => now()->subDays(9)->setTime(15, 5),
            'lugar' => 'Sector de fundaciones',
            'descripcion' => 'Un puntal cayo al soltarlo, porque no estaba trabado. No alcanzo a nadie.',
            'lesionado' => false,
            'horas_perdidas' => 0,
            'anonizado' => true,
            'medidas_correctivas' => 'Se exige trabar los puntales antes de cada tanda, con verificacion del maestro.',
            'reportado_por_id' => $inspector->id,
        ]);

        /*
         * PAGOS: el avance financiero, que NO es el fisico.
         *
         * Monto contratado: 2400
         *   Pago 1 pagado:   540 de 540
         *   Pago 2 aprobado: 540 de 540  (aprobado NO es cobrado)
         *   Pago 3 pagado:   540 de 540
         *
         * Cobrado: 1080 de 2400 = 45%.
         *
         * El avance fisico de esta obra es 73%. Queda 73% fisico contra 45%
         * cobrado: la obra va por delante de la plata. Esa diferencia es
         * información, y por eso los dos avances se reportan por separado.
         */
        AvancePago::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'numero' => 1,
            'fecha_emision' => '2026-07-05',
            'periodo_desde' => '2026-06-01',
            'periodo_hasta' => '2026-06-30',
            // FOTO del avance fisico al emitir, que es distinta cosa del
            // avance fisico de la obra hoy.
            'avance_fisico_pct' => 30.00,
            'monto_bruto' => 580.65,
            'deducciones' => 40.65,
            'monto_neto' => 540.00,
            'monto_cobrado' => 540.00,
            'fecha_cobro' => '2026-07-20',
            'estado' => EstadoAvancePago::Pagado->value,
            'observaciones' => 'Corresponde a excavacion y fundaciones.',
        ]);

        AvancePago::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'numero' => 2,
            'fecha_emision' => '2026-08-20',
            'periodo_desde' => '2026-07-01',
            'periodo_hasta' => '2026-07-31',
            'avance_fisico_pct' => 55.00,
            'monto_bruto' => 580.65,
            'deducciones' => 40.65,
            'monto_neto' => 540.00,
            // Aprobado y NO cobrado: es plata que la obra todavia no tiene.
            'monto_cobrado' => 0,
            'fecha_cobro' => null,
            'estado' => EstadoAvancePago::Aprobado->value,
            'observaciones' => 'Aprobado por el mandante. Pendiente de pago.',
        ]);

        AvancePago::query()->create([
            'tenant_id' => $empresaId,
            'obra_id' => $obraUno->id,
            'numero' => 3,
            'fecha_emision' => '2026-09-25',
            'periodo_desde' => '2026-08-01',
            'periodo_hasta' => '2026-08-31',
            'avance_fisico_pct' => 70.00,
            'monto_bruto' => 580.65,
            'deducciones' => 40.65,
            'monto_neto' => 540.00,
            'monto_cobrado' => 540.00,
            'fecha_cobro' => '2026-10-08',
            'estado' => EstadoAvancePago::Pagado->value,
            'observaciones' => 'Corresponde a estructura de hormigon.',
        ]);
    }

    private function sembrarCordillera(string $empresaId): void
    {
        Usuario::query()->create([
            'tenant_id' => $empresaId,
            'nombre' => 'Jorge Salinas Rojas',
            'correo' => 'j.salinas@cordillera.cl',
            'cargo' => 'Jefe de obra',
            'rol' => RolUsuario::JefeObra->value,
            'activo' => true,
        ]);

        // El catalogo es por empresa: esta constructora puede tener tipos
        // distintos de los de la otra, y así debe ser.
        TipoInspeccion::query()->create([
            'tenant_id' => $empresaId,
            'nombre' => 'Inspeccion de fundaciones',
            'descripcion' => 'Mismo nombre que en la otra empresa, con su propia ficha.',
            'activo' => true,
        ]);

        // Obra recien planificada: sirve para ver que el sistema aguanta una
        // obra sin etapas y sin pagos, y que el estado de cuenta no inventa
        // cifras que no existen.
        Obra::query()->create([
            'tenant_id' => $empresaId,
            'codigo' => 'OB-2026-02',
            'nombre' => 'Vivienda colectiva Los Alcores',
            'cliente_nombre' => 'Inmobiliaria Los Alcores Ltda.',
            'cliente_rut' => '77111111',
            'direccion' => 'Av. Providencia 1234, Santiago',
            'tipo' => 'Edificacion',
            'fecha_inicio' => '2026-11-01',
            'fecha_termino_estimada' => '2027-08-30',
            'monto_contratado' => 18500.00,
            'estado' => EstadoObra::Planificacion->value,
        ]);
    }
}