<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class CredentialHasher
{
    public static function attributes(#[\SensitiveParameter] string $password): array
    {
        return [
            'password' => Hash::make($password),
            'salt' => bin2hex(random_bytes(32)),
            'crypto_version' => config('crypto.version'),
        ];
    }

    public static function check(User $user, #[\SensitiveParameter] string $password): bool
    {
        if ($user->crypto_version === 1) {
            return self::checkLegacy($user, $password);
        }

        return $user->crypto_version === config('crypto.version')
            && Hash::info($user->password)['algoName'] === 'argon2id'
            && Hash::check($password, $user->password);
    }

    public static function checkTemporary(User $user, #[\SensitiveParameter] string $password): bool
    {
        if ($user->temp_password_hash === null) {
            // Previously issued temporary credentials lived in the password column.
            return $user->must_change_password && self::check($user, $password);
        }

        return Hash::check($password, $user->temp_password_hash);
    }

    public static function temporarySessionProof(User $user): string
    {
        return hash_hmac('sha256', $user->temp_password_hash ?? $user->password, config('app.key'));
    }

    private static function checkLegacy(User $user, #[\SensitiveParameter] string $password): bool
    {
        if (! ctype_xdigit($user->salt) || strlen($user->salt) !== 64
            || ! ctype_xdigit($user->password) || strlen($user->password) !== 128) {
            return false;
        }

        $pepper = (string) config('crypto.pepper');

        if (strlen($pepper) < 64) {
            throw new RuntimeException('CRYPTO_PEPPER no está configurada para verificar credenciales anteriores.');
        }

        $salt = hex2bin($user->salt);
        $email = mb_strtolower(trim($user->email));
        $userHash = hash_hmac('sha512', $email, $pepper.$salt, true);
        $passwordHash = hash_pbkdf2('sha512', $password, $salt.$pepper, config('crypto.iterations'), 64, true);
        $legacyHash = hash_hmac('sha512', $salt.($userHash ^ $passwordHash), $pepper);

        return hash_equals($user->password, $legacyHash);
    }
}
