<?php

return [

    /*
    | Control de intentos de inicio de sesión (se guardan en MySQL, no en caché:
    | el bloqueo sobrevive a recargas, cierres de navegador, sesiones nuevas y
    | a "cache:clear").
    */
    'login' => [
        'max_attempts'   => (int) env('LOGIN_MAX_ATTEMPTS', 3),    // por cuenta + IP
        'window_seconds' => (int) env('LOGIN_WINDOW_SECONDS', 180), // ventana de 3 minutos
        'lock_seconds'   => (int) env('LOGIN_LOCK_SECONDS', 180),   // bloqueo de 3 minutos
        'account_max'    => (int) env('LOGIN_ACCOUNT_MAX', 10),     // fallos contra una cuenta desde cualquier IP
        'ip_max'         => (int) env('LOGIN_IP_MAX', 20),          // fallos desde una misma IP (límite global por origen)
    ],

    /* Restablecimiento de contraseña con enlace de un solo uso */
    'reset' => [
        'ttl_minutes' => (int) env('RESET_TTL_MINUTES', 30),
        // true: el administrador recibe el enlace en pantalla (laboratorio sin correo).
        // false: el enlace se envía por correo y el administrador no lo ve.
        'show_link'   => (bool) env('RESET_SHOW_LINK', true),
    ],

    /* Contraseñas */
    'password' => [
        'min_length'   => 12,
        // Consulta la API k-anonymity de Have I Been Pwned (requiere Internet).
        'check_pwned'  => (bool) env('SECURITY_CHECK_PWNED', true),
    ],

    'force_https' => (bool) env('FORCE_HTTPS', false),
];
