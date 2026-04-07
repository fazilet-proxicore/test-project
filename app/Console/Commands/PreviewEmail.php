<?php

namespace App\Console\Commands;

use App\Services\Email\EmailRenderer;
use Illuminate\Console\Command;

class PreviewEmail extends Command
{
    protected $signature = 'mail:preview {template}';
    protected $description = 'Render and preview an email template with test data';

    public function handle(): int
    {
        $template=$this->argument('template'); //emails.test-template
        $renderer=new EmailRenderer();
        //render with test data
        $dto = $renderer->renderView($template, ['name' => 'Fazilet'], 'Test subject');

        $this->line($dto->content_html);
        $this->info("Email preview");

        return self::SUCCESS;
    }
}
