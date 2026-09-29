<?php

namespace App\Http\Controllers;

use App\Models\Cultivo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NutritionalCalculatorController extends Controller
{
    public function calculate(Request $request)
    {
        $config = config('nutritional_calculator');
        $data = $request->validate([
            'cultivo_id' => ['required', 'integer', 'exists:cultivos,id'],
            'etapa' => ['required', 'string', Rule::in(array_keys($config['stages']))],
            'ph' => ['required', 'numeric', 'between:0,14'],
            'n' => ['required', 'numeric', 'min:0', 'max:100000'],
            'p' => ['required', 'numeric', 'min:0', 'max:100000'],
            'k' => ['required', 'numeric', 'min:0', 'max:100000'],
            'humedad_suelo' => ['nullable', 'numeric', 'between:0,100'],
            'agua_litros' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'fertilizantes.n_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'fertilizantes.p2o5_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'fertilizantes.k2o_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'acido_ml_litro_titulado' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $user = $request->user();
        $query = Cultivo::query()->whereKey($data['cultivo_id']);
        if (!in_array($user->rol, ['admin', 'asistente'], true)) {
            $query->where('user_id', $user->id);
        }
        $cultivo = $query->firstOrFail();
        $stage = $config['stages'][$data['etapa']];
        $volume = (float) $data['agua_litros'];
        $tolerance = $config['npk_tolerance_percent'] / 100;
        $nutrients = [];
        $fertilizerFractions = [
            'n' => (float) $data['fertilizantes']['n_percent'] / 100,
            'p' => ((float) $data['fertilizantes']['p2o5_percent'] / 100) * $config['p_from_p2o5'],
            'k' => ((float) $data['fertilizantes']['k2o_percent'] / 100) * $config['k_from_k2o'],
        ];

        foreach (['n', 'p', 'k'] as $nutrient) {
            $target = $config['base_targets_mg_l'][$nutrient] * $stage[$nutrient];
            $current = (float) $data[$nutrient];
            $nutrients[$nutrient] = [
                'actual_mg_l' => $current,
                'objetivo_mg_l' => round($target, 2),
                'estado' => $current < $target * (1 - $tolerance) ? 'deficiente'
                    : ($current > $target * (1 + $tolerance) ? 'excesivo' : 'optimo'),
                // Gramos de producto equivalente para corregir solo la deficiencia.
                'deficit_mg' => round(max(0, $target - $current) * $volume, 2),
                'fertilizante_g' => round(max(0, $target - $current) * $volume / (1000 * $fertilizerFractions[$nutrient]), 2),
            ];
        }

        $ph = (float) $data['ph'];
        $phStatus = $ph < $config['ph']['min'] ? 'bajo' : ($ph > $config['ph']['max'] ? 'alto' : 'optimo');
        $acidRate = isset($data['acido_ml_litro_titulado']) ? (float) $data['acido_ml_litro_titulado'] : null;
        $acidNeeded = $ph > $config['ph']['max'] && $acidRate !== null;
        $moisture = isset($data['humedad_suelo']) ? (float) $data['humedad_suelo'] : null;

        $alerts = [];
        if ($ph > $config['ph']['max']) {
            $alerts[] = $acidRate === null
                ? 'El pH supera el rango objetivo. Realiza una titulación del agua de riego antes de calcular ácido.'
                : 'El pH supera el rango objetivo; la dosis de ácido usa el factor medido en la titulación ingresada.';
        } elseif ($ph < $config['ph']['min']) {
            $alerts[] = 'El pH está por debajo del rango objetivo; evita acidificar y confirma la medición.';
        }
        if ($moisture !== null && $moisture < $config['moisture']['low']) $alerts[] = 'Humedad baja: revisa el tiempo y la uniformidad del riego.';
        if ($moisture !== null && $moisture > $config['moisture']['excess']) $alerts[] = 'Humedad elevada: revisa drenaje y programación del riego.';
        foreach ($nutrients as $symbol => $result) {
            if ($result['estado'] === 'excesivo') $alerts[] = strtoupper($symbol).' supera el objetivo; no añadas su fertilizante hasta validar la lectura.';
        }

        return response()->json([
            'cultivo' => ['id' => $cultivo->id, 'nombre' => $cultivo->nombre, 'device_id' => $cultivo->device_id],
            'etapa' => $stage['label'],
            'ph' => [
                'actual' => $ph,
                'objetivo' => [$config['ph']['min'], $config['ph']['max']],
                'estado' => $phStatus,
                'corrector' => $acidNeeded ? [
                    'tipo' => 'Ácido definido y titulado por el usuario',
                    'ml_por_litro_titulado' => $acidRate,
                    'cantidad_ml' => round($acidRate * $volume, 2),
                ] : null,
                'requiere_titulacion' => $ph > $config['ph']['max'] && $acidRate === null,
            ],
            'nutrientes' => $nutrients,
            'humedad_suelo' => $moisture === null ? null : [
                'actual_porcentaje' => $moisture,
                'estado' => $moisture < $config['moisture']['low'] ? 'baja'
                    : ($moisture > $config['moisture']['excess'] ? 'excesiva'
                    : ($moisture > $config['moisture']['high'] ? 'alta' : 'optima')),
            ],
            'agua_litros' => $volume,
            'dosis' => [
                'n_g' => $nutrients['n']['fertilizante_g'],
                'p_g' => $nutrients['p']['fertilizante_g'],
                'k_g' => $nutrients['k']['fertilizante_g'],
                'formula' => 'g = max(objetivo - lectura, 0) mg/L × litros ÷ (1000 × fracción elemental del producto)',
                'nota' => 'Equivalentes de productos separados calculados desde el análisis de etiqueta; no mezclar estas cantidades sin revisar compatibilidad y balance.',
            ],
            'alertas' => $alerts,
            'base_referencia_mg_l' => $config['base_targets_mg_l'],
            'aviso_agronomico' => 'Estimación inicial semiprofesional. Verifica con análisis de agua, sustrato y tejido, y calibra los objetivos de la finca antes de aplicar.',
        ]);
    }
}
