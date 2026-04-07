<?php

namespace App\Services\Email;

use App\DTO\RenderedEmailDto;
use Illuminate\Mail\Mailable;

interface EmailRendererInterface
{
    public function renderMailable(Mailable $mailable, ?string $locale = null): RenderedEmailDto;

    public function renderView(
        string $view,
        array $data = [],
        string $subject = '',
        ?string $locale = null
    ): RenderedEmailDto;
}
