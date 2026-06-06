<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InformeInasistenciasDocenteMail extends Mailable
{
    public function __construct(
        public readonly string $nombreDocente,
        public readonly string $nombreBimestre,
        public readonly string $nombreInstitucion,
        public readonly string $pdfBinario,
        public readonly string $nombreArchivoPdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Informe de Inasistencias - Bimestre '.$this->nombreBimestre,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Sr/a/ta: '.e($this->nombreDocente).'</p>'
                .'<p>Enviamos por este medio el resumen de Inasistencias del bimestre '
                .e($this->nombreBimestre).' (consulte el archivo adjunto).</p>'
                .'<p>Atentamente: '.e($this->nombreInstitucion).'</p>',
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
