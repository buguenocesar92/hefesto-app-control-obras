<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Guardas de autenticacion
    |--------------------------------------------------------------------------
    |
    | El guard `web` es el unico, y usa sesion. No hay guard con token de API:
    | la interfaz Nuxt corre en el mismo origen y habla con la API por el
    | servidor, asi que la cookie de sesion es lo unico que viaja. Eso evita
    | guardar tokens de larga duracion en el navegador de un maestro de obra
    | que trabaja con el computador en la obra.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'usuarios',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => App\Models\Usuario::class,
        ],
    ],

    'passwords' => [
        'usuarios' => [
            'provider' => 'usuarios',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];