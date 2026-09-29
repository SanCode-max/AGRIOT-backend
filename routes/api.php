<?php

use App\Http\Controllers\AuthControlador;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PerfilControlador;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UsuarioDashboardController;
use App\Http\Controllers\CultivoController;
use App\Http\Controllers\NutritionalCalculatorController;
use App\Http\Controllers\AgendaController;

Route::post('/registro', [AuthControlador::class, 'registro']);
Route::post('/login', [AuthControlador::class, 'login']);
Route::post('/verificar_login_2fa', [AuthControlador::class, 'verificarLogin2FA']);
Route::post('/request_password', [AuthControlador::class, 'requestPassword']);
Route::post('/reset_password', [AuthControlador::class, 'resetPassword']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/perfil/{correo}', [PerfilControlador::class, 'obtenerPerfil'])->where('correo', '.*');
    Route::put('/perfil/{correo}', [PerfilControlador::class, 'actualizarPerfil'])->where('correo', '.*');
    Route::post('/perfil/foto/{correo}', [PerfilControlador::class, 'actualizarFoto'])->where('correo', '.*');
    Route::delete('/perfil/{correo}', [PerfilControlador::class, 'eliminarCuenta'])->where('correo', '.*');
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/password/change-initial', [AuthControlador::class, 'cambiarPasswordInicial']);
    Route::post('/logout', [AuthControlador::class, 'logout']);
    Route::middleware('password.changed')->group(function () {
        Route::get('/cultivos', [CultivoController::class, 'index']);
        Route::get('/cultivos/mapa', [CultivoController::class, 'mapa']);
        Route::get('/cultivos/{id}', [CultivoController::class, 'show'])->whereNumber('id');
        Route::post('/calculadora-nutricional', [NutritionalCalculatorController::class, 'calculate']);
        Route::prefix('agenda')->controller(AgendaController::class)->group(function () {
            Route::get('/eventos', 'eventos');
            Route::post('/eventos', 'crearEvento');
            Route::put('/eventos/{id}', 'actualizarEvento')->whereNumber('id');
            Route::delete('/eventos/{id}', 'eliminarEvento')->whereNumber('id');
            Route::get('/notas', 'notas');
            Route::post('/notas', 'crearNota');
            Route::put('/notas/{id}', 'actualizarNota')->whereNumber('id');
            Route::delete('/notas/{id}', 'eliminarNota')->whereNumber('id');
        });
        Route::middleware('role.crop-manager')->group(function () {
            Route::get('/admin/usuarios', [CultivoController::class, 'usuariosAsignables']);
            Route::post('/cultivos', [CultivoController::class, 'store']);
            Route::put('/cultivos/{id}', [CultivoController::class, 'update'])->whereNumber('id');
            Route::delete('/cultivos/{id}', [CultivoController::class, 'destroy'])->whereNumber('id');
        });
        Route::middleware('role.admin')->post('/admin/users', [UserController::class, 'store']);
        Route::middleware('role.admin')->put('/admin/users/{user}/cultivos', [UserController::class, 'assignCultivos']);
        Route::get('/usuario/dashboard', [UsuarioDashboardController::class, 'show']);
    });
});
Route::get('/ping', function () {
    return response()->json(['status' => 'active', 'message' => 'AgrIoT Backend Live']);
});
