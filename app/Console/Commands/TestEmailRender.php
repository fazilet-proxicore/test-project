<?php

namespace App\Console\Commands;

use App\Services\Email\EmailRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericTemplateMailable;
use App\Integrations\Courier\CourierMessageFactory;
use App\Integrations\Courier\CourierClient;

class TestEmailRender extends Command
{
    protected $signature = 'mail:test-render';
    protected $description = 'Test the email rendering engine and Courier integration';

    public function handle() :int
    {
        $renderer = new EmailRenderer();
        $courierMessageFactory = new CourierMessageFactory();
        $courierClient = new CourierClient();

        $this->info("Rendering email (Bedrock logic)...");

        try {
            $dto = $renderer->renderView(
                view: 'emails.test-template',
                data: ['name' => 'Fazilet'],
                subject: 'Welcome!'
            );

            $this->info("Subject: " . $dto->subject);
            $this->info("HTML Content length: " . strlen($dto->content_html));

            if (str_contains($dto->content_html, 'Hi Fazilet!')) {
                $this->info("Verification: Success! 'Hi Fazilet!' found in HTML.");

                $this->info("Preparing Courier payload...");
                $payload = $courierMessageFactory->make(
                    tenantId: 'tenant-1',
                    idempotencyKey: 'welcome-email-customer-123',
                    receivers: ['fazilet@proxicore.com'],
                    email: $dto
                );

                $this->info("Sending payload to Courier...");
                $courierClient->send($payload);

                $this->info("Courier Client: Success! Check logs for payload.");

                // Still send for Herd/SMTP simulation to see result in UI
                $this->info("Simulating delivery for Herd UI...");
                Mail::to('fazilet@proxicore.com')->send(new GenericTemplateMailable('emails.test-template', ['name' => 'Fazilet']));
                $this->info("Email sent! Check Herd Mail (Port 2525)");
            } else {
                $this->error("Verification: Failed! 'Hi Fazilet!' not found in HTML.");
                $this->line($dto->content_html);
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }
        return self::SUCCESS;
    }
}
