<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountLock extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'scope', 'identifier', 'ip_address', 'reason', 'locked_until', 'released_at', 'created_at'];

    protected function casts(): array
    {
        return ['locked_until' => 'datetime', 'released_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
