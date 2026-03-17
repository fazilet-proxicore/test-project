<?php

namespace App\Application\Junie;

use Illuminate\Support\Facades\File;
use RuntimeException;

class GuidelineMerger
{    private array $warnings = [];

    public function __construct(
        private IncludeExpander $includeExpander,
        private PathResolver $pathResolver
    ) {
    }

    public function mergeFromLocalFile(string $localGuidelinesPath): string
    {
        $this->warnings = [];
        $this->includeExpander->resetWarnings();

        if (! File::exists($localGuidelinesPath)) {
            throw new RuntimeException("Local guidelines file could not be found: {$localGuidelinesPath}");
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

//    /**
//     * @var array<int, string>
//     */
//    protected array $warnings = [];
//
//    public function mergeFromLocalFile(string $localGuidelinesPath): string
//    {
//        if (! File::exists($localGuidelinesPath)) {
//            throw new RuntimeException("Local guidelines file could not be found: {$localGuidelinesPath}");
//        }
//        $localContent = File::get($localGuidelinesPath);
//
//        if (trim($localContent) === '') {
//            $this->addWarning("Local guidelines file is empty: {$localGuidelinesPath}");
//
//            return '';
//        }
//
//        $visitedFiles = [
//            realpath($localGuidelinesPath) ?: $localGuidelinesPath,
//        ];
//
//        return trim($this->expandIncludes(
//            content: $localContent,
//            relativeTo: dirname($localGuidelinesPath),
//            visitedFiles: $visitedFiles,
//        ));
//    }
//
//    /**
//     * @return array<int, string>
//     */
//    public function warnings(): array
//    {
//        return $this->warnings;
//    }
//
//    protected function expandIncludes(
//        string $content,
//        string $relativeTo,
//        array $visitedFiles = []
//    ): string {
//        $pattern = '/\{\$includeFile=(.+?)\$\}/';
//
//        return preg_replace_callback(
//            $pattern,
//            function (array $matches) use ($relativeTo, $visitedFiles) {
//                $includePath = trim($matches[1]);
//
//                if (str_contains($includePath, '*')) {
//                    return $this->expandWildcardInclude(
//                        includePath: $includePath,
//                        relativeTo: $relativeTo,
//                        visitedFiles: $visitedFiles,
//                    );
//                }
//
//                return $this->expandSingleInclude(
//                    includePath: $includePath,
//                    relativeTo: $relativeTo,
//                    visitedFiles: $visitedFiles,
//                );
//            },
//            $content
//        );
//    }
//
//    protected function expandSingleInclude(
//        string $includePath,
//        string $relativeTo,
//        array $visitedFiles = []
//    ): string {
//        $filePath = $this->resolvePath($includePath, $relativeTo);
//
//        return $this->expandResolvedFile(
//            filePath: $filePath,
//            visitedFiles: $visitedFiles,
//        );
//    }
//
//    protected function expandWildcardInclude(
//        string $includePath,
//        string $relativeTo,
//        array $visitedFiles = []
//    ): string {
//        $globPath = $this->resolveGlobPath($includePath, $relativeTo);
//        $files = File::glob($globPath);
//
//        if (empty($files)) {
//            $this->addWarning("No files matched wildcard include: {$globPath}");
//
//            return '';
//        }
//
//        sort($files);
//
//        $result = [];
//
//        foreach ($files as $file) {
//            $expandedContent = $this->expandResolvedFile(
//                filePath: $file,
//                visitedFiles: $visitedFiles,
//            );
//
//            if (trim($expandedContent) !== '') {
//                $result[] = $expandedContent;
//            }
//        }
//
//        return trim(implode(PHP_EOL.PHP_EOL, $result));
//    }
//
//    protected function expandResolvedFile(
//        string $filePath,
//        array $visitedFiles = []
//    ): string {
//        $normalizedPath = realpath($filePath) ?: $filePath;
//
//        if (! File::exists($filePath)) {
//            $this->addWarning("Included file not found: {$filePath}");
//
//            return '';
//        }
//
//        if (in_array($normalizedPath, $visitedFiles, true)) {
//            $this->addWarning("Circular include skipped: {$normalizedPath}");
//
//            return '';
//        }
//
//        $content = File::get($filePath);
//
//        if (trim($content) === '') {
//            $this->addWarning("Included file is empty: {$filePath}");
//
//            return '';
//        }
//
//        return trim($this->expandIncludes(
//            content: $content,
//            relativeTo: dirname($filePath),
//            visitedFiles: [...$visitedFiles, $normalizedPath],
//        ));
//    }
//
//    protected function resolvePath(string $path, string $relativeTo): string
//    {
//        if ($this->isAbsolutePath($path)) {
//            return $path;
//        }
//
//        $relativePath = $relativeTo.DIRECTORY_SEPARATOR.$path;
//
//        if (File::exists($relativePath)) {
//            return realpath($relativePath) ?: $relativePath;
//        }
//
//        $basePath = base_path($path);
//
//        return realpath($basePath) ?: $basePath;
//    }
//
//    protected function resolveGlobPath(string $path, string $relativeTo): string
//    {
//        if ($this->isAbsolutePath($path)) {
//            return $path;
//        }
//
//        return $relativeTo.DIRECTORY_SEPARATOR.$path;
//    }
//
//    protected function isAbsolutePath(string $path): bool
//    {
//        return str_starts_with($path, '/')
//            || preg_match('/^[A-Z]:\\\\/i', $path) === 1;
//    }
//
//    protected function addWarning(string $message): void
//    {
//        $this->warnings[] = $message;
//    }
}
