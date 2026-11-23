<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Credentials;
use Illuminate\Database\Seeder;

/**
 * Cuentas de prueba FICTICIAS, creadas solo para la evaluación (ver README).
 */
class DatabaseSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['Capitán Prueba',    'admin500@haki.test',  'Capitan#Haki-500!', 500],
        ['Vigía Prueba',      'visor499@haki.test',  'Vigia#Haki-4999!',  499],
        ['Tripulante Prueba', 'basico300@haki.test', 'Nakama#Haki-3000!', 300],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as [$name, $email, $password, $level]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name       = $name;
            $user->role_level = $level;
            $user->is_active  = true;
            Credentials::apply($user, $password);
            $user->save();
        }
    }
}
