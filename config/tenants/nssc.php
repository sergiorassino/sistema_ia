<?php

/*
 | Colegio NSSC — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=nssc en el despliegue de ese colegio.
 */

return [
    'programas_examen' => [
        'habilitado' => true,
        'glo_codcol' => 'nssc',
        'base_url' => 'https://sistemasescolares1.com/archivos',
        'anios' => [2022, 2021, 2020, 2019],
    ],
];
