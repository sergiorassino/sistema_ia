<?php

/*
 | EPQ — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=epq en el despliegue de ese colegio.
 |
 | Base operativa igual que San Francisco de Asís (cuotas SIRO, formulario SFA de datos
 | personales sin documentos institucionales, ficha de matrícula, recursos didácticos en
 | Menú de Docentes). Calificaciones primario: variante `epq`.
 */

return [
    'nombre' => 'EPQ',

    'institucional' => [
        'logo_forma' => 'emblema',
    ],

    'boletin_primario' => [
        'menu_etiqueta_boletin_ipe' => 'Boletín (Prim)',
        'epq_membrete_portada' => 'img/tenants/epq/boletin-prim-membrete.png',
    ],

    'boletin_secundario' => [
        'menu_etiqueta' => 'Informe de calificaciones',
        'epq_subtitulo_institucion' => 'Padres Escolapios',
        'epq_membrete' => 'img/tenants/epq/boletin-prim-membrete.png',
    ],

    'calificaciones_primario' => [
        'carga_estudiante' => ['implementacion' => 'epq'],
        'boletin_prim' => ['implementacion' => 'epq'],
        'planilla' => ['implementacion' => 'epq'],
    ],

    'calificaciones_secundario' => [
        'carga' => ['implementacion' => 'epq'],
        'boletin' => ['implementacion' => 'epq'],
        'planilla' => ['implementacion' => 'epq'],
    ],

    /*
     | Pie del informe de calificaciones secundario: filas en tabla `itemsboletin`.
     | Migración: database/migrations/2026_06_28_120000_seed_itemsboletin_epq_secundario.php
     | Catálogo PHP: App\Support\CalificacionesSecundario\Epq\ItemsBoletinEpqSecundarioCatalogo
     */

    'login' => [
        'niveles_ids' => [2, 3, 5],
    ],

    'cuotas' => [
        // Legacy EPQ: recargo % fijo por tramo (sin multiplicar por días).
        'interes_mora_modo' => 'total',

        'comprobante_pago' => [
            'implementacion' => 'epq',
        ],

        'siro' => [
            'habilitado' => true,
            'qr_url' => null,
            // Prefijo CPE SIRO (2 dígitos): obligatorio en Parámetros del sistema (ento.siroPrefijoCPE).
            // No se usa como fallback en generación de CPE.
            'cpe_prefijo' => null,
            'descarga_rendicion' => [
                'canales_planilla' => ['Roela'],
            ],
        ],

        'facturacion_afip' => [
            'habilitado' => true,
            'modo' => 'devengamiento',
            // Certificados WSAA/WSFE: carpeta y nombres de archivo en Parámetros del sistema (ento).
            'cbte_tipo' => 11,
            'nota_credito_tipo' => 12,
            'cbte_tipo_asociado' => 11,
            'produccion' => true,
            'simular' => true,
        ],
    ],

    'portal_docente' => [
        'menu' => [
            'inicial' => [
                'listado_estudiantes' => true,
                'listado_estudiantes_formato' => true,
                'recursos_didacticos_nueva_reserva' => true,
                'recursos_didacticos_listado' => true,
                // Calificaciones inicial (estándar): pendiente variante epq.
                'indicadores' => false,
                'observaciones' => false,
                'observaciones_materia' => false,
                'informe_progreso' => false,
            ],
            'primario' => [
                'listado_estudiantes' => true,
                'listado_estudiantes_formato' => true,
                'recursos_didacticos_nueva_reserva' => true,
                'recursos_didacticos_listado' => true,
                'carga_estudiante' => true,
                'carga_materia' => false,
                'boletin_ipe' => true,
                'planilla' => true,
            ],
            'secundario' => [
                'listado_estudiantes' => true,
                'listado_estudiantes_formato' => true,
                'recursos_didacticos_nueva_reserva' => true,
                'recursos_didacticos_listado' => true,
                'calificaciones' => true,
                'solicitud_evaluacion' => false,
                'cuaderno_seguimiento_aulico' => false,
            ],
        ],
    ],

    /*
     | Niveles EPQ: primario (2), secundario (3), administración (5). Sin inicial (1).
     | Menú de Administración (nivel 5): cuotas, becas, mora, cooperadora vía permisos_ia
     | y bloque `cuotas` arriba (SIRO, mora diaria).
     */

    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
        ],
        // Sin consulta de calificaciones estándar en secundario (usan Informe de calificaciones EPQ).
        'consulta_calificaciones' => [
            'habilitado' => false,
        ],
    ],

    'autogestion' => [
        /*
         | Menú de Alumnos — primario (nivel 2): boletín EPQ (portada + calificaciones),
         | Gestión de Aranceles e Inicio (escritorio). Datos personales / ficha: `ento.verDatosFicha`.
         | Secundario (nivel 3): consulta de calificaciones EPQ, aranceles e inasistencias.
         | Gestión de Aranceles (`gestion_aranceles`): todos los niveles con acceso al Menú de Alumnos.
         */
        'actualizacion_datos' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
            'requiere_documentos' => false,
        ],
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
        ],
        'aranceles_escolares' => [
            'habilitado' => true,
            'implementacion' => 'gestion_aranceles',
            'menu_etiqueta' => 'Gestión de Aranceles',
            'boton_pagos' => [
                'url' => 'https://siropagos.bancoroela.com.ar',
            ],
        ],
        // Primario (2) sin cuaderno; secundario (3) sí muestra comunicación institucional.
        'comunicaciones' => [
            'niveles_deshabilitados' => [2],
        ],
        // Boletín (Prim) EPQ en autogestión familia (portada + calificaciones).
        'boletin_prim_epq' => [
            'habilitado' => true,
        ],
        // Informe EPQ secundario en autogestión familia (consulta de calificaciones).
        'boletin_sec_epq' => [
            'habilitado' => true,
        ],
        // Sin consulta de calificaciones estándar (IPE / secundario).
        'consulta_calificaciones' => [
            'habilitado' => false,
        ],
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [2],
        ],
    ],
];
