<?php

namespace App\Console\Commands;

use App\Services\CredentialHasher;
use Illuminate\Console\Command;

class CryptoDemo extends Command
{
    protected $signature = 'crypto:demo {user} {password}';

    protected $description = 'Muestra paso a paso cómo se protegen las credenciales';

    public function handle(): int
    {
        $salt = CredentialHasher::newSalt();
        $s    = CredentialHasher::stages($this->argument('user'), $this->argument('password'), $salt);

        $bits = fn (string $hex) => substr(CredentialHasher::toBits($hex), 0, 64) . '...';

        $this->info('1. SAL aleatoria (256 bits)');
        $this->line("   {$s['salt']}");
        $this->info('2. SHA-512 del usuario (HMAC + sal + pimienta)');
        $this->line("   {$s['user_hash']}");
        $this->line('   binario: ' . $bits($s['user_hash']));
        $this->info('3. SHA-512 de la contraseña (PBKDF2, iteraciones)');
        $this->line("   {$s['pass_hash']}");
        $this->line('   binario: ' . $bits($s['pass_hash']));
        $this->info('4. XOR de ambos binarios');
        $this->line("   {$s['xor']}");
        $this->line('   binario: ' . $bits($s['xor']));
        $this->info('5. Sello final HMAC-SHA-512 (lo que se guarda)');
        $this->line("   {$s['final']}");

        return self::SUCCESS;
    }
}
