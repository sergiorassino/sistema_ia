<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificacionDeudaMail extends Mailable
{
    /**
     * @param  list<array<string, string>>  $filas
     * @param  array<string, string>  $totales
     */
    public function __construct(
        public readonly string $nombreColegio,
        public readonly string $localidad,
        public readonly string $fechaCarta,
        public readonly string $familiaLinea,
        public readonly string $tituloFamilia,
        public readonly string $textoInicial,
        public readonly string $textoFinal,
        public readonly array $filas,
        public readonly array $totales,
        public readonly string $fromAddress,
        public readonly string $fromName,
    ) {}

    public function envelope(): Envelope
    {
        $prefijo = $this->nombreColegio !== '' ? '['.$this->nombreColegio.'] ' : '';

        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName !== '' ? $this->fromName : $this->fromAddress),
            subject: $prefijo.'Notificación de deuda',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.mora.notificacion-deuda');
    }
}
