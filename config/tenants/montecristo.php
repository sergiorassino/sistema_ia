<?php

/*
 | Colegio Montecristo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=montecristo en el despliegue de ese colegio.
 */

return [
    'login' => [
        'niveles_ids' => [1, 2, 3, 5],
    ],

    'boletin_primario' => [
        'ipe_implementacion' => 'montecristo',
        'director_firma' => 'Prof. Lic. Adriana Rufino',
        'menu_etiqueta_boletin_ipe' => 'Síntesis y Calificaciones',
    ],

    'calificaciones_primario' => [
        'carga_estudiante' => ['implementacion' => 'montecristo'],
        'carga_materia' => ['implementacion' => 'montecristo'],
        'planilla' => ['implementacion' => 'montecristo'],
    ],

    'portal_docente' => [
        'menu' => [
            'inicial' => [
                'indicadores' => true,
                'observaciones' => true,
                'observaciones_materia' => true,
                'informe_progreso' => true,
            ],
            'primario' => [
                'carga_estudiante' => true,
                'carga_materia' => true,
                'boletin_ipe' => true,
                'planilla' => true,
            ],
            'secundario' => [
                'calificaciones' => true,
                'solicitud_evaluacion' => true,
            ],
        ],
    ],

    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'montecristo',
        ],
        // Sin informe de inasistencias en inicial ni primario.
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [1, 2],
        ],
    ],

    'autogestion' => [
        'aranceles_aulica_url' => 'https://familia.aulica.com.ar/login?idCompany=953',
        'comunicaciones' => [
            'habilitado' => false,
        ],
        'boletin_ipe_primario' => [
            'habilitado' => true,
        ],
        'informe_progreso_inicial' => [
            'habilitado' => true,
        ],
        // Sin informe de inasistencias en inicial ni primario.
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [1, 2],
        ],
    ],

    'modulos' => [
        'solicitud_evaluacion' => true,
    ],
];
