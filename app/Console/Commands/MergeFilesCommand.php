<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MergeFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:merge-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extracts @includeFile directives and merges content from linked files.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $filePath = base_path('.github/.junie/guidelines_local.md');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return;
        }

        $content = file_get_contents($filePath);
        $directory = dirname($filePath);

        $mergedContent = preg_replace_callback(
            "/@includeFile\(['\"](.*?)['\"]\)/",
            function ($matches) use ($directory) {
                $includeFileName = $matches[1];
                $includePath = $directory.DIRECTORY_SEPARATOR.$includeFileName;

                if (file_exists($includePath)) {
                    return file_get_contents($includePath);
                }

                $this->warn("Included file not found: {$includePath}");

                return $matches[0]; // Keep original if file not found
            },
            $content
        );

        $this->line($mergedContent);
    }
}
