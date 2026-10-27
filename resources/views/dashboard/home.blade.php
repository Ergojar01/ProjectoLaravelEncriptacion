@extends('layouts.app')
@section('title', '')

@section('content')
    <section class="welcome">
        <div class="manga-panel">
            <div class="burst">
                <h1 class="sfx">¡Bienvenidos!</h1>
                <p class="crew">A bordo, {{ $user->name }}</p>
            </div>
            <figure class="frame">
                <img src="{{ asset('images/bienvenida.gif') }}" alt="Bienvenidos a bordo">
            </figure>
        </div>
    </section>
@endsection
