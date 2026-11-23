<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Audit;
use Illuminate\Http\Request;

/**
 * Panel de auditoría: solo lectura. Lo ven los niveles 499 y 500.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'event'  => ['nullable', 'string', 'in:' . implode(',', array_keys(Audit::EVENTS))],
            'result' => ['nullable', 'string', 'in:success,failure,denied'],
            'q'      => ['nullable', 'string', 'max:100'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date'],
        ]);

        $tz = config('audit.timezone');

        $logs = AuditLog::query()
            ->with(['user:id,name,email', 'target:id,name,email'])
            ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', $v))
            ->when($filters['result'] ?? null, fn ($q, $v) => $q->where('result', $v))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $like = '%' . addcslashes($v, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('identifier', 'like', $like)->orWhere('ip_address', 'like', $like));
            })
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($v, $tz)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($v, $tz)->endOfDay()->utc()))
            ->orderByDesc('id')
            ->paginate((int) config('audit.per_page'))
            ->withQueryString();

        return view('admin.audit', [
            'logs'    => $logs,
            'filters' => $filters,
            'events'  => Audit::EVENTS,
            'summary' => [
                'failed24' => AuditLog::where('event', 'login')->where('result', 'failure')->where('created_at', '>=', now()->subDay())->count(),
                'locks24'  => AuditLog::where('event', 'account_locked')->where('created_at', '>=', now()->subDay())->count(),
                'denied24' => AuditLog::where('event', 'access_denied')->where('created_at', '>=', now()->subDay())->count(),
            ],
        ]);
    }
}
