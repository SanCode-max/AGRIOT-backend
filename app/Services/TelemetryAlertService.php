<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Cultivo;
use App\Models\Telemetria;

class TelemetryAlertService
{
    public function evaluate(Telemetria $reading, ?Cultivo $cultivo): void
    {
        $thresholds = config('nutritional_calculator.alerts');
        $now = now();

        if ($reading->humedad_relativa_calibrada_pct !== null) {
            $humidity = (float) $reading->humedad_relativa_calibrada_pct;
            $conditions = [];
            if ($humidity < $thresholds['moisture_low_pct']) {
                $conditions['humedad_baja'] = [
                    'severidad' => 'aviso', 'mensaje' => "Humedad baja detectada ({$humidity}%): el suelo puede requerir riego.",
                    'recomendacion' => 'Revise humedad en la zona radicular y caudal de los emisores. Si confirma déficit, aplique un pulso de riego controlado y vuelva a medir antes de extenderlo.',
                ];
            } elseif ($humidity > $thresholds['moisture_high_pct']) {
                $conditions['humedad_excesiva'] = [
                    'severidad' => 'precaucion', 'mensaje' => "Humedad elevada detectada ({$humidity}%): revise saturación y drenaje.",
                    'recomendacion' => 'Verifique drenaje, uniformidad y calibración del sensor; ajuste o pause el siguiente riego si la lectura se confirma.',
                ];
            }
            $this->syncMetric($reading, $cultivo, ['humedad_baja', 'humedad_excesiva'], $conditions, $humidity, '%', $now);
        }

        if ($reading->temperatura_suelo_C !== null) {
            $temperature = (float) $reading->temperatura_suelo_C;
            $conditions = [];
            if ($temperature < $thresholds['temperature_low_c']) {
                $conditions['temperatura_baja'] = [
                    'severidad' => 'precaucion', 'mensaje' => "Temperatura del suelo baja detectada ({$temperature} °C).",
                    'recomendacion' => 'Confirme la lectura en la zona radicular y revise el pronóstico local; proteja el cultivo ante riesgo de helada según el estado fenológico.',
                ];
            } elseif ($temperature > $thresholds['temperature_high_c']) {
                $conditions['temperatura_alta'] = [
                    'severidad' => 'aviso', 'mensaje' => "Temperatura del suelo alta detectada ({$temperature} °C).",
                    'recomendacion' => 'Verifique el sensor y la humedad radicular; reduzca el calentamiento del sustrato con cobertura y programe riego en horas frescas si se confirma estrés.',
                ];
            }
            $this->syncMetric($reading, $cultivo, ['temperatura_baja', 'temperatura_alta'], $conditions, $temperature, '°C', $now);
        }
    }

    private function syncMetric(Telemetria $reading, ?Cultivo $cultivo, array $types, array $conditions, float $value, string $unit, $now): void
    {
        foreach ($conditions as $type => $condition) {
            $alert = Alerta::where('device_id', $reading->device_id)
                ->where('tipo', $type)->whereIn('estado', ['activa', 'revisada'])->latest('id')->first();

            if ($alert) {
                $alert->update([
                    'valor' => $value,
                    'severidad' => $condition['severidad'],
                    'mensaje' => $condition['mensaje'],
                    'recomendacion' => $condition['recomendacion'],
                    'ultima_deteccion_en' => $now,
                    'cultivo_id' => $cultivo?->id ?? $alert->cultivo_id,
                ]);
                continue;
            }

            Alerta::create([
                'cultivo_id' => $cultivo?->id,
                'device_id' => $reading->device_id,
                'tipo' => $type,
                'severidad' => $condition['severidad'],
                'valor' => $value,
                'unidad' => $unit,
                'mensaje' => $condition['mensaje'],
                'recomendacion' => $condition['recomendacion'],
                'estado' => 'activa',
                'primera_deteccion_en' => $now,
                'ultima_deteccion_en' => $now,
                'metadata' => ['telemetria_id' => $reading->id],
            ]);
        }

        $cleared = Alerta::where('device_id', $reading->device_id)
            ->whereIn('tipo', $types)
            ->whereIn('estado', ['activa', 'revisada']);
        if ($conditions) {
            $cleared->whereNotIn('tipo', array_keys($conditions));
        }
        $cleared->update(['estado' => 'resuelta', 'resuelta_en' => $now, 'ultima_deteccion_en' => $now]);
    }
}
