<?php
namespace App\Mail;
use App\Services\Email\EmailRenderInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;

class GenericTemplateMailable extends Mailable{
    //to render template and data and give an HTML
    public function __construct(
        public string $templateName,
        public array $templateData
    ) {}

    public function content(): Content
    {
        return new Content(
            view: $this->templateName,
            with: $this->templateData,
        );
    }
}
