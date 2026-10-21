<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'cultivo_id', 'device_id', 'tipo', 'severidad', 'valor', 'unidad',
        'mensaje', 'recomendacion', 'estado', 'primera_deteccion_en',
        'ultima_deteccion_en', 'resuelta_en', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'primera_deteccion_en' => 'datetime',
            'ultima_deteccion_en' => 'datetime',
            'resuelta_en' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function cultivo()
    {
        return $this->belongsTo(Cultivo::class);
    }
}
