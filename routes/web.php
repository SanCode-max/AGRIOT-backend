<?php

use App\Http\Controllers\AuthControlador;
use Illuminate\Support\Facades\Route;

Route::post('/registro', [AuthControlador::class, 'registro']);
