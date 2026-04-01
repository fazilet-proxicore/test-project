<?php
namespace App\Services\Email;

use App\DTO\RenderedEmailDto;
use App\Mail\GenericTemplateMailable;

class EmailRenderer implements EmailRenderInterface
{
    public function render(string $template, array $data, string $subject): RenderedEmailDto
    {
        $mailable = new GenericTemplateMailable($template, $data);
        $html = $mailable->render();

        return new RenderedEmailDto(
            subject: $subject,
            content_html: $html,
            content_text: strip_tags($html),
            headers: ''
        );
    }
}
