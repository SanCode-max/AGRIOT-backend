<?php

use App\Http\Controllers\AuthControlador;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PerfilControlador;

Route::post('/registro', [AuthControlador::class, 'registro']);
Route::post('/login', [AuthControlador::class, 'login']);
Route::post('/verificar_login_2fa', [AuthControlador::class, 'verificarLogin2FA']);
Route::post('/request_password', [AuthControlador::class, 'requestPassword']);
Route::post('/reset_password', [AuthControlador::class, 'resetPassword']);
Route::get('/perfil/{correo}', [PerfilControlador::class, 'obtenerPerfil'])->where('correo', '.*');
Route::put('/perfil/{correo}', [PerfilControlador::class, 'actualizarPerfil'])->where('correo', '.*');
Route::post('/perfil/foto/{correo}', [PerfilControlador::class, 'actualizarFoto'])->where('correo', '.*');
Route::delete('/perfil/{correo}', [PerfilControlador::class, 'eliminarCuenta'])->where('correo', '.*');
Route::get('/ping', function () {
    return response()->json(['status' => 'active', 'message' => 'AgrIoT Backend Live']);
});
