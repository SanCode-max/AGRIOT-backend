<?php

namespace App\Http\Controllers;

use App\Models\Cultivo;
use App\Models\Telemetria;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TelemetriaController extends Controller
{
    public function store(Request $request)
    {
        $secret = (string) config('services.iot.ingest_token');
        $provided = (string) $request->header('X-Ingest-Token', '');

        if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:120'],
            'temperatura_suelo_C' => ['nullable', 'numeric', 'between:-55,125'],
            'humedad_relativa_calibrada_pct' => ['nullable', 'numeric', 'between:0,100'],
            'humedad_suelo_pct' => ['nullable', 'numeric', 'between:0,100'],
            'timestamp' => ['nullable', 'date'],
        ]);

        // Persist readings even if an administrator has not linked this device to a crop yet.
        $cultivo = Cultivo::where('device_id', $data['device_id'])->first();

        $observedAt = isset($data['timestamp'])
            ? Carbon::parse($data['timestamp'])->utc()
            : now()->utc();

        // The ESP32 uses the Unix epoch as a placeholder when NTP has not synced.
        // Do not persist that sentinel as a sensor observation; use server UTC instead.
        if ($observedAt->lessThanOrEqualTo(Carbon::createFromTimestampUTC(0))) {
            $observedAt = now()->utc();
        }
        $humidity = $data['humedad_relativa_calibrada_pct'] ?? $data['humedad_suelo_pct'] ?? null;
        $telemetria = Telemetria::create([
            'cultivo_id' => $cultivo?->id,
            'device_id' => $data['device_id'],
            'temperatura_suelo_C' => $data['temperatura_suelo_C'] ?? null,
            'humedad_relativa_calibrada_pct' => $humidity,
            'observed_at' => $observedAt,
            'payload' => $request->except(['token']),
        ]);

        return response()->json([
            'mensaje' => 'Telemetría almacenada.',
            'asociada_a_cultivo' => $cultivo !== null,
            'telemetria' => $telemetria,
        ], 201);
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
            'device_id' => ['nullable', 'string', 'max:120'],
        ]);
        $limit = (int) ($validated['limit'] ?? 500);
        $deviceId = $validated['device_id'] ?? $cultivo->device_id;

        // A user may request an unassigned device for a crop they can access,
        // but cannot read a device already linked to another user's crop.
        $deviceCultivo = $deviceId ? Cultivo::where('device_id', $deviceId)->first() : null;
        if (
            $deviceCultivo
            && $deviceCultivo->id !== $cultivo->id
            && !in_array($request->user()->rol, ['admin', 'asistente'], true)
            && $deviceCultivo->user_id !== $request->user()->id
        ) {
            abort(404);
        }

        $rows = Telemetria::where(function ($query) use ($cultivo, $deviceId) {
            $query->where('cultivo_id', $cultivo->id);
            if ($deviceId) {
                $query->orWhere('device_id', $deviceId);
            }
        })
            ->when(isset($validated['desde']), fn ($q) => $q->where('observed_at', '>=', $validated['desde']))
            ->when(isset($validated['hasta']), fn ($q) => $q->where('observed_at', '<=', $validated['hasta']))
            ->orderByDesc('observed_at')->limit($limit)->get()->reverse()->values();

        return response()->json([
            'cultivo' => ['id' => $cultivo->id, 'nombre' => $cultivo->nombre, 'device_id' => $cultivo->device_id],
            'device_id_consultado' => $deviceId,
            'telemetria' => $rows,
            'actualizado_en' => now()->toIso8601String(),
        ]);
    }
}
