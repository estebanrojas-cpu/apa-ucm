<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CredencialesAcademicoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $nombreAcademico,
        public readonly string $emailAcceso,
        public readonly string $passwordInicial,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tus credenciales de acceso — Sistema de Calificación Académica UCM',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credenciales_academico',
        );
    }
}
