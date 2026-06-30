<?php

return [
    'max_destinatarios_por_envio' => (int) env('EMAILS_MASIVOS_MAX_DESTINATARIOS', 500),
    'max_destinatarios_aviso' => (int) env('EMAILS_MASIVOS_MAX_AVISO', 400),
    'mail_bcc_chunk' => (int) env('EMAILS_MASIVOS_BCC_CHUNK', 100),
    'adjunto_nombre_max_chars' => 30,
    'attached_field_max_chars' => 150,
    'adjunto_max_mb' => 10,
    'adjuntos_max_count' => 5,
    'simulado' => (bool) env('EMAILS_MASIVOS_SIMULADO', false),
];
