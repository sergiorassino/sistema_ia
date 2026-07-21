<?php

/*
 | Colegio Caixal (San Francisco) — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=caixalsf en el despliegue de ese colegio.
 */

return [
    'calificaciones_primario' => [
        'carga_estudiante' => ['implementacion' => 'montecristo'],
        'carga_materia' => ['implementacion' => 'montecristo'],
        'planilla' => ['implementacion' => 'montecristo'],
    ],

    'autogestion' => [
        'comunicaciones' => [
            'habilitado' => false,
        ],
    ],

    'planificaciones_programas' => [
        // 2026_PRIMERO_A_LENGUA_Y_LITERATURA_Prog.pdf / …_Plan.pdf
        'nombre_archivo' => '{anio}_{cursec}_{materia}_{tipo}.pdf',
    ],
];
