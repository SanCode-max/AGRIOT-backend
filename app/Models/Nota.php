<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nota extends Model
{
    protected $table = 'notas';

    protected $fillable = ['user_id', 'titulo', 'contenido', 'fecha_asociada', 'completada'];

    protected function casts(): array
    {
        return ['fecha_asociada' => 'date:Y-m-d', 'completada' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
