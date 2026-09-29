<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoCalendario extends Model
{
    protected $table = 'eventos_calendario';

    protected $fillable = ['user_id', 'titulo', 'descripcion', 'fecha_inicio', 'fecha_fin', 'tipo'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'datetime:Y-m-d\TH:i:s', 'fecha_fin' => 'datetime:Y-m-d\TH:i:s'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
