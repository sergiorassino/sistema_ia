<?php

/*
 | Colegio Montecristo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=montecristo en el despliegue de ese colegio.
 |
 | Foto carnet (mismo criterio que Caixal SF):
 | - Secretaría: solapa `foto_carnet` + campo `campos_legajo.fotoCarnet` en la BD
 |   (migración 2026_09_03_120000_seed_solapa_foto_carnet_montecristo; SQL equivalente
 |   database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql).
 |   Habilita ABM de legajos, modal en carga de calificaciones (Secretaría/Docentes)
 |   y el modelo Fotos de listados con formato.
 | - Autogestión familia: `autogestion.actualizacion_datos.foto_carnet` (abajo).
 |   Sin esa llave la familia no puede subir la foto aunque la solapa exista.
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

    'calificaciones_inicial' => [
        'informe_progreso' => ['implementacion' => 'montecristo'],
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
        'tea_registros' => [
            'implementacion' => 'montecristo',
        ],
    ],

    /*
     | Deuda Áulica: credenciales AULICA_* en .env. Se usa al emitir Libre Deuda (portal familia).
     | AULICA_AMBIENTE=test|produccion pisa `ambiente` si está definido.
     */
    'aulica_deuda' => [
        'habilitado' => true,
        'ambiente' => 'test',
        'bloquear_autogestion' => false,
    ],

    'autogestion' => [
        'aranceles_aulica_url' => 'https://familia.aulica.com.ar/login?idCompany=953',
        'libre_deuda' => [
            'habilitado' => true,
            'lugar' => 'Monte Cristo',
            'firma' => 'img/tenants/montecristo/libre-deuda-firma.png',
            'sello' => 'img/tenants/montecristo/libre-deuda-sello.png',
        ],
        'actualizacion_datos' => [
            // Familia puede subir foto carnet (la solapa del ABM de legajos no alcanza sola).
            'foto_carnet' => true,
        ],
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

    'programas_examen' => [
        'habilitado' => true,
        // Años ofrecidos en /programas-examen (más reciente primero).
        'anios' => [2026, 2025, 2024],
    ],

    'doc_pp' => [
        'habilitado' => true,
    ],

    // Registro de asistencia: inicial/primario en blanco (llenado manual); secundario usa el default con_datos.
    'registro_asistencia' => [
        'por_nivel' => [
            1 => 'sin_datos',
            2 => 'sin_datos',
        ],
    ],
];
