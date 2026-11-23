<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'identifier', 'ip_address', 'cleared_at', 'created_at'];

    protected function casts(): array
    {
        return ['cleared_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
