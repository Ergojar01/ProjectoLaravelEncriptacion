<?php

return [
    // Pimienta secreta (fuera de la base de datos). Se mezcla con HMAC antes de Argon2id.
    'pepper'     => env('CRYPTO_PEPPER'),

    // Solo para verificar cuentas antiguas (esquema v1: PBKDF2-SHA512 + XOR + HMAC).
    'iterations' => (int) env('CRYPTO_ITERATIONS', 210000),

    // 1 = esquema propio anterior | 2 = Argon2id (actual)
    'version'    => 2,
];
