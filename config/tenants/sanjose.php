<?php

/*
 | Colegio San José — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=sanjose en el despliegue de ese colegio.
 |
 | Foto carnet (mismo criterio que Caixal SF / Montecristo):
 | - Secretaría: solapa `foto_carnet` + campo `campos_legajo.fotoCarnet` en la BD
 |   (migración 2026_09_16_120000_seed_solapa_foto_carnet_sanjose; SQL equivalente
 |   database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql).
 |   Habilita ABM de legajos y modal en carga de calificaciones (Secretaría/Docentes).
 | - Autogestión familia: `autogestion.actualizacion_datos.foto_carnet` (abajo).
 |   Sin esa llave la familia no ve ni puede subir la foto aunque la solapa exista.
 |
 | Login: sin nivel Administración (5). No usan el sistema de cuotas; el destinatario
 | ARCA no aparece en Actualización de datos personales.
 */

return [
    'login' => [
        // Sin Administración (5): no usan el sistema de cuotas ni destinatario ARCA.
        // IDs pedagógicos: si un nivel no existe en `niveles`, no aparece en el login.
        'niveles_ids' => [1, 2, 3, 4, 6],
    ],

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
        'actualizacion_datos' => [
            // Familia puede ver y subir foto carnet (la solapa del ABM de legajos no alcanza sola).
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
