<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Cultivo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertaController extends Controller
{
    private function accesibles(Request $request)
    {
        $user = $request->user();
        if (in_array($user->rol, ['admin', 'asistente'], true)) {
            return Alerta::with('cultivo:id,nombre');
        }

        $cultivos = Cultivo::where('user_id', $user->id)->get(['id', 'device_id']);
        $ids = $cultivos->pluck('id');
        $deviceIds = $cultivos->pluck('device_id')->filter()->unique()->values();

        return Alerta::with('cultivo:id,nombre')->where(function ($query) use ($ids, $deviceIds) {
            $query->whereIn('cultivo_id', $ids);
            if ($deviceIds->isNotEmpty()) {
                $query->orWhereIn('device_id', $deviceIds);
            }
        });
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'estado' => ['nullable', Rule::in(['activa', 'revisada', 'resuelta'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $alerts = $this->accesibles($request)
            ->when(isset($filters['estado']), fn ($query) => $query->where('estado', $filters['estado']))
            ->orderByDesc('ultima_deteccion_en')
            ->limit((int) ($filters['limit'] ?? 100))
            ->get();

        return response()->json(['alertas' => $alerts]);
    }

    public function revisar(Request $request, int $id)
    {
        $alert = $this->accesibles($request)->whereKey($id)->firstOrFail();
        if ($alert->estado === 'activa') {
            $alert->update(['estado' => 'revisada']);
        }

        return response()->json(['mensaje' => 'Alerta marcada como revisada.', 'alerta' => $alert->fresh('cultivo:id,nombre')]);
    }
}
