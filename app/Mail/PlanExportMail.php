<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PlanExportMail extends Mailable
{
    public function __construct(
        public array $summary, public string $planUrl, public string $xlsxUrl,
        public string $layerUrl, public ?string $unsubscribeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre plan de détection ATT&CK Radar');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.plan', text: 'mail.plan-text');
    }
}
