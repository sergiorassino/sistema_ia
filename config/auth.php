<?php

return [

    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'profesores',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'profesores',
        ],
        'alumno' => [
            'driver'   => 'session',
            'provider' => 'alumnos',
        ],
    ],

    'providers' => [
        'profesores' => [
            'driver' => 'profesor',
        ],
        'alumnos' => [
            'driver' => 'alumno',
        ],
    ],

    'passwords' => [
        'profesores' => [
            'provider'  => 'profesores',
            'table'     => 'password_reset_tokens',
            'expire'    => 60,
            'throttle'  => 60,
        ],
    ],

    'password_timeout' => 10800,

];
