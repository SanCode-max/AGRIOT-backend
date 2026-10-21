<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Telemetria extends Model
{
    protected $table = 'telemetrias';

    protected $fillable = [
        'cultivo_id', 'device_id', 'temperatura_suelo_C',
        'humedad_relativa_calibrada_pct', 'observed_at', 'payload',
    ];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'payload' => 'array'];
    }

    public function cultivo()
    {
        return $this->belongsTo(Cultivo::class);
    }
}
