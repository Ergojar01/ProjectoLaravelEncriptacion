<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Contraseñas: Argon2id (con sal aleatoria única que genera el propio algoritmo)
 * más una "pimienta" secreta que vive fuera de la base de datos.
 *
 *   entrada  = Base64( HMAC-SHA512( contraseña , pimienta ) )
 *   guardado = Argon2id( entrada )            ← lo que se guarda en MySQL
 *
 * El HMAC previo permite contraseñas de cualquier longitud y caracteres especiales,
 * y hace inútil un volcado de la base de datos sin la pimienta.
 *
 * Las cuentas antiguas (crypto_version = 1) se verifican con el esquema anterior
 * y se convierten a Argon2id en su siguiente inicio de sesión correcto.
 */
final class Credentials
{
    public const VERSION = 2;

    /** Hash Argon2id listo para guardar. */
    public static function make(string $password): string
    {
        return Hash::make(self::prepare($password));
    }

    /** Verifica con Hash::check de Laravel (no se recalcula ni se compara como texto). */
    public static function check(User $user, string $password): bool
    {
        if ((int) $user->crypto_version < self::VERSION) {
            return $user->salt !== null
                && CredentialHasher::check($user->email, $password, $user->salt, $user->password);
        }

        // El Argon2id de la revisión anterior guardaba una sal auxiliar y no usaba pimienta.
        return Hash::check($user->salt === null ? self::prepare($password) : $password, $user->password);
    }

    /** Gasta el mismo tiempo cuando el usuario no existe (evita enumeración por tiempos). */
    public static function burn(string $password): bool
    {
        Hash::make(self::prepare($password));

        return false;
    }

    public static function needsUpgrade(User $user): bool
    {
        return (int) $user->crypto_version < self::VERSION
            || $user->salt !== null
            || Hash::needsRehash($user->password);
    }

    /** Asigna la contraseña al modelo (el llamador decide cuándo guardar). */
    public static function apply(User $user, string $password): void
    {
        $user->forceFill([
            'password' => self::make($password),
            'salt' => null,
            'crypto_version' => self::VERSION,
        ]);
    }

    private static function prepare(string $password): string
    {
        $pepper = (string) config('crypto.pepper');

        if (strlen($pepper) < 64) {
            throw new RuntimeException('CRYPTO_PEPPER no está configurada o es demasiado corta (mínimo 64 caracteres).');
        }

        return base64_encode(hash_hmac('sha512', $password, $pepper, true));
    }
}
