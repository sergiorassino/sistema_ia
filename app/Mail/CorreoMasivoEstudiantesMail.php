<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CorreoMasivoEstudiantesMail extends Mailable
{
    /**
     * @param  list<string>  $bccDestinatarios
     * @param  list<string>  $adjuntosAbsolutos
     */
    public function __construct(
        public readonly string $asunto,
        public readonly string $htmlCuerpo,
        public readonly string $emailRemitente,
        public readonly string $nombreRemitente,
        public readonly array $bccDestinatarios,
        public readonly array $adjuntosAbsolutos = [],
    ) {}

    public function envelope(): Envelope
    {
        $bcc = array_values(array_filter(array_map(
            static fn (string $e) => new Address($e),
            $this->bccDestinatarios,
        )));

        return new Envelope(
            from: new Address($this->emailRemitente, $this->nombreRemitente),
            bcc: $bcc,
            subject: $this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: '<br>' . $this->htmlCuerpo);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $out = [];
        foreach ($this->adjuntosAbsolutos as $path) {
            if (is_file($path)) {
                $out[] = Attachment::fromPath($path)->as(basename($path));
            }
        }

        return $out;
    }
}
