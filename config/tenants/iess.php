<?php

/*
 | IESS — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=iess en el despliegue de ese colegio.
 */

return [
    'boletin' => [
        'mostrar_tercer_materia' => true,
    ],

    'portal_docente' => [
        'menu' => [
            'secundario' => [
                'cuaderno_seguimiento_aulico' => true,
            ],
        ],
    ],
];
