<?php

/*
 | Colegio San José — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=sanjose en el despliegue de ese colegio.
 */

return [
    'boletin_primario' => [
        'ipe_implementacion' => 'sanjose',
        'menu_etiqueta_boletin_ipe' => 'IPE (Informe de Progreso Escolar)',
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

    'autogestion' => [
        'boletin_ipe_primario' => [
            'habilitado' => true,
        ],
        'informe_progreso_inicial' => [
            'habilitado' => true,
        ],
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'sanjose',
        ],
        'cus' => [
            'habilitado' => true,
        ],
        'isa' => [
            'habilitado' => true,
        ],
        // Nivel inicial: sin comunicación institucional ni informe de inasistencias.
        'comunicaciones' => [
            'niveles_deshabilitados' => [1],
        ],
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [1],
        ],
    ],

    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'sanjose',
        ],
    ],
];
