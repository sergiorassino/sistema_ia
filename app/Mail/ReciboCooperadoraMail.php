<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReciboCooperadoraMail extends Mailable
{
    public function __construct(
        public readonly string $nombrePagador,
        public readonly string $numeroReciboTexto,
        public readonly string $fechaTexto,
        public readonly string $nombreInstitucion,
        public readonly string $pdfBinario,
        public readonly string $nombreArchivoPdf,
        public readonly string $asunto,
        public readonly string $fromAddress,
        public readonly string $fromName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: $this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Sr/a: '.e($this->nombrePagador).'</p>'
                .'<p>Adjuntamos el recibo Nº '.e($this->numeroReciboTexto)
                .' con fecha '.e($this->fechaTexto).'.</p>'
                .'<p>Atentamente,<br>'.e($this->nombreInstitucion).'</p>',
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinario, $this->nombreArchivoPdf)
                ->withMime('application/pdf'),
        ];
    }
}
