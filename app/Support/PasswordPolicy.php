<?php

namespace App\Support;

use App\Rules\NotCommonPassword;
use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    /** Reglas completas para una contraseña NUEVA (mínimo 12, sin comunes ni comprometidas). */
    public static function rules(?string $email = null): array
    {
        $password = Password::min((int) config('security.password.min_length'))
            ->max(1024)            // permite frases largas y caracteres especiales
            ->mixedCase()
            ->numbers()
            ->symbols();

        if (config('security.password.check_pwned')) {
            $password->uncompromised(3);
        }

        return [$password, new NotCommonPassword($email)];
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
            'password.min'       => 'La contraseña debe tener al menos 12 caracteres.',
            'password.max'       => 'La contraseña es demasiado larga.',
            'password.mixed'     => 'Debe incluir mayúsculas y minúsculas.',
            'password.numbers'   => 'Debe incluir al menos un número.',
            'password.symbols'   => 'Debe incluir al menos un símbolo.',
            'password.uncompromised' => 'Esa contraseña apareció en filtraciones de datos conocidas. Elige otra.',
            'role_level.in'      => 'Nivel de acceso no válido (solo 499 o 300).',
        ];
    }
}
