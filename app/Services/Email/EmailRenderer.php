<?php
namespace App\Services\Email;

use App\DTO\RenderedEmailDto;
use App\Mail\GenericTemplateMailable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;

class EmailRenderer implements EmailRendererInterface
{
    public function renderMailable(Mailable $mailable, ?string $locale = null): RenderedEmailDto
    {
        return $this->withLocale($locale, function () use ($mailable) {
            $subject = method_exists($mailable, 'envelope')
                ? ($mailable->envelope()->subject ?? '')
                : '';

            $contentHtml = $mailable->render();

            return new RenderedEmailDto(
                subject: (string) $subject,
                content_html: $contentHtml,
                content_text: strip_tags($contentHtml),
                headers: [],
            );
        });
    }

    public function renderView(
        string $view,
        array $data = [],
        string $subject = '',
        ?string $locale = null
    ): RenderedEmailDto {
        return $this->withLocale($locale, function () use ($view, $data, $subject) {
            $contentHtml = view($view, $data)->render();

            return new RenderedEmailDto(
                subject: $subject,
                content_html: $contentHtml,
                content_text: strip_tags($contentHtml),
                headers: [],
            );
        });
    }

    private function withLocale(?string $locale, callable $callback): mixed
    {
        $originalLocale = App::getLocale();

        if ($locale) {
            App::setLocale($locale);
        }

        try {
            return $callback();
        } finally {
            App::setLocale($originalLocale);
        }
    }
}
