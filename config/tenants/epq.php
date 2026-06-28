<?php

/*
 | EPQ — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=epq en el despliegue de ese colegio.
 |
 | Base operativa igual que San Francisco de Asís (cuotas SIRO, autogestión con documentos,
 | ficha de matrícula, recursos didácticos en Menú de Docentes). Calificaciones primario: variante `epq`.
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

    'calificaciones_primario' => [
        'carga_estudiante' => ['implementacion' => 'epq'],
        'boletin_prim' => ['implementacion' => 'epq'],
        'planilla' => ['implementacion' => 'epq'],
    ],

    'login' => [
        'niveles_ids' => [1, 2, 3, 5],
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
            // Prefijo CPE SIRO (2 dígitos): confirmar con el colegio al desplegar.
            'cpe_prefijo' => null,
            'descarga_rendicion' => [
                'canales_planilla' => ['Roela'],
            ],
        ],
    ],

    'portal_docente' => [
        'menu' => [
            'inicial' => [
                'listado_estudiantes' => true,
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
                'recursos_didacticos_nueva_reserva' => true,
                'recursos_didacticos_listado' => true,
                'carga_estudiante' => true,
                'carga_materia' => false,
                'boletin_ipe' => true,
                'planilla' => true,
            ],
            'secundario' => [
                'listado_estudiantes' => true,
                'recursos_didacticos_nueva_reserva' => true,
                'recursos_didacticos_listado' => true,
                // Calificaciones secundario (estándar): pendiente variante epq.
                'calificaciones' => false,
                'solicitud_evaluacion' => false,
                'cuaderno_seguimiento_aulico' => false,
            ],
        ],
    ],

    /*
     | Menú de Administración (nivel 5): cuotas, becas, mora, cooperadora vía permisos_ia
     | y bloque `cuotas` arriba (SIRO, mora diaria). Login incluye nivel 5 en `login.niveles_ids`.
     */

    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
        ],
    ],

    'autogestion' => [
        /*
         | Menú de Alumnos — primario (nivel 2): solo boletín EPQ (portada + calificaciones)
         | y Gestión de Aranceles. Sin inicio, inasistencias, datos, ficha ni comunicados.
         | Gestión de Aranceles (`gestion_aranceles`): todos los niveles con acceso al Menú de Alumnos.
         */
        'menu_inicio' => [
            'niveles_deshabilitados' => [2],
        ],
        'actualizacion_datos' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
            'niveles_deshabilitados' => [2],
        ],
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
            'niveles_deshabilitados' => [2],
        ],
        'aranceles_escolares' => [
            'habilitado' => true,
            'implementacion' => 'gestion_aranceles',
            'menu_etiqueta' => 'Gestión de Aranceles',
            'boton_pagos' => [
                'url' => 'https://siropagos.bancoroela.com.ar',
            ],
        ],
        'comunicaciones' => [
            'niveles_deshabilitados' => [2],
        ],
        // Boletín (Prim) EPQ en autogestión familia (portada + calificaciones).
        'boletin_prim_epq' => [
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
