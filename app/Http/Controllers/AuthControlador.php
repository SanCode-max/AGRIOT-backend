<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

    // 2. LOGIN - PASO 1 (Valida credenciales y genera código OTP de 6 dígitos)
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo'   => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => 'Por favor, ingrese un correo electrónico y contraseña válidos.'
            ], 422);
        }

        $usuario = User::where('correo', $request->correo)->first();

        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'detail' => 'Correo o contraseña incorrectos'
            ], 401);
        }

        try {
            // Generar código numérico seguro de 6 dígitos
            $codigoOtp = random_int(100000, 999999);

            // Guardar código encriptado
            DB::table('login_mfa_tokens')->updateOrInsert(
                ['email' => $usuario->correo],
                [
                    'token'      => Hash::make($codigoOtp),
                    'created_at' => now()
                ]
            );

            // Envío vía API HTTP de Brevo (HTTPS Puerto 443)
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
                'subject' => "{$codigoOtp} es tu código de verificación - AgrIoT",
                'htmlContent' => "
                    <div style='font-family: Arial, sans-serif; padding: 25px; color: #333; max-width: 500px; margin: auto; border: 1px solid #e0e0e0; border-radius: 10px;'>
                        <h2 style='color: #2e7d32; text-align: center;'>AgrIoT - Verificación 2FA</h2>
                        <p>Hola, <strong>{$usuario->nombre}</strong>.</p>
                        <p>Usa el siguiente código de verificación de 6 dígitos para completar tu inicio de sesión:</p>
                        <div style='background-color: #f4f6f8; padding: 15px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2e7d32; margin: 20px 0; border-radius: 8px;'>
                            {$codigoOtp}
                        </div>
                        <p style='font-size: 13px; color: #666;'>Este código caducará en 5 minutos. No lo compartas con nadie.</p>
                    </div>
                "
            ]);

            if ($response->successful()) {
                return response()->json([
                    'requiere_2fa' => true,
                    'detail'       => 'Se ha enviado un código de verificación a tu correo.'
                ], 200);
            }

            return response()->json([
                'detail' => 'Error al enviar el código por la API de correo.'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'detail' => 'Error en el servidor al generar el código: ' . $e->getMessage()
            ], 500);
        }
    }

    // 3. LOGIN - PASO 2 (Valida el código de 6 dígitos e inicia sesión)
    public function verificarLogin2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'codigo' => 'required|numeric|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => 'El código debe ser de 6 dígitos numéricos.'
            ], 422);
        }

        $registroToken = DB::table('login_mfa_tokens')
            ->where('email', $request->correo)
            ->first();

        if (!$registroToken) {
            return response()->json([
                'detail' => 'El código es inválido o no se ha solicitado.'
            ], 400);
        }

        // --- SOLUCIÓN A ZONA HORARIA Y EXPIRACIÓN EN SEGUNDOS ---
        $tiempoCreacion = Carbon::parse($registroToken->created_at)->timestamp;
        $tiempoActual   = now()->timestamp;
        $diferenciaSegundos = $tiempoActual - $tiempoCreacion;

        // Permite un margen razonable de 5 minutos (300 segundos) y tolera pequeños desajustes de reloj
        if ($diferenciaSegundos < -60 || $diferenciaSegundos > 300) {
            DB::table('login_mfa_tokens')->where('email', $request->correo)->delete();
            return response()->json([
                'detail' => 'El código ha caducado. Vuelve a ingresar tus datos para generar uno nuevo.'
            ], 400);
        }

        // Validar hash del código
        if (!Hash::check($request->codigo, $registroToken->token)) {
            return response()->json([
                'detail' => 'Código de verificación incorrecto.'
            ], 400);
        }

        // Obtener datos del usuario
        $usuario = User::where('correo', $request->correo)->first();

        if ($usuario) {
            // Eliminar token para evitar reusar el mismo código
            DB::table('login_mfa_tokens')->where('email', $request->correo)->delete();

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

        return response()->json(['detail' => 'Usuario no encontrado.'], 404);
    }

    // 4. SOLICITAR ENLACE DE RECUPERACIÓN
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

            $response = Http::withHeaders([
                'api-key'      => env('BREVO_API_KEY'),
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name'  => 'AgrIoT Soporte',
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

    // 5. RESTABLECER LA CONTRASEÑA
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo'   => 'required|email',
            'token'    => 'required|string',
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
            'password.regex' => 'La contraseña debe tener al menos 8 caracteres, mayúscula, minúscula, número y un carácter especial.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'detail' => $validator->errors()->first()
            ], 422);
        }

        $registroToken = DB::table('password_reset_tokens')
            ->where('email', $request->correo)
            ->first();

        if (!$registroToken) {
            return response()->json([
                'detail' => 'El enlace de recuperación es inválido o ha expirado.'
            ], 400);
        }

        if (now()->subSeconds(3600)->gt($registroToken->created_at)) {
            DB::table('password_reset_tokens')->where('email', $request->correo)->delete();
            return response()->json([
                'detail' => 'El enlace de recuperación ha caducado. Solicita uno nuevo.'
            ], 400);
        }

        if (!Hash::check($request->token, $registroToken->token)) {
            return response()->json([
                'detail' => 'El token proporcionado no es válido.'
            ], 400);
        }

        $usuario = User::where('correo', $request->correo)->first();
        if ($usuario) {
            $usuario->password = Hash::make($request->password);
            $usuario->save();

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