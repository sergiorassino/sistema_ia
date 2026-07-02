<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RecuperacionContrasenaMail extends Mailable
{
    public function __construct(
        public readonly string $nombreDestinatario,
        public readonly string $contrasena,
        public readonly string $portalEtiqueta,
        public readonly string $nombreInstitucion,
        public readonly string $fromAddress,
        public readonly string $fromName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: 'Recuperación de contraseña — '.$this->nombreInstitucion,
        );
    }

    public function content(): Content
    {
        $nombre = e($this->nombreDestinatario);
        $portal = e($this->portalEtiqueta);
        $institucion = e($this->nombreInstitucion);
        $contrasena = e($this->contrasena);

        $html = '<p>Hola '.($nombre !== '' ? $nombre : 'usuario').',</p>'
            .'<p>Recibimos una solicitud de recuperación de contraseña para el acceso de <strong>'.$portal.'</strong> de <strong>'.$institucion.'</strong>.</p>'
            .'<p>Su contraseña registrada en el sistema es:</p>'
            .'<p style="font-size:1.15em;font-weight:bold;letter-spacing:0.04em;">'.$contrasena.'</p>'
            .'<p>Por seguridad, le recomendamos cambiarla después de ingresar al portal.</p>'
            .'<hr style="border:none;border-top:1px solid #C1D7DA;margin:1.5em 0;">'
            .'<p style="font-size:0.85em;color:#555;">'
            .'<strong>NO RESPONDA A ESTE CORREO.</strong> '
            .'Este mensaje fue generado automáticamente por Sistemas Escolares. '
            .'La casilla de envío no está monitoreada. '
            .'Para consultas o cambios de clave, contacte a la secretaría de su institución.'
            .'</p>';

        return new Content(htmlString: $html);
    }
}
