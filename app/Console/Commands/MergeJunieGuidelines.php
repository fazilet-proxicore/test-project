<?php

namespace App\Console\Commands;

use App\Application\Junie\GuidelineMerger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MergeJunieGuidelines extends Command
{
    protected $signature = 'app:merge-junie-guidelines
                            {--local=.junie/guidelines_local.md}
                            {--output=.junie/guidelines.md}';

    protected $description = 'Build merged Junie guidelines from local file and included base/service files';

    public function __construct(
        protected GuidelineMerger $guidelineMerger
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $localGuidelinesPath = base_path($this->option('local'));
        $outputGuidelinesPath = base_path($this->option('output'));
        try {
            $mergedContent = $this->guidelineMerger->mergeFromLocalFile($localGuidelinesPath);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        foreach ($this->guidelineMerger->warnings() as $warning) {
            $this->warn($warning);
        }
        File::ensureDirectoryExists(dirname($outputGuidelinesPath));
        File::put($outputGuidelinesPath, trim($mergedContent).PHP_EOL);

        $this->info("Junie guidelines generated: {$outputGuidelinesPath}");

        return self::SUCCESS;
    }
}
