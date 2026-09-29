<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cultivo;

class UsuarioDashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $cultivos = Cultivo::where('user_id', $user->id)->get();

        return response()->json([
            'usuario' => ['id' => $user->id, 'nombre' => $user->nombre, 'apellido' => $user->apellido, 'correo' => $user->correo, 'rol' => $user->rol],
            'cultivos' => $cultivos->map(fn ($crop) => [
                'id' => $crop->id, 'nombre' => $crop->nombre,
                'fechaSiembra' => $crop->fecha_siembra?->format('Y-m-d'),
                'fechaCosecha' => ($crop->fecha_estimada_cosecha ?? $crop->fecha_cosecha)?->format('Y-m-d'),
                'estado' => $crop->estado_actual ?? $crop->estado, 'ubicacion' => $crop->ubicacion,
                'device_id' => $crop->device_id,
                'observaciones' => $crop->observaciones,
            ]),
            'resumen' => ['total_cultivos' => $cultivos->count()],
        ]);
    }
}
