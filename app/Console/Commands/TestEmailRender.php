<?php

namespace App\Console\Commands;

use App\Services\Email\EmailRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericTemplateMailable;

class TestEmailRender extends Command
{
    protected $signature = 'app:email-test-render';
    protected $description = 'Test the email rendering engine';

    public function handle()
    {
        $renderer = new EmailRenderer();

        $this->info("Rendering email (Bedrock logic)...");

        try {
            $dto = $renderer->render('emails.test-template', ['name' => 'John'], 'Welcome!');

            dd($dto->content_html, $dto->content_text);

            $this->info("Subject: " . $dto->subject);
            $this->info("HTML Content length: " . strlen($dto->content_html));

            if (str_contains($dto->content_html, 'Hi John!')) {
                $this->info("Verification: Success! 'Hi John!' found in HTML.");

                $this->info("Simulating Courier sending via Log...");
                // Since Courier is not here, we use GenericTemplateMailable to send for local testing
                Mail::to('test@example.com')->send(new GenericTemplateMailable('emails.test-template', ['name' => 'John']));
                $this->info("Email sent! Check storage/logs/laravel.log");
            } else {
                $this->error("Verification: Failed! 'Hi John!' not found in HTML.");
                $this->line($dto->content_html);
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
    }
}
