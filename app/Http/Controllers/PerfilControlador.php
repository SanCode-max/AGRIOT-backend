<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PerfilControlador extends Controller
{
    private function autorizarPropietario(Request $request, string $correo): void
    {
        abort_if(!$request->user() || strcasecmp($request->user()->correo, $correo) !== 0, 403, 'Solo puedes gestionar tu propio perfil.');
    }

    // 1. OBTENER PERFIL
    public function obtenerPerfil(Request $request, $correo)
    {
        $this->autorizarPropietario($request, $correo);
        $usuario = User::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json(['detail' => 'Usuario no encontrado'], 404);
        }

        return response()->json([
            'nombre'              => $usuario->nombre,
            'apellido'            => $usuario->apellido,
            'nombre_completo'     => $usuario->nombre . ' ' . $usuario->apellido,
            'correo'              => $usuario->correo,
            'telefono'            => $usuario->telefono,
            'profesion'           => $usuario->profesion ?? 'Agricultor',
            'ubicacion'           => $usuario->ubicacion ?? 'Ubaté, Cundinamarca',
            'foto'                => $usuario->foto ? Storage::disk(config('filesystems.profile_disk', 'public'))->url($usuario->foto) : null,
            'ultima_sesion'       => 'Hoy',
            'proyectos_asignados' => 2,
            'cultivos_seguimiento'=> 3,
            'estabilidad'         => 75 
        ], 200);
    }

    // 2. ACTUALIZAR DATOS DEL PERFIL
    public function actualizarPerfil(Request $request, $correo)
    {
        $this->autorizarPropietario($request, $correo);
        $usuario = User::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json(['detail' => 'Usuario no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre'    => 'sometimes|required|string|max:255',
            'telefono'  => 'sometimes|required|string|digits:10',
            'ubicacion' => 'nullable|string|max:255',
            'profesion' => 'nullable|string|max:100',
            'latitud'   => 'nullable|numeric',
            'longitud'  => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['detail' => $validator->errors()->first()], 422);
        }

        // Si se envió un nombre completo, separar en 'nombre' y 'apellido'
        if ($request->has('nombre')) {
            $partesNombre = explode(' ', trim($request->nombre), 2);
            $usuario->nombre = $partesNombre[0]; // Primer nombre
            $usuario->apellido = $partesNombre[1] ?? ''; // Resto como apellidos
        }

        // Actualizar el resto de los campos
        if ($request->has('telefono'))  $usuario->telefono  = $request->telefono;
        if ($request->has('ubicacion')) $usuario->ubicacion = $request->ubicacion;
        if ($request->has('profesion')) $usuario->profesion = $request->profesion;
        if ($request->has('latitud'))   $usuario->latitud   = $request->latitud;
        if ($request->has('longitud'))  $usuario->longitud  = $request->longitud;

        $usuario->save();

        return response()->json([
            'mensaje' => 'Perfil actualizado correctamente',
            'usuario' => [
                'nombre'          => $usuario->nombre,
                'apellido'        => $usuario->apellido,
                'nombre_completo' => trim($usuario->nombre . ' ' . $usuario->apellido),
                'correo'          => $usuario->correo,
                'telefono'        => $usuario->telefono,
                'profesion'       => $usuario->profesion,
                'ubicacion'       => $usuario->ubicacion,
            ]
        ], 200);
    }

    // 3. SUBIR / ACTUALIZAR FOTO DE PERFIL
    public function actualizarFoto(Request $request, $correo)
    {
        $this->autorizarPropietario($request, $correo);
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048', // Máximo 2MB
        ]);

        $usuario = User::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json(['detail' => 'Usuario no encontrado'], 404);
        }

        $diskName = config('filesystems.profile_disk', 'public');
        $disk = Storage::disk($diskName);
        $rutaAnterior = $usuario->foto;
        $rutaFoto = $request->file('foto')->storePublicly('perfiles', $diskName);
        if (!$rutaFoto) {
            return response()->json(['detail' => 'No se pudo escribir la imagen en el almacenamiento.'], 500);
        }

        $usuario->foto = $rutaFoto;
        if (!$usuario->save()) {
            $disk->delete($rutaFoto);
            return response()->json(['detail' => 'No se pudo actualizar el perfil con la nueva imagen.'], 500);
        }

        // La foto anterior se elimina solo después de confirmar el guardado nuevo.
        if ($rutaAnterior && $rutaAnterior !== $rutaFoto && $disk->exists($rutaAnterior)) {
            $disk->delete($rutaAnterior);
        }

        return response()->json([
            'mensaje' => 'Foto de perfil actualizada correctamente',
            'foto'    => $disk->url($rutaFoto)
        ], 200);
    }

    // 4. ELIMINAR CUENTA DE USUARIO
    public function eliminarCuenta(Request $request, $correo)
    {
        $this->autorizarPropietario($request, $correo);
        $usuario = User::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json(['detail' => 'Usuario no encontrado'], 404);
        }

        // Eliminar foto del servidor si tiene
        $disk = Storage::disk(config('filesystems.profile_disk', 'public'));
        if ($usuario->foto && $disk->exists($usuario->foto)) {
            $disk->delete($usuario->foto);
        }

        // Eliminar usuario de la base de datos
        $usuario->delete();

        return response()->json([
            'mensaje' => 'La cuenta ha sido eliminada permanentemente'
        ], 200);
    }
}
