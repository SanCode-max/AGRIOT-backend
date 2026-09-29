<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cultivo extends Model
{
    protected $table = 'cultivos';

    protected $fillable = [
        'user_id', 'nombre', 'fecha_siembra', 'fecha_cosecha', 'estado',
        'ubicacion', 'observaciones', 'latitud', 'longitud',
    ];

    protected function casts(): array
    {
        return ['fecha_siembra' => 'date:Y-m-d', 'fecha_cosecha' => 'date:Y-m-d'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
