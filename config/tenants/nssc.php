<?php

/*
 | Colegio NSSC — personalización declarada en repo (no en .env).
 |
 | Requiere TENANT_SLUG=nssc en el despliegue de ese colegio.
 */

return [
    'programas_examen' => [
        'habilitado' => true,
        // Sin base_url: las URLs usan {APP_URL}/archivos (igual que Montecristo).
        // Solo definir base_url si los PDF se sirven desde otro dominio.
        'anios' => [2026, 2025, 2024, 2023, 2022, 2021, 2020, 2019],
    ],

    'doc_pp' => [
        'habilitado' => true,
    ],
];
