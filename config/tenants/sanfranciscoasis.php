<?php

/*
 | San Francisco de Asís — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=sanfranciscoasis en el despliegue de ese colegio.
 */

return [
    'cuotas' => [
        // % de mora en tramos 2–4: tasa diaria × días (default del sistema).
        'interes_mora_modo' => 'diario',

        'siro' => [
            'habilitado' => true,
            'qr_url' => null,
            // Prefijo de 2 dígitos del CPE (legacy Scriptcase / SIRO). San Fra usa 09.
            'cpe_prefijo' => '09',
            'descarga_rendicion' => [
                // Solo Roela al crear planilla de rendición (cuotastipopago).
                'canales_planilla' => ['Roela'],
            ],
        ],

        'facturacion_afip' => [
            'habilitado' => true,
            // Certificados WSAA/WSFE: carpeta y nombres de archivo en Parámetros del sistema (ento).
            'cbte_tipo' => 15,
            'produccion' => true,
            'simular' => true,
        ],
    ],

    'portal_docente' => [
        'menu' => [
            'inicial' => [
                'recursos_didacticos_nueva_reserva' => true,
                'recursos_didacticos_listado' => true,
            ],
            'primario' => [
                'recursos_didacticos_nueva_reserva' => true,
                'recursos_didacticos_listado' => true,
            ],
            'secundario' => [
                'recursos_didacticos_nueva_reserva' => true,
                'recursos_didacticos_listado' => true,
            ],
        ],
    ],

    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
        ],
    ],

    'autogestion' => [
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
            'implementacion' => 'sanfranciscoasis',
            'debito_automatico' => [
                'banner' => 'img/tenants/sanfranciscoasis/debito-automatico-banner.png',
                'formulario_pdf' => 'tenants/sanfranciscoasis/formulario-adhesion-debito-automatico.pdf',
            ],
            'medios_pago' => [
                'banner' => 'img/tenants/sanfranciscoasis/medios-pago-banner.png',
                'url' => 'https://sanfranciscoasis.edu.ar/administracion2/',
            ],
        ],
    ],
];
