@extends('layouts.app')
@section('title', 'Auditoría')

@section('content')
    <div class="page-head"><h1>Registros de auditoría</h1></div>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fecha y hora</th><th>ID de usuario</th><th>Nivel</th><th>Acción</th><th>Resultado</th><th>Origen</th></tr></thead>
                <tbody>
                @forelse($entries as $entry)
                    <tr>
                        <td>{{ $entry->created_at }}</td>
                        <td>{{ $entry->actor_id }}</td>
                        <td>{{ $entry->actor_level }}</td>
                        <td>{{ $entry->action === 'password_hashes.viewed' ? 'Consulta de hashes' : $entry->action }}</td>
                        <td>{{ $entry->result === 'success' ? 'Correcto' : $entry->result }}</td>
                        <td>{{ $entry->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Aún no hay registros de auditoría.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $entries->links() }}
@endsection
