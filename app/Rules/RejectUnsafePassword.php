<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

final class RejectUnsafePassword implements ValidationRule
{
    public function validate(string $attribute, #[\SensitiveParameter] mixed $value, Closure $fail): void
    {
        $normalized = Str::lower(Str::ascii($value));
        $substitutions = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's'];
        $candidates = [
            $value,
            $normalized,
            preg_replace('/[^a-z]/', '', $normalized),
            preg_replace('/[^a-z]/', '', strtr($normalized, $substitutions)),
        ];

        $blocklist = config('password_blocklist');

        foreach ($candidates as $candidate) {
            if (isset($blocklist[hash('sha256', $candidate)])) {
                $fail('Esta contraseña es demasiado común o aparece en la lista local de contraseñas inseguras. Elige otra.');

                return;
            }
        }
    }
}
