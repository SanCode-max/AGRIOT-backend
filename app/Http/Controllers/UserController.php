<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function assignCultivos(Request $request, User $user)
    {
        $data = $request->validate([
            'cultivo_ids' => ['required', 'array'],
            'cultivo_ids.*' => ['integer', 'distinct', 'exists:cultivos,id'],
        ]);

        $count = DB::table('cultivos')->whereIn('id', $data['cultivo_ids'])
            ->update(['user_id' => $user->id, 'updated_at' => now()]);
        return response()->json(['mensaje' => 'Cultivos asignados.', 'asignados' => $count]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', 'unique:users,correo'],
            'rol' => ['required', 'in:admin,user'],
        ]);

        $temporaryPassword = Str::password(16, symbols: true);
        $user = User::create([
            ...$data,
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        $apiKey = config('services.brevo.key');
        if (!$apiKey) {
            $user->delete();
            return response()->json(['detail' => 'El servicio de correo no está configurado.'], 503);
        }

        $name = e(trim($user->nombre.' '.$user->apellido));
        $email = e($user->correo);
        $password = e($temporaryPassword);
        $response = Http::withHeaders(['api-key' => $apiKey, 'accept' => 'application/json'])
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'name' => config('mail.from.name', 'AgrIoT'),
                    'email' => config('mail.from.address'),
                ],
                'to' => [['email' => $user->correo, 'name' => trim($user->nombre.' '.$user->apellido)]],
                'subject' => 'Acceso a AgrIoT: contraseña temporal',
                'htmlContent' => "<div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#18352b'><h2>Bienvenido a AgrIoT, {$name}</h2><p>Tu usuario es <strong>{$email}</strong>.</p><p>Contraseña temporal: <strong>{$password}</strong></p><p>Inicia sesión y cambia esta contraseña antes de continuar. Por seguridad, no compartas este mensaje.</p></div>",
            ]);

        if (!$response->successful()) {
            $user->delete();
            return response()->json(['detail' => 'No se pudo enviar el correo de acceso. Verifica la configuración de Brevo e inténtalo de nuevo.'], 502);
        }

        return response()->json([
            'mensaje' => 'Usuario creado y credenciales enviadas por correo.',
            'usuario' => [
                'id' => $user->id, 'nombre' => $user->nombre, 'apellido' => $user->apellido,
                'correo' => $user->correo, 'rol' => $user->rol,
                'must_change_password' => (bool) $user->must_change_password,
            ],
        ], 201);
    }
}
