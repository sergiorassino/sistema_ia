<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComMensajeEnvio extends Model
{
    protected $table = 'com_mensajes_envios';
    public $timestamps = false;

    protected $fillable = [
        'id_mensaje_destinatario', 'medio', 'estado',
        'motivo', 'proveedor_msgid', 'enviado_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function destinatario()
    {
        return $this->belongsTo(ComMensajeDestinatario::class, 'id_mensaje_destinatario');
    }

    public function iconoMedio(): string
    {
        return match($this->medio) {
            'push'      => '🔔',
            'email'     => '✉',
            'whatsapp'  => '💬',
            default     => '?',
        };
    }

    /**
     * WhatsApp vía driver wa_link: se registra "enviado" al generar el enlace (wa.me o web.whatsapp.com), no al entregar el mensaje en WhatsApp.
     */
    public static function esWhatsappEnvioManualWaMe(string $medio, string $estado, mixed $proveedorMsgId): bool
    {
        $url = is_string($proveedorMsgId) ? $proveedorMsgId : '';

        return $medio === 'whatsapp'
            && $estado === 'enviado'
            && $url !== ''
            && (str_starts_with($url, 'https://wa.me/')
                || str_starts_with($url, 'https://web.whatsapp.com/send'));
    }

    /** Enlace guardado (wa.me o web.whatsapp.com) para abrir en UI con window.open / pestaña reutilizada. */
    public function tieneEnlaceWhatsappManualAbrible(): bool
    {
        return static::esWhatsappEnvioManualWaMe((string) $this->medio, (string) $this->estado, $this->proveedor_msgid);
    }

    public function estadoLabel(): string
    {
        if (static::esWhatsappEnvioManualWaMe((string) $this->medio, (string) $this->estado, $this->proveedor_msgid)) {
            return '(Envío manual)';
        }

        return match($this->estado) {
            'enviado'      => 'Enviado',
            'fallido'      => 'Fallido',
            'pendiente'    => 'Pendiente',
            'no_aplicable' => 'No disponible',
            default        => ucfirst((string) $this->estado),
        };
    }
}
