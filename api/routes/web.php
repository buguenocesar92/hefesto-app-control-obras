<?php

declare(strict_types=1);

use App\Http\Controllers\AdministracionController;
use App\Http\Controllers\AvancePagoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\EtapaController;
use App\Http\Controllers\EstadoDeCuentaController;
use App\Http\Controllers\InspeccionController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\RegistroSeguridadController;
use App\Http\Controllers\TareaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Control de Obras
|--------------------------------------------------------------------------
|
| Todas las rutas cuelgan del grupo `web`, que es lo que trae CSRF y sesion.
| El middleware `ResolverTenant` se anade al grupo en bootstrap/app.php, y
| por eso fija la empresa ANTES de que corra el `auth` de la ruta.
|
| SOLO TRES RUTAS SON PUBLICAS
| -----------------------------
| El listado de empresas, el inicio de sesion y el retorno de Authentik. Todo
| lo demas exige sesion. Si alguna ruta de datos de obra quedara abierta, la
| RLS la rechazaria con una excepcion, porque sin sesion no hay empresa en la
| conexion: el aislamiento falla cerrado.
|
*/

Route::get('/api/v1/salud', fn () => response()->json([
    'estado' => 'ok',
    'servicio' => 'control-obras',
]))->name('salud');

Route::get('/api/v1/empresas', [AuthController::class, 'empresas'])->name('empresas');
Route::get('/api/v1/auth/entrar', [AuthController::class, 'entrar'])->name('entrar');
Route::get('/api/v1/auth/retorno', [AuthController::class, 'retorno'])->name('retorno');
Route::get('/api/v1/auth/salir', [AuthController::class, 'salir'])->name('salir');

// ------------------------------------------------------------------
// Todo lo de aquí para abajo exige sesión.
// ------------------------------------------------------------------
Route::middleware('auth')->group(function (): void {
    Route::get('/api/v1/sesion', [AuthController::class, 'perfil'])->name('sesion');
    Route::get('/api/v1/catalogos', [AdministracionController::class, 'catalogos'])->name('catalogos');

    Route::get('/api/v1/usuarios', [AdministracionController::class, 'usuarios'])->name('usuarios');
    Route::post('/api/v1/usuarios', [AdministracionController::class, 'crearUsuario'])->name('usuarios.crear');

    Route::get('/api/v1/tipos-inspeccion', [AdministracionController::class, 'tiposInspeccion'])
        ->name('tipos-inspeccion');

    Route::get('/api/v1/obras', [ObraController::class, 'index'])->name('obras');
    Route::post('/api/v1/obras', [ObraController::class, 'store'])->name('obras.crear');
    Route::get('/api/v1/obras/{obra}', [ObraController::class, 'show'])->name('obras.ver');
    Route::put('/api/v1/obras/{obra}', [ObraController::class, 'update'])->name('obras.actualizar');
    Route::delete('/api/v1/obras/{obra}', [ObraController::class, 'destroy'])->name('obras.eliminar');

    // ETapas: el avance fisico vive aquí.
    Route::get('/api/v1/obras/{obra}/etapas', [EtapaController::class, 'index'])->name('etapas');
    Route::post('/api/v1/obras/{obra}/etapas', [EtapaController::class, 'store'])->name('etapas.crear');
    Route::put('/api/v1/etapas/{etapa}', [EtapaController::class, 'update'])->name('etapas.actualizar');

    // TAREAS: el tablero de lo comprometido.
    Route::get('/api/v1/obras/{obra}/tareas', [TareaController::class, 'index'])->name('tareas');
    Route::post('/api/v1/obras/{obra}/tareas', [TareaController::class, 'store'])->name('tareas.crear');
    Route::get('/api/v1/obras/{obra}/tareas-vencidas', [TareaController::class, 'vencidas'])
        ->name('tareas.vencidas');
    Route::put('/api/v1/tareas/{tarea}', [TareaController::class, 'update'])->name('tareas.actualizar');

    // Inspecciones: cada hallazgo genera su tarea de corrección con plazo.
    Route::get('/api/v1/obras/{obra}/inspecciones', [InspeccionController::class, 'index'])
        ->name('inspecciones');
    Route::post('/api/v1/obras/{obra}/inspecciones', [InspeccionController::class, 'store'])
        ->name('inspecciones.crear');
    Route::post('/api/v1/hallazgos/{hallazgo}/cerrar', [InspeccionController::class, 'cerrarHallazgo'])
        ->name('hallazgos.cerrar');

    // SEGURIDAD: accidentes y casi accidentes, por separado.
    Route::get('/api/v1/obras/{obra}/registros-seguridad', [RegistroSeguridadController::class, 'index'])
        ->name('registros-seguridad');
    Route::post('/api/v1/obras/{obra}/registros-seguridad', [RegistroSeguridadController::class, 'store'])
        ->name('registros-seguridad.crear');
    Route::post('/api/v1/registros-seguridad/{registro}/anonimizar', [RegistroSeguridadController::class, 'anonizar'])
        ->name('registros-seguridad.anonimizar');

    // PAGOS: el avance financiero.
    Route::get('/api/v1/obras/{obra}/avances-pago', [AvancePagoController::class, 'index'])
        ->name('avances-pago');
    Route::post('/api/v1/obras/{obra}/avances-pago', [AvancePagoController::class, 'store'])
        ->name('avances-pago.crear');

    // ESTADO DE CUENTA: fisico y financiero, siempre por separado.
    Route::get('/api/v1/obras/{obra}/estado-de-cuenta', [EstadoDeCuentaController::class, 'show'])
        ->name('estado-de-cuenta');

    // BITACORA: donde quedan escritas las decisiones.
    Route::get('/api/v1/obras/{obra}/bitacora', [BitacoraController::class, 'index'])->name('bitacora');
    Route::post('/api/v1/obras/{obra}/bitacora', [BitacoraController::class, 'store'])->name('bitacora.crear');
});