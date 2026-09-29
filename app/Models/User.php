<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'apellido',
        'telefono',
        'correo',
        'password',
        'rol',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function routeNotificationForMail($notification)
    {
        return $this->correo;
    }

    public function cultivos()
    {
        return $this->hasMany(Cultivo::class);
    }

    public function eventosCalendario()
    {
        return $this->hasMany(EventoCalendario::class);
    }

    public function notas()
    {
        return $this->hasMany(Nota::class);
    }
}
