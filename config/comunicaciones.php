<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Adaptador de WhatsApp
    |--------------------------------------------------------------------------
    | 'wa_link'   — Genera enlaces manuales (por defecto WhatsApp Web en navegador; configurable).
    | 'meta_cloud' — Meta Cloud API (requiere configurar token, phone_id).
    */
    'whatsapp_driver' => env('COM_WHATSAPP_DRIVER', 'wa_link'),

    /*
    | Contexto de navegación para enlaces wa.me (envío manual).
    | Un nombre fijo distinto de _blank hace que el navegador reutilice la misma pestaña
    | al hacer clic en otro enlace desde el sistema. Opcional: COM_WHATSAPP_WA_LINK_TARGET en .env
    |
    | Importante: en esos <a> no usar rel="noopener" ni "noreferrer": el estándar hace que, con
    | noopener, cualquier target con nombre se trate como _blank y se abra pestaña nueva cada vez.
    */
    'whatsapp_wa_link_target' => env('COM_WHATSAPP_WA_LINK_TARGET', 'comunicaciones_whatsapp_wa'),

    /*
    | Formato del enlace manual (driver wa_link):
    | - web — https://web.whatsapp.com/send?... (se abre en el navegador; con target con nombre se reutiliza la pestaña;
    |         en Windows evita que wa.me dispare la app de escritorio en cada clic).
    | - wa_me — https://wa.me/... (universal; suele abrir la app instalada en PC/móvil).
    */
    'whatsapp_manual_link_style' => env('COM_WHATSAPP_MANUAL_LINK_STYLE', 'web'),

    'meta_cloud' => [
        'token'    => env('META_WA_TOKEN', ''),
        'phone_id' => env('META_WA_PHONE_ID', ''),
        'version'  => env('META_WA_VERSION', 'v19.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Límites de contenido
    |--------------------------------------------------------------------------
    */
    'max_asunto'     => 200,
    'max_contenido'  => 2000,
    'max_push_chars' => 280,

    /*
    |--------------------------------------------------------------------------
    | Rate limit por usuario: máximo de envíos por ventana de tiempo
    |--------------------------------------------------------------------------
    */
    'rate_limit_max'    => 20,
    'rate_limit_decay'  => 60, // segundos

    /*
    |--------------------------------------------------------------------------
    | Distribución (push / mail / WhatsApp) en la misma petición HTTP
    |--------------------------------------------------------------------------
    | Muchos destinatarios con correo síncrono superan con facilidad max_execution_time
    | (p. ej. 30 s en PHP). Se amplía el límite solo durante Distribuidor::distribuir().
    | 0 = no modificar el límite configurado en PHP.
    */
    'distribuir_max_seconds' => (int) env('COM_DISTRIBUIR_MAX_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Correo en cola (recomendado con muchos destinatarios)
    |--------------------------------------------------------------------------
    | Si es true y QUEUE_CONNECTION no es "sync", se encola un solo trabajo
    | EnviarComunicadoMailLoteJob por envío masivo (requiere worker: php artisan queue:work).
    | Con QUEUE_CONNECTION=sync no se usa cola: sigue siendo síncrono y aplica distribuir_max_seconds.
    */
    'queue_mail' => (bool) env('COM_QUEUE_MAIL', false),

    /*
    |--------------------------------------------------------------------------
    | Un solo envío SMTP por fragmento (BCC) cuando el cuerpo es el mismo
    |--------------------------------------------------------------------------
    | true: agrupa destinatarios en Mail::bcc() por lotes (ver chunk). false: un SMTP por destinatario.
    */
    'mail_agrupar_bcc' => (bool) env('COM_MAIL_AGRUPAR_BCC', true),

    /*
    | Máximo de destinatarios por mensaje BCC (fragmentación si el servidor limita destinatarios).
    */
    'mail_bcc_chunk_destinatarios' => (int) env('COM_MAIL_BCC_CHUNK', 50),
];
