<?php

/*
 | Colegio Caixal (San Francisco) — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=caixalsf en el despliegue de ese colegio.
 |
 | Autogestión familia (inicial / primario): mismas opciones de menú que San José.
 */

return [
    'boletin_primario' => [
        // Mismo PDF que NSSC (A4 vertical, selector 1ª/2ª etapa).
        'ipe_implementacion' => 'estandar',
        'menu_etiqueta_boletin_ipe' => 'Calificaciones',
    ],

    'calificaciones_primario' => [
        'carga_estudiante' => ['implementacion' => 'montecristo'],
        'carga_materia' => ['implementacion' => 'montecristo'],
        'planilla' => ['implementacion' => 'montecristo'],
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
        // Nivel inicial: sin comunicación institucional ni informe de inasistencias.
        'comunicaciones' => [
            'niveles_deshabilitados' => [1,2,3],
        ],
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [1,2],
        ],
    ],
];
