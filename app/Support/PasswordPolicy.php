<?php

namespace App\Support;

use App\Rules\RejectUnsafePassword;
use Closure;

final class PasswordPolicy
{
    public static function rules(): array
    {
        return [
            'bail',
            'required',
            'string',
            'min:12',
            'max:64',
            'confirmed',
            static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }

                foreach ([
                    '/[A-Z]/' => 'La contraseña debe incluir al menos una mayúscula (A-Z).',
                    '/[a-z]/' => 'La contraseña debe incluir al menos una minúscula (a-z).',
                    '/[0-9]/' => 'La contraseña debe incluir al menos un número (0-9).',
                    '/[@$!%*?&#]/' => 'La contraseña debe incluir al menos un carácter especial de este conjunto: @$!%*?&#.',
                ] as $pattern => $message) {
                    if (! preg_match($pattern, $value)) {
                        $fail($message);
                    }
                }
            },
            new RejectUnsafePassword,
        ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede superar los :max caracteres.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'email.max' => 'El correo no puede superar los :max caracteres.',
            'email.unique' => 'Ese correo ya está registrado.',
            'current_password.required' => 'Escribe tu contraseña actual.',
            'current_password.string' => 'La contraseña actual debe ser texto.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.confirmed' => 'La confirmación debe coincidir con la contraseña.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.max' => 'La contraseña no puede superar los :max caracteres.',
            'role_level.required' => 'Selecciona un nivel de acceso.',
            'role_level.in' => 'Nivel de acceso no válido.',
        ];
    }
}
