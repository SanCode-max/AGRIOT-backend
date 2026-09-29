<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioDashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $cultivos = DB::table('cultivos')->where('user_id', $user->id)->get();

        return response()->json([
            'usuario' => ['id' => $user->id, 'nombre' => $user->nombre, 'apellido' => $user->apellido, 'correo' => $user->correo, 'rol' => $user->rol],
            'cultivos' => $cultivos->map(fn ($crop) => [
                'id' => $crop->id, 'nombre' => $crop->nombre,
                'fechaSiembra' => $crop->fecha_siembra, 'fechaCosecha' => $crop->fecha_cosecha,
                'estado' => $crop->estado, 'ubicacion' => $crop->ubicacion,
                'observaciones' => $crop->observaciones,
            ]),
            'resumen' => ['total_cultivos' => $cultivos->count()],
        ]);
    }
}
