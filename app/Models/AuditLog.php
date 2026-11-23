<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría de solo escritura: la aplicación no ofrece ninguna
 * ruta para editar ni borrar estos registros.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'target_user_id', 'identifier', 'event', 'result',
        'ip_address', 'user_agent', 'details', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function getEventLabelAttribute(): string
    {
        return \App\Services\Audit::EVENTS[$this->event] ?? $this->event;
    }

    public function getShownAtAttribute()
    {
        return $this->created_at?->copy()->timezone(config('audit.timezone'));
    }
}
