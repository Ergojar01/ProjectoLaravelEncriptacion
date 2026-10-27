<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(9)->mixedCase()->numbers()->symbols();
    }

    public static function messages(): array
    {
        return [
            'name.required'      => 'El nombre es obligatorio.',
            'email.required'     => 'El correo es obligatorio.',
            'email.email'        => 'Ingresa un correo válido.',
            'email.unique'       => 'Ese correo ya está registrado.',
            'current_password.required' => 'Escribe tu contraseña actual.',
            'password.required'  => 'La contraseña es obligatoria.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min'       => 'La contraseña debe tener al menos 9 caracteres.',
            'password.mixed'     => 'Debe incluir mayúsculas y minúsculas.',
            'password.numbers'   => 'Debe incluir al menos un número.',
            'password.symbols'   => 'Debe incluir al menos un símbolo.',
            'role_level.in'      => 'Nivel de acceso no válido.',
        ];
    }
}
