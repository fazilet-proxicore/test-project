<?php

namespace App\Console\Commands;

use App\Services\Email\EmailRenderer;
use Illuminate\Console\Command;

class PreviewEmail extends Command
{
    protected $signature = 'app:email-preview {template}';
    protected $description = 'Render and preview an email template with test data';

    public function handle()
    {
        $template=$this->argument('template'); //emails.test-template
        $renderer=new EmailRenderer();
        //render with test data
        $dto = $renderer->render($template, ['name' => 'John Doe'], 'Test subject');

        $this->line($dto->content_html);
        $this->info("Email preview");
    }
}
