<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PerfilControlador extends Controller
{
    // 1. OBTENER PERFIL
    public function obtenerPerfil($correo)
    {
        $usuario = User::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json(['detail' => 'Usuario no encontrado'], 404);
        }

        return response()->json([
            'nombre'              => $usuario->nombre,
            'correo'              => $usuario->correo,
            'telefono'            => $usuario->telefono,
            'profesion'           => $usuario->profesion ?? 'Agricultor',
            'ubicacion'           => $usuario->ubicacion ?? 'Ubaté, Cundinamarca',
            'foto'                => $usuario->foto ? asset('storage/' . $usuario->foto) : null,
            'ultima_sesion'       => '14/03/2025',
            'proyectos_asignados' => 2,
            'cultivos_seguimiento'=> 3,
            'estabilidad'         => 75
        ], 200);
    }

    // 2. ACTUALIZAR DATOS DEL PERFIL
    public function actualizarPerfil(Request $request, $correo)
    {
        $usuario = User::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json(['detail' => 'Usuario no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'    => 'sometimes|required|string|max:100',
            'telefono'  => 'sometimes|required|string|digits:10',
            'ubicacion' => 'nullable|string|max:255',
            'profesion' => 'nullable|string|max:100',
            'latitud'   => 'nullable|numeric',
            'longitud'  => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['detail' => $validator->errors()->first()], 422);
        }

        // Actualizar campos
        $usuario->update($request->only([
            'nombre', 'telefono', 'ubicacion', 'profesion', 'latitud', 'longitud'
        ]));

        return response()->json([
            'mensaje' => 'Perfil actualizado correctamente',
            'usuario' => $usuario
        ], 200);
    }

    // 3. SUBIR / ACTUALIZAR FOTO DE PERFIL
    public function actualizarFoto(Request $request, $correo)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048', // Máximo 2MB
        ]);

        $usuario = User::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json(['detail' => 'Usuario no encontrado'], 404);
        }

        // Eliminar foto anterior si existe
        if ($usuario->foto && Storage::disk('public')->exists($usuario->foto)) {
            Storage::disk('public')->delete($usuario->foto);
        }

        // Guardar la nueva foto en storage/app/public/perfiles
        $rutaFoto = $request->file('foto')->store('perfiles', 'public');

        $usuario->foto = $rutaFoto;
        $usuario->save();

        return response()->json([
            'mensaje' => 'Foto de perfil actualizada correctamente',
            'foto'    => asset('storage/' . $rutaFoto)
        ], 200);
    }

    // 4. ELIMINAR CUENTA DE USUARIO
    public function eliminarCuenta(Request $request, $correo)
    {
        $usuario = User::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json(['detail' => 'Usuario no encontrado'], 404);
        }

        // Eliminar foto del servidor si tiene
        if ($usuario->foto && Storage::disk('public')->exists($usuario->foto)) {
            Storage::disk('public')->delete($usuario->foto);
        }

        // Eliminar usuario de la base de datos
        $usuario->delete();

        return response()->json([
            'mensaje' => 'La cuenta ha sido eliminada permanentemente'
        ], 200);
    }
}
