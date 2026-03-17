<?php

namespace App\Application\Junie;

use Illuminate\Support\Facades\File;
use RuntimeException;

class GuidelineMerger
{
    public function mergeFromLocalFile(string $localGuidelinesPath): string
    {
        if (! File::exists($localGuidelinesPath)) {
            throw new RuntimeException(
                "Local guidelines file could not be found: {$localGuidelinesPath}"
            );
        }

        $localContent = File::get($localGuidelinesPath);

        $visitedFiles = [
            realpath($localGuidelinesPath) ?: $localGuidelinesPath,
        ];

        return $this->expandIncludes(
            content: $localContent,
            relativeTo: dirname($localGuidelinesPath),
            visitedFiles: $visitedFiles,
        );
    }

    protected function expandIncludes(
        string $content,
        string $relativeTo,
        array $visitedFiles = []
    ): string {
        $pattern = '/\{\$includeFile=(.+?)\$\}/';

        return preg_replace_callback(
            $pattern,
            function (array $matches) use ($relativeTo, $visitedFiles) {
                $includePath = $matches[1];

                if (str_contains($includePath, '*')) {
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

    // if there will be more files, it needs...
    protected function expandSingleInclude(
        string $includePath,
        string $relativeTo,
        array $visitedFiles = []
    ): string {
        $fullPath = $this->resolvePath($includePath, $relativeTo);
        $normalizedPath = realpath($fullPath) ?: $fullPath;

        if (! File::exists($fullPath)) {
            throw new RuntimeException("Included file not found: {$fullPath}");
        }

        if (in_array($normalizedPath, $visitedFiles, true)) {
            throw new RuntimeException("Circular include detected: {$normalizedPath}");
        }

        $includedContent = File::get($fullPath);

        return trim($this->expandIncludes(
            content: $includedContent,
            relativeTo: dirname($fullPath),
            visitedFiles: [...$visitedFiles, $normalizedPath],
        ));
    }

    protected function expandWildcardInclude(
        string $includePath,
        string $relativeTo,
        array $visitedFiles = []
    ): string {
        $globPath = $this->resolveGlobPath($includePath, $relativeTo);
        $files = File::glob($globPath);

        sort($files);

        $result = [];

        foreach ($files as $file) {
            $normalizedPath = realpath($file) ?: $file;

            if (in_array($normalizedPath, $visitedFiles, true)) {
                continue;
            }

            $includedContent = File::get($file);

            $result[] = trim($this->expandIncludes(
                content: $includedContent,
                relativeTo: dirname($file),
                visitedFiles: [...$visitedFiles, $normalizedPath],
            ));
        }

        return trim(implode(PHP_EOL.PHP_EOL, array_filter($result)));
    }

    protected function resolvePath(string $path, string $relativeTo): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        $relativePath = $relativeTo.DIRECTORY_SEPARATOR.$path;

        if (File::exists($relativePath)) {
            return realpath($relativePath) ?: $relativePath;
        }

        $basePath = base_path($path);

        return realpath($basePath) ?: $basePath;
    }

    protected function resolveGlobPath(string $path, string $relativeTo): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $relativeTo.DIRECTORY_SEPARATOR.$path;
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Z]:\\\\/i', $path) === 1;
    }
}
