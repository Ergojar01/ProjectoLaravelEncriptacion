<?php

namespace App\Rules;

use App\Support\CommonPasswords;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCommonPassword implements ValidationRule
{
    public function __construct(private ?string $email = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (CommonPasswords::isCommon($password)) {
            $fail('Esa contraseña es demasiado común o predecible. Elige otra.');

            return;
        }

        // No puede contener la parte local del correo de la propia cuenta
        $local = $this->email ? strtolower(strstr($this->email, '@', true) ?: '') : '';
        if (strlen($local) >= 4 && str_contains(strtolower($password), $local)) {
            $fail('La contraseña no puede contener tu nombre de usuario.');
        }
    }
}
