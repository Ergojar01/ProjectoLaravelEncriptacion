@extends('layouts.app')
@section('title', 'Bitácora')

@section('content')
    <div class="page-head">
        <div>
            <h1>Bitácora de seguridad</h1>
            <p>{{ number_format($logs->total()) }} eventos registrados · solo lectura · hora de {{ config('audit.timezone') }}.</p>
        </div>
    </div>

    <div class="audit-stats">
        <div class="card"><div class="card-body"><span class="muted small">Logins fallidos (24 h)</span><strong class="audit-num">{{ $summary['failed24'] }}</strong></div></div>
        <div class="card"><div class="card-body"><span class="muted small">Bloqueos (24 h)</span><strong class="audit-num">{{ $summary['locks24'] }}</strong></div></div>
        <div class="card"><div class="card-body"><span class="muted small">Accesos no autorizados (24 h)</span><strong class="audit-num">{{ $summary['denied24'] }}</strong></div></div>
    </div>

    <form method="GET" action="{{ route('admin.audit.index') }}" class="audit-filters">
        <select name="event" aria-label="Evento">
            <option value="">Todos los eventos</option>
            @foreach($events as $key => $label)
                <option value="{{ $key }}" @selected(($filters['event'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="result" aria-label="Resultado">
            <option value="">Todos los resultados</option>
            <option value="success" @selected(($filters['result'] ?? '') === 'success')>Correcto</option>
            <option value="failure" @selected(($filters['result'] ?? '') === 'failure')>Fallido</option>
            <option value="denied"  @selected(($filters['result'] ?? '') === 'denied')>Denegado</option>
        </select>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Correo o IP" maxlength="100" aria-label="Correo o IP">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Desde">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Hasta">
        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.audit.index') }}">Limpiar</a>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Fecha y hora</th><th>Evento</th><th>Resultado</th><th>Usuario</th><th>Origen</th><th>Detalle</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="mono small">{{ $log->shown_at?->format('Y-m-d H:i:s') }}</td>
                        <td><strong>{{ $log->event_label }}</strong></td>
                        <td>
                            @if($log->result === 'success')
                                <span class="badge b-ok">Correcto</span>
                            @elseif($log->result === 'failure')
                                <span class="badge b-warn">Fallido</span>
                            @else
                                <span class="badge b-you">Denegado</span>
                            @endif
                        </td>
                        <td>
                            @if($log->user)
                                <strong>{{ $log->user->name }}</strong>
                                <div class="muted small">{{ $log->user->email }}</div>
                            @else
                                <span class="muted small">{{ $log->identifier ?? 'Desconocido' }}</span>
                            @endif
                            @if($log->target)
                                <div class="muted small">→ {{ $log->target->email }}</div>
                            @elseif($log->user && $log->identifier && $log->identifier !== $log->user->email)
                                <div class="muted small">→ {{ $log->identifier }}</div>
                            @endif
                        </td>
                        <td class="mono small">{{ $log->ip_address ?? '—' }}</td>
                        <td class="small">{{ $log->details }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:2rem">Sin eventos para estos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="audit-pager">
        @if($logs->previousPageUrl())
            <a class="btn btn-ghost btn-sm" href="{{ $logs->previousPageUrl() }}">← Más recientes</a>
        @endif
        <span class="muted small">Página {{ $logs->currentPage() }} de {{ $logs->lastPage() }}</span>
        @if($logs->nextPageUrl())
            <a class="btn btn-ghost btn-sm" href="{{ $logs->nextPageUrl() }}">Más antiguos →</a>
        @endif
    </div>
@endsection
