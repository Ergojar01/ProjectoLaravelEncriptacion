{{-- Emblema del proyecto. Si colocas tu imagen en public/images/logo.png, se usa automáticamente. --}}
@if (file_exists(public_path('images/logo.png')))
    <img src="{{ asset('images/logo.png') }}" alt="One Piece Haki Encryption" {{ $attributes }}>
@else
    <svg viewBox="0 0 64 64" role="img" aria-label="One Piece Haki Encryption" {{ $attributes }}>
        <g stroke="#1a120a" stroke-width="2" stroke-linejoin="round" stroke-linecap="round">
            <path d="M12 44L52 60M52 44L12 60" stroke-width="6.5"/>
            <path d="M12 44L52 60M52 44L12 60" stroke="#fffaf0" stroke-width="3"/>
            <circle cx="32" cy="40" r="11" fill="#fffaf0"/>
            <circle cx="27.5" cy="39.5" r="2.7" fill="#1a120a"/>
            <circle cx="36.5" cy="39.5" r="2.7" fill="#1a120a"/>
            <path d="M32 43.5l-1.7 3h3.4z" fill="#1a120a"/>
            <path d="M27.5 48.5v2.5M32 48.5v2.5M36.5 48.5v2.5" stroke-width="1.3"/>
            <path d="M4 25Q32 36 60 25Q58 32 32 34Q6 32 4 25Z" fill="#f4b52e"/>
            <path d="M15 26C14 6 50 6 49 26Q32 31 15 26Z" fill="#f4b52e"/>
            <path d="M15.3 21.5Q32 27.5 48.7 21.5L49 26Q32 31 15 26Z" fill="#d62839"/>
        </g>
    </svg>
@endif
