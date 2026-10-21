<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'salt',
        'crypto_version',
        'role_level',
    ];

    protected $hidden = [
        'password',
        'salt',
        'remember_token',
    ];

    // IMPORTANTE: sin 'password' => 'hashed'. Si lo agregas, Laravel aplica bcrypt
    // encima de nuestro hash y el login nunca coincide.
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role_level'        => 'integer',
        ];
    }

    public function getRoleNameAttribute(): string
    {
        return match (true) {
            $this->role_level >= 500 => 'Administrador',
            $this->role_level >= 499 => 'Visualizador',
            default                  => 'Usuario básico',
        };
    }

    public function getRoleClassAttribute(): string
    {
        return match (true) {
            $this->role_level >= 500 => 'b-500',
            $this->role_level >= 499 => 'b-499',
            default                  => 'b-300',
        };
    }

    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }
}
