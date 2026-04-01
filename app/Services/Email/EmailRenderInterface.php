<?php

namespace App\Services\Email;

use App\DTO\RenderedEmailDto;

interface EmailRenderInterface
{
    public function render(string $template, array $data, string $subject): RenderedEmailDto;
}
