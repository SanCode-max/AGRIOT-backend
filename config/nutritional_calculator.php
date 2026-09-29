<?php

/*
 * Valores iniciales de referencia para soluciones nutritivas de arándano.
 * Requieren validación local con análisis de agua, sustrato y tejido vegetal.
 * Cada finca puede ajustar los objetivos en mg/L según su sistema productivo.
 */
return [
    'ph' => ['min' => 4.5, 'max' => 5.5, 'target' => 5.2],
    'moisture' => ['low' => 40, 'high' => 80, 'excess' => 85],
    'npk_tolerance_percent' => 10,
    // Referencia inicial de solución nutritiva. Coeficientes por etapa editables.
    'base_targets_mg_l' => ['n' => 39.0, 'p' => 12.0, 'k' => 79.0],
    'stages' => [
        'vegetativo' => ['label' => 'Vegetativo', 'n' => 1.10, 'p' => 0.95, 'k' => 0.90],
        'floracion' => ['label' => 'Floración', 'n' => 1.00, 'p' => 1.05, 'k' => 1.00],
        'fructificacion' => ['label' => 'Fructificación', 'n' => 0.90, 'p' => 1.00, 'k' => 1.15],
        'cosecha' => ['label' => 'Cosecha', 'n' => 0.80, 'p' => 0.90, 'k' => 1.10],
    ],
    // Fracción elemental en las declaraciones comerciales P2O5 y K2O.
    'p_from_p2o5' => 0.4364,
    'k_from_k2o' => 0.8301,
];
