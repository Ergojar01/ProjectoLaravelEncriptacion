<?php

namespace App\Support;

/** Lista local de contraseñas comunes (funciona sin Internet). */
final class CommonPasswords
{
    private const LIST = [
        'password', 'password1', 'password12', 'password123', 'passw0rd', 'p@ssw0rd', 'p@ssword', 'qwerty', 'qwerty123', 'qwertyuiop',
        'qwerty12345', 'asdfghjkl', 'zxcvbnm', '1qaz2wsx', '1q2w3e4r', '1q2w3e4r5t', 'qazwsxedc', 'abc123', 'abcd1234', 'abcdef123456',
        '123456', '1234567', '12345678', '123456789', '1234567890', '12345678910', '123123123', '111111', '000000', '121212',
        '123321', '654321', '987654321', '112233', '102030', 'iloveyou', 'iloveyou1', 'welcome', 'welcome1', 'welcome123',
        'admin', 'admin123', 'admin1234', 'administrator', 'root', 'root123', 'toor', 'letmein', 'letmein123', 'changeme',
        'master', 'monkey', 'dragon', 'football', 'baseball', 'superman', 'batman', 'trustno1', 'sunshine', 'princess',
        'shadow', 'michael', 'jordan', 'hunter', 'freedom', 'whatever', 'starwars', 'pokemon', 'naruto', 'onepiece',
        'luffy', 'zoro', 'nakama', 'grandline', 'strawhat', 'sombrerodepaja', 'contrasena', 'contraseña', 'clave123', 'micontrasena',
        'micontraseña', 'bienvenido', 'bienvenido1', 'hola1234', 'holamundo', 'holamundo123', 'te quiero', 'teamo', 'tequiero', 'cariño',
        'futbol', 'barcelona', 'realmadrid', 'america', 'panama', 'panama507', 'colombia', 'mexico', 'venezuela', 'argentina',
        'laravel', 'laravel123', 'php12345', 'mysql123', 'docker123', 'secret', 'secret123', 'default', 'guest', 'test1234',
        'usuario', 'usuario123', 'estudiante', 'universidad', 'ingenieria', 'sistemas', 'seguridad', 'encriptacion', 'cifrado', 'hakiencryption',
        '2024', '2025', '2026', 'primavera', 'verano2026', 'invierno', 'verano', 'otoño', 'abril2026', 'enero2026',
    ];

    public static function isCommon(string $password): bool
    {
        $normalized = self::normalize($password);

        foreach (self::LIST as $common) {
            $c = self::normalize($common);
            if ($normalized === $c) {
                return true;
            }
            // Una clave que es "común + relleno corto" (Password123!, Admin2026!) sigue siendo débil
            if (strlen($c) >= 5 && str_starts_with($normalized, $c) && strlen($normalized) - strlen($c) <= 5) {
                return true;
            }
        }

        // Secuencias y repeticiones: aaaaaaaaaaaa, 121212121212, abcdefghijkl
        if (preg_match('/^(.{1,3})\1{3,}$/su', $password)) {
            return true;
        }

        return false;
    }

    /** Minúsculas y sin "leet" (4→a, 3→e, 1→i, 0→o, 5→s, 7→t, @→a, $→s) ni símbolos. */
    private static function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, ['4' => 'a', '3' => 'e', '1' => 'i', '0' => 'o', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);

        return preg_replace('/[^a-z0-9ñáéíóú]/u', '', $value);
    }
}
