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
        // IPE A4 vertical Caixal SF (selector 1ª/2ª etapa, ciclo en subtítulo, inasistencias de matrícula).
        'ipe_implementacion' => 'caixalsf',
        'menu_etiqueta_boletin_ipe' => 'IPE (Informe de Progreso Escolar)',
    ],

    'calificaciones_primario' => [
        'carga_estudiante' => ['implementacion' => 'montecristo'],
        'carga_materia' => ['implementacion' => 'montecristo'],
        'planilla' => ['implementacion' => 'montecristo'],
    ],

    'autogestion' => [
        'actualizacion_datos' => [
            // Familia puede subir foto carnet (la solapa del ABM de legajos no alcanza sola).
            'foto_carnet' => true,
        ],
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
            'niveles_deshabilitados' => [1,2],
        ],
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [1,2],
        ],
    ],
];
