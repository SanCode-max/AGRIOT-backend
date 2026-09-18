<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\RestablecerPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthControlador extends Controller
{

    // 1. REGISTRO
    public function registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'   => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'telefono' => 'required|string|digits:10',
            'correo'   => 'required|string|email|max:150|unique:users,correo',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[#$%&\/()=?.]/'
            ],
        ], [
            'correo.unique' => 'El correo electrónico ya está registrado.',
            'password.regex' => 'La contraseña no cumple con el formato requerido.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => $validator->errors()->first()
            ], 422);
        }

        try {
            $usuario = User::create([
                'nombre'   => $request->nombre,
                'apellido' => $request->apellido,
                'telefono' => $request->telefono,
                'correo'   => $request->correo,
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'mensaje' => 'Usuario registrado correctamente',
                'usuario' => $usuario
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'detail' => 'Error al registrar en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }


    // 2. LOGIN
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo'   => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => 'Por favor, ingrese correo y contraseña válidos.'
            ], 422);
        }

        // Buscamos al usuario por la columna 'correo'
        $usuario = User::where('correo', $request->correo)->first();

        // Verificamos si existe y si la contraseña coincide
        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'detail' => 'Correo o contraseña incorrectos'
            ], 401);
        }

        return response()->json([
            'mensaje' => 'Inicio de sesión exitoso',
            'usuario' => [
                'id'       => $usuario->id,
                'nombre'   => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'correo'   => $usuario->correo,
                'rol'      => 'administrador'
            ]
        ], 200);
    }

    // 1. SOLICITAR ENLACE DE RECUPERACIÓN DE CONTRASEÑA
    public function requestPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => 'Por favor, ingrese un correo electrónico válido.'
            ], 422);
        }

        try {
            $usuario = User::where('correo', $request->correo)->first();

            if (!$usuario) {
                return response()->json([
                    'detail' => 'Si el correo está registrado, recibirás un enlace de recuperación pronto.'
                ], 200);
            }

            $token = Str::random(60);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->correo],
                [
                    'token'      => Hash::make($token),
                    'created_at' => now()
                ]
            );

            $urlFrontend = env('FRONTEND_URL', 'https://frontend-agriot.vercel.app') 
                . '/restablecer-password?token=' . $token 
                . '&correo=' . urlencode($request->correo);

            // Envío directo mediante la API HTTP de Brevo (Puerto 443 - Inmune a bloqueos)
            $response = Http::withHeaders([
                'api-key' => env('BREVO_API_KEY'),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name' => 'AgrIoT Soporte',
                    'email' => env('MAIL_FROM_ADDRESS', 'santytorres879@gmail.com')
                ],
                'to' => [
                    ['email' => $request->correo, 'name' => $usuario->nombre]
                ],
                'subject' => 'Restablecimiento de Contraseña - AgrIoT',
                'htmlContent' => "
                    <h2>Hola, {$usuario->nombre}</h2>
                    <p>Has recibido este correo porque solicitaste un restablecimiento de contraseña para tu cuenta de AgrIoT.</p>
                    <p><a href='{$urlFrontend}' style='background: #2e7d32; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Restablecer Contraseña</a></p>
                    <p>Este enlace caducará en 1 hora.</p>
                "
            ]);

            if ($response->successful()) {
                return response()->json([
                    'detail' => 'Enlace enviado con éxito a tu correo electrónico.'
                ], 200);
            }

            return response()->json([
                'detail' => 'Error al enviar por API de Brevo: ' . $response->body()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'detail' => 'Error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    // 2. RESTABLECER LA CONTRASEÑA CON EL TOKEN
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo'                => 'required|email',
            'token'                 => 'required|string',
            'password'              => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[#$%&\/()=?.]/'
            ],
        ], [
            'password.regex' => 'La contraseña debe tener al menos 8 caracteres, mayúscula, minúscula, número y un carácter especial.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => $validator->errors()->first()
            ], 422);
        }

        // Buscar el registro del token en la base de datos
        $registroToken = DB::table('password_reset_tokens')
            ->where('email', $request->correo)
            ->first();

        if (!$registroToken) {
            return response()->json([
                'detail' => 'El enlace de recuperación es inválido o ha expirado.'
            ], 400);
        }

        // Verificar si el token ha expirado (ej. 60 minutos)
        if (now()->subSeconds(60)->gt($registroToken->created_at)) {
            DB::table('password_reset_tokens')->where('email', $request->correo)->delete();
            return response()->json([
                'detail' => 'El enlace de recuperación ha caducado. Solicita uno nuevo.'
            ], 400);
        }

        // Validar que el token coincida con el hash guardado
        if (!Hash::check($request->token, $registroToken->token)) {
            return response()->json([
                'detail' => 'El token proporcionado no es válido.'
            ], 400);
        }

        // Actualizar la contraseña del usuario
        $usuario = User::where('correo', $request->correo)->first();
        if ($usuario) {
            $usuario->password = Hash::make($request->password);
            $usuario->save();

            // Eliminar el token usado para evitar reutilizaciones
            DB::table('password_reset_tokens')->where('email', $request->correo)->delete();

            return response()->json([
                'detail' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.'
            ], 200);
        }

        return response()->json([
            'detail' => 'No se encontró el usuario asociado.'
        ], 404);
    }
}