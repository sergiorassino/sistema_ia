<?php

namespace App\Comunicaciones\Adapters;

use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;
use App\Models\ComMensajeEnvio;
use App\Comunicaciones\Whatsapp\WaLinkAdapter;

class WhatsappAdapter
{
    public static function enviar(
        ComMensajeDestinatario $destinatario,
        ComMensaje $mensaje
    ): ComMensajeEnvio {
        $driver = config('comunicaciones.whatsapp_driver', 'wa_link');

        $resultado = match ($driver) {
            'wa_link' => WaLinkAdapter::generar($destinatario, $mensaje),
            default   => ['estado' => 'no_aplicable', 'motivo' => "Driver '{$driver}' no implementado.", 'link' => null],
        };

        return ComMensajeEnvio::create([
            'id_mensaje_destinatario' => $destinatario->id,
            'medio'                   => 'whatsapp',
            'estado'                  => $resultado['estado'],
            'motivo'                  => $resultado['motivo'],
            'proveedor_msgid'         => $resultado['link'] ?? null,
            'enviado_at'              => $resultado['estado'] === 'enviado' ? now() : null,
        ]);
    }
}
