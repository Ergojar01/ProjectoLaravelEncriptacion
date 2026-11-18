<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CryptoDemo extends Command
{
    protected $signature = 'crypto:demo';

    protected $description = 'Demuestra cómo Laravel protege una contraseña de ejemplo';

    public function handle(): int
    {
        $sample = 'Aa1!'.bin2hex(random_bytes(16));
        $first = Hash::make($sample);
        $second = Hash::make($sample);

        $this->line('Algoritmo: '.Hash::info($first)['algoName']);
        $this->line('Hashes distintos para una misma contraseña: '.($first !== $second ? 'sí' : 'no'));
        $this->line('Verificación correcta: '.(Hash::check($sample, $first) ? 'sí' : 'no'));

        return self::SUCCESS;
    }
}
