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
            'temperatura_suelo' => ['nullable', 'numeric', 'between:-55,125'],
            'conductividad_electrica' => ['nullable', 'numeric', 'between:0,20'],
            'agua_litros' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'fertilizantes.n_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'fertilizantes.p2o5_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'fertilizantes.k2o_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'fertilizantes.n_producto' => ['nullable', 'string', 'max:100'],
            'fertilizantes.p_producto' => ['nullable', 'string', 'max:100'],
            'fertilizantes.k_producto' => ['nullable', 'string', 'max:100'],
            'acido_ml_litro_titulado' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'acido_tipo' => ['required_with:acido_ml_litro_titulado', 'nullable', Rule::in(['fosforico', 'nitrico'])],
            'acido_concentracion_pct' => ['required_with:acido_ml_litro_titulado', 'nullable', 'numeric', 'gt:0', 'max:100'],
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

        $fertilizerTotalGrams = array_sum(array_map(
            fn (string $nutrient) => $nutrients[$nutrient]['fertilizante_g'],
            ['n', 'p', 'k']
        ));
        $ecInitial = isset($data['conductividad_electrica']) ? (float) $data['conductividad_electrica'] : null;
        $ecFactor = (float) $config['ec_tds_factor_mg_l_per_ds_m'];
        $ecIncrement = round((($fertilizerTotalGrams / $volume) * 1000) / $ecFactor, 2);
        $ecEstimated = $ecInitial === null ? null : round($ecInitial + $ecIncrement, 2);

        $ph = (float) $data['ph'];
        $phStatus = $ph < $config['ph']['min'] ? 'bajo' : ($ph > $config['ph']['max'] ? 'alto' : 'optimo');
        $acidRate = isset($data['acido_ml_litro_titulado']) ? (float) $data['acido_ml_litro_titulado'] : null;
        $acidNeeded = $ph > $config['ph']['max'] && $acidRate !== null;
        $moisture = isset($data['humedad_suelo']) ? (float) $data['humedad_suelo'] : null;
        $soilTemperature = isset($data['temperatura_suelo']) ? (float) $data['temperatura_suelo'] : null;
        $conductivity = isset($data['conductividad_electrica']) ? (float) $data['conductividad_electrica'] : null;
        $ecThresholds = $config['electrical_conductivity'];
        $ecStatus = $conductivity === null ? null
            : ($conductivity >= $ecThresholds['high_ds_m'] ? 'alta'
                : ($conductivity >= $ecThresholds['elevated_ds_m'] ? 'elevada' : 'referencia'));

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
        if ($conductivity !== null && $ecStatus !== 'referencia') {
            $alerts[] = 'La CE está elevada para una referencia inicial de arándano. Confirma unidades y método de extracción antes de ajustar fertilización.';
        }
        if ($soilTemperature !== null && $soilTemperature < $config['temperature']['low_c']) {
            $alerts[] = 'Temperatura radicular baja: confirma el sensor y revisa el riesgo de helada según la etapa fenológica.';
        } elseif ($soilTemperature !== null && $soilTemperature > $config['temperature']['high_c']) {
            $alerts[] = 'Temperatura radicular alta: revisa humedad, cobertura del sustrato y horario de riego.';
        }

        $recommendations = [];
        if ($ph > $config['ph']['max']) $recommendations[] = $acidRate === null
            ? 'Realice una titulación del agua antes de acidificar; confirme pH y alcalinidad con una muestra representativa.'
            : 'Aplique el corrector titulado de forma gradual y verifique el pH de la solución antes del riego.';
        if ($ph < $config['ph']['min']) $recommendations[] = 'Confirme pH y alcalinidad; no agregue ácido y revise el plan de fertilización acidificante.';
        if ($moisture !== null && $moisture < $config['moisture']['low']) $recommendations[] = 'Confirma la humedad en la zona radicular y el caudal de los emisores; si hay déficit, aplica riego en pulsos controlados y vuelve a medir.';
        if ($moisture !== null && $moisture > $config['moisture']['excess']) $recommendations[] = 'Revisa drenaje y uniformidad; ajusta el siguiente ciclo solo tras confirmar que la zona radicular permanece saturada.';
        if ($conductivity !== null && $ecStatus !== 'referencia') $recommendations[] = 'Verifica la CE con el mismo método de muestreo y analiza agua/sustrato; evita aumentar fertilizantes hasta descartar acumulación de sales.';
        if ($soilTemperature !== null && $soilTemperature < $config['temperature']['low_c']) $recommendations[] = 'Confirma la temperatura radicular y aplica medidas de protección frente a heladas apropiadas para la etapa y el lote.';
        if ($soilTemperature !== null && $soilTemperature > $config['temperature']['high_c']) $recommendations[] = 'Confirma la lectura, protege el sustrato del calentamiento y programa riego en horas frescas si la humedad lo requiere.';
        foreach ($nutrients as $symbol => $result) {
            if ($result['estado'] === 'deficiente') $recommendations[] = strtoupper($symbol).' está por debajo del objetivo estimado; confirme unidades y análisis antes de fraccionar una corrección de fertirriego.';
            if ($result['estado'] === 'excesivo') $recommendations[] = strtoupper($symbol).' supera el objetivo estimado; suspenda aportes adicionales de ese nutriente hasta validar la medición.';
        }
        if (!$recommendations) $recommendations[] = 'Mantén el monitoreo y ajusta el fertirriego según análisis de agua, sustrato y respuesta del cultivo.';

        return response()->json([
            'cultivo' => ['id' => $cultivo->id, 'nombre' => $cultivo->nombre, 'device_id' => $cultivo->device_id],
            'etapa' => $stage['label'],
            'ph' => [
                'actual' => $ph,
                'objetivo' => [$config['ph']['min'], $config['ph']['max']],
                'estado' => $phStatus,
                'corrector' => $acidNeeded ? [
                    'tipo' => $data['acido_tipo'] ?? null,
                    'concentracion_pct' => isset($data['acido_concentracion_pct']) ? (float) $data['acido_concentracion_pct'] : null,
                    'ml_por_litro_titulado' => $acidRate,
                    'cantidad_ml' => round($acidRate * $volume, 2),
                ] : null,
                'ph_objetivo' => $config['ph']['target'],
                'requiere_titulacion' => $ph > $config['ph']['max'] && $acidRate === null,
            ],
            'nutrientes' => $nutrients,
            'humedad_suelo' => $moisture === null ? null : [
                'actual_porcentaje' => $moisture,
                'estado' => $moisture < $config['moisture']['low'] ? 'baja'
                    : ($moisture > $config['moisture']['excess'] ? 'excesiva'
                    : ($moisture > $config['moisture']['high'] ? 'alta' : 'optima')),
            ],
            'temperatura_suelo' => $soilTemperature === null ? null : [
                'actual_c' => $soilTemperature,
                'estado' => $soilTemperature < $config['temperature']['low_c'] ? 'baja'
                    : ($soilTemperature > $config['temperature']['high_c'] ? 'alta' : 'referencia'),
                'umbrales_referencia_c' => [$config['temperature']['low_c'], $config['temperature']['high_c']],
            ],
            'conductividad_electrica' => $conductivity === null ? null : [
                'actual_ds_m' => $conductivity,
                'estado' => $ecStatus,
                'umbral_elevado_ds_m' => $ecThresholds['elevated_ds_m'],
                'umbral_alto_ds_m' => $ecThresholds['high_ds_m'],
                'nota' => 'Los umbrales dependen del método de muestreo; valide con análisis de agua/sustrato del lote.',
            ],
            'ce_estimada' => [
                'ce_inicial_ds_m' => $ecInitial,
                'aporte_fertilizantes_ds_m' => $ecIncrement,
                'resultado_ds_m' => $ecEstimated,
                'metodo' => 'Aproximación TDS/CE (factor 640 mg/L por dS/m); confirme con conductímetro y solución preparada.',
            ],
            'agua_litros' => $volume,
            'dosis' => [
                'n_g' => $nutrients['n']['fertilizante_g'],
                'p_g' => $nutrients['p']['fertilizante_g'],
                'k_g' => $nutrients['k']['fertilizante_g'],
                'productos' => [
                    'n' => $data['fertilizantes']['n_producto'] ?? 'Fuente de N',
                    'p' => $data['fertilizantes']['p_producto'] ?? 'Fuente de P₂O₅',
                    'k' => $data['fertilizantes']['k_producto'] ?? 'Fuente de K₂O',
                ],
                'total_fertilizantes_g' => round($fertilizerTotalGrams, 2),
                'formula' => 'g = max(objetivo - lectura, 0) mg/L × litros ÷ (1000 × fracción elemental del producto)',
                'nota' => 'Equivalentes calculados por nutriente y por separado. El modelo no descuenta nutrientes secundarios aportados por MAP, nitrato de potasio u otros productos; revise compatibilidad y balance completo antes de aplicar.',
            ],
            'alertas' => $alerts,
            'recomendaciones' => $recommendations,
            'base_referencia_mg_l' => $config['base_targets_mg_l'],
            'aviso_agronomico' => 'Estimación inicial semiprofesional. Verifica con análisis de agua, sustrato y tejido, y calibra los objetivos de la finca antes de aplicar.',
        ]);
    }
}
