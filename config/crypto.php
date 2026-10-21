<?php

return [
    'pepper'     => env('CRYPTO_PEPPER'),
    'iterations' => (int) env('CRYPTO_ITERATIONS', 210000),
    'version'    => 1,
];
