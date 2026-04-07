<?php

namespace App\Integrations\Courier;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourierDeliveredMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $subject,
        public $html
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->html,
        );
    }
}
