<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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

    protected $attributes = [
        'must_change_password' => false,
    ];

    protected $hidden = [
        'password',
        'salt',
        'remember_token',
        'temp_password_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role_level'        => 'integer',
            'crypto_version'    => 'integer',
            'must_change_password'     => 'boolean',
            'temp_password_expires_at' => 'datetime',
            'temp_password_used_at'    => 'datetime',
            'temp_requested_at'        => 'datetime',
        ];
    }

    public function credentialQuery(): Builder
    {
        return self::query()->whereKey($this->getKey())
            ->where('password', $this->password)
            ->where('temp_password_hash', $this->temp_password_hash)
            ->where('must_change_password', $this->must_change_password);
    }

    public function revokeDatabaseSessions(): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table'))
                ->where('user_id', $this->getKey())->delete();
        }
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
