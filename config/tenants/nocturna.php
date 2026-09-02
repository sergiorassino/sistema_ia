<?php

/*
 | Nocturna — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=nocturna en el despliegue de ese colegio.
 */

return [
    'portal_docente' => [
        'menu' => [
            'secundario' => [
                'cuaderno_seguimiento_aulico' => true,
            ],
        ],
    ],
];
