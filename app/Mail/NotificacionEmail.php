<?php

namespace App\Mail;

use App\Models\Notificacion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Envía una notificación ya renderizada (asunto y HTML guardados en la cola).
 */
class NotificacionEmail extends Mailable
{
    public function __construct(
        public Notificacion $notificacion,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notificacion->asunto);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->notificacion->cuerpo_html);
    }
}
