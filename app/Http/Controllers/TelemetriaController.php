<?php

namespace App\Http\Controllers;

use App\Models\Cultivo;
use App\Models\Telemetria;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TelemetriaController extends Controller
{
    public function store(Request $request)
    {
        $secret = (string) config('services.iot.ingest_token');
        $provided = (string) $request->bearerToken();
        abort_unless($secret !== '' && hash_equals($secret, $provided), 401, 'Credenciales IoT inválidas.');

        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:120'],
            'temperatura_suelo_C' => ['nullable', 'numeric', 'between:-55,125', 'required_without_all:humedad_relativa_calibrada_pct,humedad_suelo_pct'],
            'humedad_relativa_calibrada_pct' => ['nullable', 'numeric', 'between:0,100'],
            'humedad_suelo_pct' => ['nullable', 'numeric', 'between:0,100'],
            'timestamp' => ['nullable', 'date'],
        ]);

        $cultivo = Cultivo::where('device_id', $data['device_id'])->first();
        if (!$cultivo) {
            throw ValidationException::withMessages(['device_id' => 'No existe un cultivo asociado a este dispositivo.']);
        }

        $observedAt = isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now();
        $humidity = $data['humedad_relativa_calibrada_pct'] ?? $data['humedad_suelo_pct'] ?? null;
        $telemetria = Telemetria::create([
            'cultivo_id' => $cultivo->id,
            'device_id' => $data['device_id'],
            'temperatura_suelo_C' => $data['temperatura_suelo_C'] ?? null,
            'humedad_relativa_calibrada_pct' => $humidity,
            'observed_at' => $observedAt,
            'payload' => $request->except(['token']),
        ]);

        return response()->json(['mensaje' => 'Telemetría almacenada.', 'telemetria' => $telemetria], 201);
    }

    public function index(Request $request, int $id)
    {
        $query = Cultivo::query()->whereKey($id);
        if (!in_array($request->user()->rol, ['admin', 'asistente'], true)) {
            $query->where('user_id', $request->user()->id);
        }
        $cultivo = $query->firstOrFail();

        $validated = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $limit = (int) ($validated['limit'] ?? 500);
        $rows = Telemetria::where('cultivo_id', $cultivo->id)
            ->when(isset($validated['desde']), fn ($q) => $q->where('observed_at', '>=', $validated['desde']))
            ->when(isset($validated['hasta']), fn ($q) => $q->where('observed_at', '<=', $validated['hasta']))
            ->orderByDesc('observed_at')->limit($limit)->get()->reverse()->values();

        return response()->json([
            'cultivo' => ['id' => $cultivo->id, 'nombre' => $cultivo->nombre, 'device_id' => $cultivo->device_id],
            'telemetria' => $rows,
            'actualizado_en' => now()->toIso8601String(),
        ]);
    }
}
