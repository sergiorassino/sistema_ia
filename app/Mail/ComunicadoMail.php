<?php

namespace App\Mail;

use App\Support\Comunicaciones\ComunicacionAdjuntoStorage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;
use App\Models\ComMensaje;
use App\Models\ComMensajeDestinatario;

class ComunicadoMail extends Mailable
{
    public function __construct(
        public readonly ComMensaje $mensaje,
        public readonly ?ComMensajeDestinatario $destinatario = null,
        public readonly string $nombreColegio = '',
    ) {}

    public function envelope(): Envelope
    {
        $asunto = $this->mensaje->hilo?->asunto ?? 'Comunicado escolar';
        $prefijo = $this->nombreColegio !== '' ? "[{$this->nombreColegio}] " : '';

        return new Envelope(subject: $prefijo . $asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'comunicaciones::emails.comunicaciones.comunicado');
    }

    /**
     * Adjunta el archivo del mensaje al correo si existe en disco.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (! $this->mensaje->tieneAdjunto()) {
            return [];
        }

        $ruta = (string) $this->mensaje->adjunto_ruta;

        if (ComunicacionAdjuntoStorage::ruta($ruta) === null) {
            return [];
        }

        $disk     = Storage::disk(ComunicacionAdjuntoStorage::DISK);
        $nombre   = (string) ($this->mensaje->adjunto_nombre ?? basename($ruta));
        $mimeType = (string) ($this->mensaje->adjunto_mime ?? 'application/octet-stream');

        return [
            Attachment::fromStorageDisk(ComunicacionAdjuntoStorage::DISK, $ruta)
                ->as($nombre)
                ->withMime($mimeType),
        ];
    }
}
