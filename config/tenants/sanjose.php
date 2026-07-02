<?php

/*
 | Colegio San José — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=sanjose en el despliegue de ese colegio.
 */

return [
    'boletin_primario' => [
        'ipe_implementacion' => 'sanjose',
    ],

    'calificaciones_primario' => [
        'carga_estudiante' => ['implementacion' => 'montecristo'],
        'carga_materia' => ['implementacion' => 'montecristo'],
        'planilla' => ['implementacion' => 'montecristo'],
    ],

    'portal_docente' => [
        'menu' => [
            'primario' => [
                'carga_estudiante' => true,
                'carga_materia' => true,
                'boletin_ipe' => true,
                'planilla' => true,
            ],
        ],
    ],
];
