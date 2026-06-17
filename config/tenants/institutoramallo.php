<?php

/*
 | Instituto Ramallo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=institutoramallo en el despliegue de ese colegio.
 */

return [
    'cuotas' => [
        // Los % de interés en 2.º/3.er venc. y post-venc. son totales del tramo, no diarios.
        'interes_mora_modo' => 'total',
    ],
];
