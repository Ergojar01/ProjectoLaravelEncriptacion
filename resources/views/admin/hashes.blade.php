@extends('layouts.app')
@section('title', 'Hashes de contraseñas')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hashes de contraseñas</h1>
            <p>Consulta académica de solo lectura. Cada acceso queda registrado en auditoría.</p>
        </div>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>ID de usuario</th><th>Hash de contraseña</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr><td>{{ $user['id'] }}</td><td class="mono">{{ $user['hash'] ?? 'No disponible' }}</td></tr>
                @empty
                    <tr><td colspan="2">No hay hashes disponibles.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $users->links() }}
@endsection
