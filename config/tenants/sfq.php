<?php

/*
 | SFQ — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=sfq en el despliegue de ese colegio.
 |
 | Perfil operativo alineado con EPQ (cuotas SIRO, autogestión con documentos, ficha de matrícula,
 | recursos didácticos en Menú de Docentes). Niveles: inicial (1), primario (2), administración (5).
 | Sin secundario (3).
 |
 | Primario: misma variante `epq` que Escuelas Pías Quimilí.
 | Inicial: variantes propias `sfq` (módulos en desarrollo — ver `calificaciones_inicial.*`).
 */

return [
    'nombre' => 'SFQ',

    'institucional' => [
        'logo_forma' => 'emblema',
    ],

    'boletin_primario' => [
        'menu_etiqueta_boletin_ipe' => 'Boletín (Prim)',
        'epq_membrete_portada' => 'img/tenants/sfq/boletin-prim-membrete.png',
    ],

    'calificaciones_primario' => [
        'carga_estudiante' => ['implementacion' => 'epq'],
        'boletin_prim' => ['implementacion' => 'epq'],
        'planilla' => ['implementacion' => 'epq'],
    ],

    /*
     | Calificaciones inicial — variante `sfq` (en desarrollo).
     | Activar menús docente/secretaría al registrar Livewire en CalificacionesInicialModulos.
     */
    'boletin_inicial' => [
        'membrete' => 'img/tenants/sfq/boletin-inic-membrete.png',
        'titulo_institucion' => 'E.P. SAN FRANCISCO',
    ],

    'calificaciones_inicial' => [
        'carga_notas' => ['implementacion' => 'sfq'],
        'indicadores' => ['implementacion' => 'sfq'],
        'observaciones' => ['implementacion' => 'sfq'],
        'observaciones_materia' => ['implementacion' => 'sfq'],
        'informe_progreso' => ['implementacion' => 'sfq'],
        'boletin' => ['implementacion' => 'sfq'],
    ],

    'login' => [
        'niveles_ids' => [1, 2, 5],
    ],

    'cuotas' => [
        'interes_mora_modo' => 'total',

        'comprobante_pago' => [
            'implementacion' => 'epq',
        ],

        'siro' => [
            'habilitado' => true,
            'qr_url' => null,
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
                'carga_notas' => true,
                'boletin' => true,
                // Calificaciones inicial estándar (Montecristo): desactivado en SFQ.
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
        ],
    ],

    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
        ],
        'consulta_calificaciones' => [
            'habilitado' => false,
        ],
        // Menú estándar inicial (Montecristo) oculto; se usa variante SFQ.
        'calificaciones_inicial' => [
            'habilitado' => false,
        ],
        'calificaciones_inicial_sfq' => [
            'habilitado' => true,
        ],
    ],

    'autogestion' => [
        /*
         | Menú de Alumnos — primario (nivel 2): boletín EPQ, Gestión de Aranceles e Inicio.
         | Inicial (nivel 1): aranceles e inicio; boletín/informe inicial SFQ cuando exista la variante.
         */
        'actualizacion_datos' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
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
        'comunicaciones' => [
            'niveles_deshabilitados' => [2],
        ],
        'boletin_prim_epq' => [
            'habilitado' => true,
        ],
        'consulta_calificaciones' => [
            'habilitado' => false,
        ],
        'informe_inasistencias' => [
            'niveles_deshabilitados' => [2],
        ],
    ],
];
