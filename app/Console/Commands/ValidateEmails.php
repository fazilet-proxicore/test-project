<?php

namespace App\Console\Commands;

use App\Services\Email\EmailRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ValidateEmails extends Command
{
    protected $signature = 'mail:validate';
    protected $description = 'Validate that all email templates can be rendered without errors';

    public function handle(): int
    {
        $renderer = new EmailRenderer();
        $emailViewPath = resource_path('views/emails');

        if (!File::exists($emailViewPath)) {
            $this->error("Emails directory not found at $emailViewPath");
            return self::FAILURE;
        }

        $files = File::allFiles($emailViewPath);
        $successCount = 0;
        $failCount = 0;

        foreach ($files as $file) {
            $templateName = $this->getTemplateName($file);

            // Skip layouts if any
            if (str_contains($templateName, 'layouts.')) {
                continue;
            }

            $this->info("Validating: $templateName");

            try {
                // We use dummy data for validation
                // In a real scenario, we might want to provide specific test data for each template
                $renderer->renderView($templateName, ['name' => 'Validation Test'], 'Validation Subject');
                $this->info("  [OK] Rendered successfully.");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("  [FAIL] Error rendering $templateName: " . $e->getMessage());
                $failCount++;
            }
        }

        $this->line("");
        $this->info("Validation complete: $successCount successful, $failCount failed.");

        return $failCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function getTemplateName(\Symfony\Component\Finder\SplFileInfo $file): string
    {
        $relativePath = $file->getRelativePathname();
        $template = str_replace(['/', '.blade.php'], ['.', ''], $relativePath);
        return 'emails.' . $template;
    }

}
