<?php

namespace App\Http\Controllers;

use App\Models\EventoCalendario;
use App\Models\Nota;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgendaController extends Controller
{
    private const TIPOS_EVENTO = ['riego', 'fertilizacion', 'mantenimiento', 'visita', 'cosecha', 'general'];

    public function eventos(Request $request)
    {
        $rango = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);
        $query = EventoCalendario::query()->where('user_id', $request->user()->id)->orderBy('fecha_inicio');
        if (!empty($rango['desde'])) {
            $query->where(function ($builder) use ($rango) {
                $builder->whereNull('fecha_fin')->where('fecha_inicio', '>=', $rango['desde'])
                    ->orWhere('fecha_fin', '>=', $rango['desde']);
            });
        }
        if (!empty($rango['hasta'])) {
            $query->where('fecha_inicio', '<=', $rango['hasta']);
        }

        return response()->json(['eventos' => $query->get()]);
    }

    public function crearEvento(Request $request)
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'tipo' => ['required', 'string', Rule::in(self::TIPOS_EVENTO)],
        ]);
        $evento = EventoCalendario::create([...$data, 'user_id' => $request->user()->id]);

        return response()->json(['mensaje' => 'Evento creado.', 'evento' => $evento], 201);
    }

    public function actualizarEvento(Request $request, int $id)
    {
        $evento = EventoCalendario::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'tipo' => ['required', 'string', Rule::in(self::TIPOS_EVENTO)],
        ]);
        $evento->update($data);

        return response()->json(['mensaje' => 'Evento actualizado.', 'evento' => $evento->refresh()]);
    }

    public function eliminarEvento(Request $request, int $id)
    {
        EventoCalendario::query()->where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['mensaje' => 'Evento eliminado.']);
    }

    public function notas(Request $request)
    {
        $data = $request->validate(['desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date', 'after_or_equal:desde']]);
        $query = Nota::query()->where('user_id', $request->user()->id)->orderByDesc('updated_at');
        if (!empty($data['desde'])) $query->where(fn ($builder) => $builder->whereNull('fecha_asociada')->orWhere('fecha_asociada', '>=', $data['desde']));
        if (!empty($data['hasta'])) $query->where(fn ($builder) => $builder->whereNull('fecha_asociada')->orWhere('fecha_asociada', '<=', $data['hasta']));
        return response()->json(['notas' => $query->get()]);
    }

    public function crearNota(Request $request)
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'contenido' => ['required', 'string', 'max:10000'],
            'fecha_asociada' => ['nullable', 'date'],
            'completada' => ['sometimes', 'boolean'],
        ]);
        $nota = Nota::create([...$data, 'user_id' => $request->user()->id]);
        return response()->json(['mensaje' => 'Nota creada.', 'nota' => $nota], 201);
    }

    public function actualizarNota(Request $request, int $id)
    {
        $nota = Nota::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'contenido' => ['required', 'string', 'max:10000'],
            'fecha_asociada' => ['nullable', 'date'],
            'completada' => ['required', 'boolean'],
        ]);
        $nota->update($data);
        return response()->json(['mensaje' => 'Nota actualizada.', 'nota' => $nota->refresh()]);
    }

    public function eliminarNota(Request $request, int $id)
    {
        Nota::query()->where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['mensaje' => 'Nota eliminada.']);
    }
}
