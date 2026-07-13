<?php

/*
 | CSC — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=csc en el despliegue de ese colegio.
 | Autogestión familia: solo Gestión de Aranceles (UI sanfranciscoasis).
 */

return [
    'cuotas' => [
        'facturacion_afip' => [
            'habilitado' => true,
            'modo' => 'devengamiento',
            // Certificados WSAA/WSFE: carpeta y nombres de archivo en Parámetros del sistema (ento).
            'cbte_tipo' => 11,
            'nota_credito_tipo' => 12,
            'cbte_tipo_asociado' => 11,
            'produccion' => true,
            // Prueba local / homologación sin llamar a AFIP (CAE simulado). Para emisión real: simular => false.
            'simular' => true,
        ],
    ],

    'autogestion' => [
        /*
         | Menú de Alumnos — solo Gestión de Aranceles (misma UI que San Francisco de Asís).
         | Sin inicio, datos, ficha, calificaciones, inasistencias, horario ni comunicados.
         */
        'menu_inicio' => [
            'habilitado' => false,
        ],
        'actualizacion_datos' => [
            'habilitado' => false,
        ],
        'ficha_matricula' => [
            'habilitado' => false,
        ],
        'aranceles_escolares' => [
            'habilitado' => true,
            'implementacion' => 'sanfranciscoasis',
            'menu_etiqueta' => 'Gestión de Aranceles',
        ],
        'horario_clase' => [
            'habilitado' => false,
        ],
        'consulta_calificaciones' => [
            'habilitado' => false,
        ],
        'informe_inasistencias' => [
            'habilitado' => false,
        ],
        'comunicaciones' => [
            'habilitado' => false,
        ],
    ],
];
