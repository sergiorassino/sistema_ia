<?php

/*
 | Colegio Montecristo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=montecristo en el despliegue de ese colegio.
 */

return [
    'autogestion' => [
        'aranceles_aulica_url' => 'https://familia.aulica.com.ar/login?idCompany=953',
    ],

    'modulos' => [
        'solicitud_evaluacion' => true,
    ],
];
