<?php

/*
 | San Francisco de Asís — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=sanfranciscoasis en el despliegue de ese colegio.
 */

return [
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
