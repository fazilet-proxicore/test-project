<?php

namespace Support\JunieGuidelines;

use Illuminate\Support\Facades\File;
use RuntimeException;

class GuidelineMerger
{
    private array $warnings = [];

    public function __construct(
        private IncludeExpander $includeExpander,
        private PathResolver $pathResolver
    ) {}

    public function mergeFromLocalFile(string $localGuidelinesPath): string
    {
        $this->warnings = [];
        $this->includeExpander->resetWarnings();

        if (! File::exists($localGuidelinesPath)) {
            throw new RuntimeException("Local guidelines file could not be found: {$localGuidelinesPath}");
            // warning for fail-soft OR throw error
        }

        $localContent = File::get($localGuidelinesPath);

        if ($this->isBlank($localContent)) {
            $this->warnings[] = "Local guidelines file is empty: {$localGuidelinesPath}";

            return '';
        }

        $normalizedPath = $this->pathResolver->normalizePath($localGuidelinesPath);

        $merged = trim($this->includeExpander->expand(
            content: $localContent,
            relativeTo: dirname($localGuidelinesPath),
            visitedFiles: [$normalizedPath],
        ));

        $this->warnings = array_merge($this->warnings, $this->includeExpander->warnings());

        return $merged;
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function isBlank(string $content): bool
    {
        return trim($content) === '';
    }

}
