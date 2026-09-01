<?php

/*
 | IESS — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=iess en el despliegue de ese colegio.
 |
 | Foto carnet: solo Menú de Secretaría (ABM de legajos). La visibilidad
 | la da la solapa `foto_carnet` + campo `campos_legajo.fotoCarnet` en la BD
 | (migración 2026_09_01_120000_seed_solapa_foto_carnet_iess; SQL equivalente
 | database/sql/campos_legajo_foto_carnet_solapa_idempotente.sql).
 | Autogestión familia queda off (default
 | autogestion.actualizacion_datos.foto_carnet = false). No activar esa llave.
 */

return [
    'boletin' => [
        'mostrar_tercer_materia' => true,
    ],

    'portal_docente' => [
        'menu' => [
            'secundario' => [
                'cuaderno_seguimiento_aulico' => true,
            ],
        ],
    ],

    'secretaria' => [
        'ficha_matricula' => [
            'habilitado' => true,
            'implementacion' => 'iess',
        ],
    ],
];
