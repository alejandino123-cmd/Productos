<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu Código de Verificación para Iniciar Sesión',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<h3>Tu código de acceso es: <strong>{$this->code}</strong></h3><p>Ingresa este código de 6 dígitos en la aplicación para verificar tu identidad.</p>"
        );
    }
}