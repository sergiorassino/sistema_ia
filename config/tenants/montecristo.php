<?php

/*
 | Colegio Montecristo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=montecristo en el despliegue de ese colegio.
 */

return [
    'boletin_primario' => [
        'ipe_implementacion' => 'montecristo',
        'director_firma' => 'Prof. Lic. Adriana Rufino',
        'menu_etiqueta_boletin_ipe' => 'Síntesis y Calificaciones',
    ],

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
