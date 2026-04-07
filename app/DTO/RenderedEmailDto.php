<?php
namespace App\DTO;

    readonly class RenderedEmailDto{
    public function __construct(
        public string $subject,
        public string $content_html,
        public ?string $content_text=null,
        public array $headers=[],
    ) {}
    }
