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

        // Buscamos al usuario por correo
        $usuario = User::where('correo', $request->correo)->first();

        // Verificamos credenciales básicas
        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'detail' => 'Correo o contraseña incorrectos'
            ], 401);
        }

        try {
            // Generar token MFA de 60 caracteres
            $tokenMfa = Str::random(60);

            // Guardar o actualizar el token en la tabla login_mfa_tokens
            DB::table('login_mfa_tokens')->updateOrInsert(
                ['email' => $usuario->correo],
                [
                    'token'      => Hash::make($tokenMfa),
                    'created_at' => now()
                ]
            );

            // URL hacia el Frontend en Vercel para la verificación del 2FA
            $urlFrontend2FA = env('FRONTEND_URL', 'https://frontend-agriot.vercel.app') 
                . '/verificar-2fa?token=' . $tokenMfa 
                . '&correo=' . urlencode($usuario->correo);

            // Envío del enlace vía API HTTP de Brevo (Puerto 443 - HTTPS)
            $response = Http::withHeaders([
                'api-key'      => env('BREVO_API_KEY'),
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name'  => 'AgrIoT Seguridad',
                    'email' => env('MAIL_FROM_ADDRESS', 'santytorres879@gmail.com')
                ],
                'to' => [
                    ['email' => $usuario->correo, 'name' => $usuario->nombre]
                ],
                'subject' => 'Código de Verificación / Enlace de Acceso - AgrIoT',
                'htmlContent' => "
                    <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                        <h2>Autenticación de Dos Factores (2FA)</h2>
                        <p>Hola, <strong>{$usuario->nombre}</strong>.</p>
                        <p>Se ha solicitado un inicio de sesión en tu cuenta. Haz clic en el siguiente enlace para completar la verificación e ingresar al sistema:</p>
                        <p style='margin: 25px 0;'>
                            <a href='{$urlFrontend2FA}' style='background-color: #2e7d32; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Ingresar al Dashboard</a>
                        </p>
                        <p>Este enlace caducará en 10 minutos.</p>
                        <p style='color: #888; font-size: 12px;'>Si no intentaste iniciar sesión, ignora este correo.</p>
                    </div>
                "
            ]);

            if ($response->successful()) {
                return response()->json([
                    'requiere_2fa' => true,
                    'detail'       => 'Credenciales correctas. Se ha enviado un enlace de verificación a tu correo electrónico.'
                ], 200);
            }

            return response()->json([
                'detail' => 'Error al enviar el correo de verificación 2FA.'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'detail' => 'Error en el servidor al procesar 2FA: ' . $e->getMessage()
            ], 500);
        }
    }


    // 3. VERIFICAR LOGIN 2FA (Paso 2: Valida el token del link y autoriza la sesión)
    public function verificarLogin2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'token'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => 'Parámetros de verificación inválidos.'
            ], 422);
        }

        // Buscar registro del token
        $registroToken = DB::table('login_mfa_tokens')
            ->where('email', $request->correo)
            ->first();

        if (!$registroToken) {
            return response()->json([
                'detail' => 'El enlace de acceso es inválido o ya fue utilizado.'
            ], 400);
        }

        // Expiración de 10 minutos (600 segundos)
        if (now()->subSeconds(600)->gt($registroToken->created_at)) {
            DB::table('login_mfa_tokens')->where('email', $request->correo)->delete();
            return response()->json([
                'detail' => 'El enlace de acceso ha caducado. Vuelve a iniciar sesión.'
            ], 400);
        }

        // Validar token
        if (!Hash::check($request->token, $registroToken->token)) {
            return response()->json([
                'detail' => 'El token de verificación no es válido.'
            ], 400);
        }

        // Obtener usuario y responder inicio de sesión exitoso
        $usuario = User::where('correo', $request->correo)->first();

        if ($usuario) {
            // Eliminar token para evitar reutilización del enlace
            DB::table('login_mfa_tokens')->where('email', $request->correo)->delete();

            return response()->json([
                'mensaje' => 'Autenticación multifactor completada',
                'usuario' => [
                    'id'       => $usuario->id,
                    'nombre'   => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'correo'   => $usuario->correo,
                    'rol'      => 'administrador'
                ]
            ], 200);
        }

        return response()->json(['detail' => 'Usuario no encontrado.'], 404);
    }

    // 4. SOLICITAR ENLACE DE RECUPERACIÓN DE CONTRASEÑA
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

    // 5. RESTABLECER LA CONTRASEÑA CON EL TOKEN
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