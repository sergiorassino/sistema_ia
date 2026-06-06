<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Comunicado escolar</title>
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background: #f3f4f6; color: #111827; }
    .wrapper { max-width: 540px; margin: 32px auto; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; }
    .header { background: #40848D; color: #fff; padding: 24px 28px; }
    .header h1 { margin: 0; font-size: 18px; font-weight: 600; }
    .header p  { margin: 4px 0 0; font-size: 13px; opacity: .85; }
    .body { padding: 24px 28px; }
    .meta { font-size: 12px; color: #6b7280; margin-bottom: 16px; display: flex; gap: 12px; flex-wrap: wrap; }
    .meta span { background: #f3f4f6; padding: 2px 8px; border-radius: 12px; }
    .contenido { font-size: 15px; line-height: 1.7; white-space: pre-wrap; background: #f9fafb; border-left: 3px solid #40848D; padding: 16px 20px; border-radius: 0 8px 8px 0; }
    .cta { text-align: center; margin-top: 28px; }
    .cta a { display: inline-block; background: #40848D; color: #fff; text-decoration: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; }
    .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 28px; font-size: 11px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ $nombreColegio !== '' ? $nombreColegio : 'Comunicado escolar' }}</h1>
        <p>{{ $mensaje->hilo?->asunto }}</p>
    </div>
    <div class="body">
        <div class="meta">
            <span>{{ \Carbon\Carbon::parse($mensaje->created_at)->locale('es')->isoFormat('D [de] MMMM YYYY, H:mm') }}</span>
            @if($mensaje->nombre_remitente_snapshot)
            <span>De: {{ $mensaje->nombre_remitente_snapshot }}</span>
            @endif
            @if($mensaje->vinculo_familiar)
            <span>{{ $mensaje->vinculoLabel() }}</span>
            @endif
        </div>

        <div class="contenido">{{ $mensaje->contenido }}</div>

        @if($mensaje->hilo && $mensaje->hilo->esComunicadoInformativoEscuela())
        <p style="margin: 20px 0 0; padding: 12px 14px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; font-size: 13px; color: #92400e;">
            Este mensaje es <strong>solo informativo</strong>: en el cuaderno de comunicados no podrá enviarse respuesta.
        </p>
        @endif

        <div class="cta">
            <a href="{{ route('alumnos.comunicaciones.abrir', ['id' => (int) $mensaje->id_hilo]) }}">
                Ver comunicado completo
            </a>
        </div>
    </div>
    <div class="footer">
        Este es un correo automático enviado desde el sistema de gestión escolar.<br>
        Por favor no responda directamente a este correo.
    </div>
</div>
</body>
</html>
