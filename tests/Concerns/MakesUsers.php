<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Services\CredentialHasher;
use App\Services\Credentials;

trait MakesUsers
{
    protected const PASS = 'ClaveSegura#2026x';

    protected function setUpSecurity(): void
    {
        config([
            'crypto.pepper' => str_repeat('ab', 40),
            'crypto.iterations' => 1000,
            'security.password.check_pwned' => false,
            'hashing.argon.memory' => 1024, 'hashing.argon.time' => 1,
        ]);
    }

    protected function makeUser(int $level, string $email, bool $legacy = false): User
    {
        $u = new User(['name' => "Usuario {$level}", 'email' => $email, 'role_level' => $level, 'is_active' => true]);

        if ($legacy) {
            $salt = CredentialHasher::newSalt();
            $u->forceFill(['salt' => $salt, 'password' => CredentialHasher::make($email, self::PASS, $salt), 'crypto_version' => 1]);
        } else {
            Credentials::apply($u, self::PASS);
        }
        $u->save();

        return $u;
    }
}
