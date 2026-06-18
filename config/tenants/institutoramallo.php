<?php

/*
 | Instituto Ramallo — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=institutoramallo en el despliegue de ese colegio.
 */

return [
    'cuotas' => [
        // Los % de interés en 2.º/3.er venc. y post-venc. son totales del tramo, no diarios.
        'interes_mora_modo' => 'total',

        'facturacion_afip' => [
            'habilitado' => true,
            'cert_usuario_id' => '1',
            'cert_key' => 'privada_prod.key',
            'cert_crt' => 'Sistemalaboratorio_2bbae7f592f630ba.crt',
            'cbte_tipo' => 15,
            'produccion' => true,
            // Por ahora no envía a AFIP: genera comprobante simulado en BD.
            'simular' => true,
        ],
    ],
];
