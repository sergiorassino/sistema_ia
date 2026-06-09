<?php

/*
 | Colegio Montecristo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=montecristo en el despliegue de ese colegio.
 */

return [
    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'montecristo',
        ],
    ],

    'autogestion' => [
        'aranceles_aulica_url' => 'https://familia.aulica.com.ar/login?idCompany=953',
    ],

    'modulos' => [
        'solicitud_evaluacion' => true,
    ],
];
