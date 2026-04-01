<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\File;

trait InteractsWithEmailSnapshots
{
    /**
     * Assert that the given HTML matches the snapshot for the given template.
     * If the snapshot does not exist, it will be created.
     */
    protected function assertHtmlMatchesSnapshot(string $snapshotName, string $actualHtml): void
    {
        $snapshotPath = base_path("tests/Snapshots/Emails/{$snapshotName}.html");

        if (!File::exists(dirname($snapshotPath))) {
            File::makeDirectory(dirname($snapshotPath), 0755, true);
        }

        if (!File::exists($snapshotPath)) {
            File::put($snapshotPath, $actualHtml);
            $this->markTestIncomplete("Snapshot [{$snapshotName}] created. Please review it and run the test again.");
        }

        $expectedHtml = File::get($snapshotPath);

        // Normalize line endings and trim to avoid whitespace-only differences
        $expectedHtml = $this->normalizeHtml($expectedHtml);
        $actualHtml = $this->normalizeHtml($actualHtml);

        $this->assertEquals($expectedHtml, $actualHtml, "HTML does not match snapshot [{$snapshotName}].");
    }

    private function normalizeHtml(string $html): string
    {
        // Replace all line endings with \n
        $html = str_replace(["\r\n", "\r"], "\n", $html);

        // Trim each line
        $lines = explode("\n", $html);
        $lines = array_map('trim', $lines);

        return implode("\n", array_filter($lines));
    }
}
