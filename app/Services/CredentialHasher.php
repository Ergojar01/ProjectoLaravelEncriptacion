<?php

namespace App\Services;

use RuntimeException;

final class CredentialHasher
{
    /** Sal aleatoria de 256 bits (64 caracteres hex). */
    public static function newSalt(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** Hash final (128 caracteres hex) que se guarda en la columna password. */
    public static function make(string $username, string $password, string $salt): string
    {
        return self::stages($username, $password, $salt)['final'];
    }

    public static function check(string $username, string $password, string $salt, string $stored): bool
    {
        return hash_equals($stored, self::make($username, $password, $salt));
    }

    /** Gasta el mismo tiempo cuando el usuario no existe (evita enumeración). */
    public static function burn(string $username, string $password): bool
    {
        self::stages($username, $password, str_repeat('00', 32));

        return false;
    }

    /** Todas las etapas del proceso (para la demostración). */
    public static function stages(string $username, string $password, string $salt): array
    {
        $pepper  = self::pepper();
        $saltBin = hex2bin($salt);
        $user    = mb_strtolower(trim($username));

        // 1. SHA-512 del usuario (HMAC, clave = pimienta + sal)
        $userHash = hash_hmac('sha512', $user, $pepper . $saltBin, true);

        // 2. SHA-512 de la contraseña (PBKDF2, sal + pimienta + iteraciones)
        $passHash = hash_pbkdf2(
            'sha512',
            $password,
            $saltBin . $pepper,
            (int) config('crypto.iterations'),
            64,
            true
        );

        // 3. Ambos hashes en binario, combinados con XOR
        $mixed = $userHash ^ $passHash;

        // 4. Sello final: el XOR nunca se guarda crudo
        $final = hash_hmac('sha512', $saltBin . $mixed, $pepper, true);

        return [
            'salt'      => $salt,
            'user_hash' => bin2hex($userHash),
            'pass_hash' => bin2hex($passHash),
            'xor'       => bin2hex($mixed),
            'final'     => bin2hex($final),
        ];
    }

    /** Hex a cadena de 0 y 1 (para mostrar el binario). */
    public static function toBits(string $hex): string
    {
        $bits = '';
        foreach (str_split(hex2bin($hex)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return $bits;
    }

    private static function pepper(): string
    {
        $pepper = (string) config('crypto.pepper');

        if (strlen($pepper) < 64) {
            throw new RuntimeException('CRYPTO_PEPPER no está configurada o es demasiado corta (mínimo 64 caracteres).');
        }

        return $pepper;
    }
}
