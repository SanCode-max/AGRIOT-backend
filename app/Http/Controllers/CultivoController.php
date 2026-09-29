<?php

namespace App\Http\Controllers;

use App\Models\Cultivo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CultivoController extends Controller
{
    private function puedeVerTodos(User $user): bool
    {
        return in_array($user->rol, ['admin', 'asistente'], true);
    }

    private function formato(Cultivo $cultivo): array
    {
        return [
            'id' => $cultivo->id,
            'nombre' => $cultivo->nombre,
            'variedad' => $cultivo->variedad ?: 'Arándano',
            'user_id' => $cultivo->user_id,
            'usuario' => $cultivo->user ? trim($cultivo->user->nombre.' '.$cultivo->user->apellido) : null,
            'device_id' => $cultivo->device_id,
            'fecha_siembra' => $cultivo->fecha_siembra?->format('Y-m-d'),
            'fecha_estimada_cosecha' => $cultivo->fecha_estimada_cosecha?->format('Y-m-d'),
            'estado_actual' => $cultivo->estado_actual ?? $cultivo->estado,
            'ubicacion' => $cultivo->ubicacion,
            'observaciones' => $cultivo->observaciones,
            'latitud' => $cultivo->latitud,
            'longitud' => $cultivo->longitud,
        ];
    }

    public function index(Request $request)
    {
        $query = Cultivo::with('user')->orderByDesc('created_at');
        if (!$this->puedeVerTodos($request->user())) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json(['cultivos' => $query->get()->map(fn (Cultivo $cultivo) => $this->formato($cultivo))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->reglasCultivo());

        $cultivo = Cultivo::create($this->atributosCultivo($data));

        return response()->json(['mensaje' => 'Cultivo registrado.', 'cultivo' => $this->formato($cultivo->load('user'))], 201);
    }

    public function update(Request $request, int $id)
    {
        $cultivo = Cultivo::findOrFail($id);
        $data = $request->validate($this->reglasCultivo($cultivo));

        DB::transaction(function () use ($cultivo, $data) {
            $cultivo->update($this->atributosCultivo($data));
        });

        return response()->json(['mensaje' => 'Cultivo actualizado.', 'cultivo' => $this->formato($cultivo->refresh()->load('user'))]);
    }

    public function destroy(int $id)
    {
        $cultivo = Cultivo::findOrFail($id);
        $cultivo->delete();

        return response()->json(['mensaje' => 'Cultivo eliminado.']);
    }

    private function reglasCultivo(?Cultivo $cultivo = null): array
    {
        $deviceIdRule = Rule::unique('cultivos', 'device_id');
        if ($cultivo) {
            $deviceIdRule->ignore($cultivo->id);
        }

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'variedad' => ['nullable', 'string', 'max:120'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('rol', 'user')],
            'device_id' => ['required', 'string', 'max:120', $deviceIdRule],
            'fecha_siembra' => ['required', 'date'],
            'fecha_estimada_cosecha' => ['nullable', 'date', 'after_or_equal:fecha_siembra'],
            'estado_actual' => ['required', 'string', Rule::in(['Vegetativo', 'Floración', 'Fructificación', 'Cosecha', 'Descanso', 'Otro'])],
            'ubicacion' => ['required', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
        ];
    }

    private function atributosCultivo(array $data): array
    {
        return [
            ...$data,
            // Compatibility columns are kept while the older dashboard is migrated.
            'fecha_cosecha' => $data['fecha_estimada_cosecha'] ?? null,
            'estado' => $data['estado_actual'],
        ];
    }

    public function show(Request $request, int $id)
    {
        $query = Cultivo::with('user')->whereKey($id);
        if (!$this->puedeVerTodos($request->user())) {
            $query->where('user_id', $request->user()->id);
        }
        $cultivo = $query->firstOrFail();

        return response()->json(['cultivo' => $this->formato($cultivo)]);
    }

    public function mapa(Request $request)
    {
        $query = Cultivo::with('user')->whereNotNull('latitud')->whereNotNull('longitud')->orderBy('nombre');
        if (!$this->puedeVerTodos($request->user())) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json([
            'cultivos' => $query->get()->map(fn (Cultivo $cultivo) => $this->formato($cultivo)),
        ]);
    }

    public function usuariosAsignables()
    {
        return response()->json([
            'usuarios' => User::query()->where('rol', 'user')->orderBy('nombre')->get(['id', 'nombre', 'apellido', 'correo']),
        ]);
    }
}
