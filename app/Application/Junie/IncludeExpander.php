<?php

namespace App\Application\Junie;

use Illuminate\Support\Facades\File;

class IncludeExpander
{
    private const INCLUDE_PATTERN = '/\{\$includeFile=(.+?)\$\}/';

    /**
     * @var array<int, string>
     */
    private array $warnings = [];

    public function __construct(
        private PathResolver $pathResolver
    ) {
    }

    public function expand(string $content, string $relativeTo, array $visitedFiles = []): string
    {
        return preg_replace_callback(
            self::INCLUDE_PATTERN,
            function (array $matches) use ($relativeTo, $visitedFiles) {
                $includePath = trim($matches[1]);

                if ($this->pathResolver->isWildcardPath($includePath)) {
                    return $this->expandWildcardInclude(
                        includePath: $includePath,
                        relativeTo: $relativeTo,
                        visitedFiles: $visitedFiles,
                    );
                }

                return $this->expandSingleInclude(
                    includePath: $includePath,
                    relativeTo: $relativeTo,
                    visitedFiles: $visitedFiles,
                );
            },
            $content
        );
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function resetWarnings(): void
    {
        $this->warnings = [];
    }

    private function expandSingleInclude(
        string $includePath,
        string $relativeTo,
        array $visitedFiles = []
    ): string {
        $filePath = $this->pathResolver->resolvePath($includePath, $relativeTo);

        return $this->loadAndExpandFile(
            filePath: $filePath,
            visitedFiles: $visitedFiles,
        );
    }

    private function expandWildcardInclude(
        string $includePath,
        string $relativeTo,
        array $visitedFiles = []
    ): string {
        $globPath = $this->pathResolver->resolveGlobPath($includePath, $relativeTo);
        $files = File::glob($globPath);

        if (empty($files)) {
            $this->addWarning("No files matched wildcard include: {$globPath}");

            return '';
        }

        sort($files);

        $contents = [];

        foreach ($files as $file) {
            $expanded = $this->loadAndExpandFile(
                filePath: $file,
                visitedFiles: $visitedFiles,
            );

            if (! $this->isBlank($expanded)) {
                $contents[] = trim($expanded);
            }
        }

        return implode(PHP_EOL . PHP_EOL, $contents);
    }

    private function loadAndExpandFile(
        string $filePath,
        array $visitedFiles = []
    ): string {
        if (! File::exists($filePath)) {
            $this->addWarning("Included file not found: {$filePath}");

            return '';
        }

        $normalizedPath = $this->pathResolver->normalizePath($filePath);

        if (in_array($normalizedPath, $visitedFiles, true)) {
            $this->addWarning("Circular include skipped: {$normalizedPath}");

            return '';
        }

        $content = File::get($filePath);

        if ($this->isBlank($content)) {
            $this->addWarning("Included file is empty: {$filePath}");

            return '';
        }

        return trim($this->expand(
            content: $content,
            relativeTo: dirname($filePath),
            visitedFiles: [...$visitedFiles, $normalizedPath],
        ));
    }

    private function isBlank(string $content): bool
    {
        return trim($content) === '';
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }
}
