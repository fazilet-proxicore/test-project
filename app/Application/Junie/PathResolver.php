<?php

namespace App\Application\Junie;

use Illuminate\Support\Facades\File;

class PathResolver
{
    public function resolvePath(string $path, string $relativeTo): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        $relativePath = $relativeTo . DIRECTORY_SEPARATOR . $path;

        if (File::exists($relativePath)) {
            return $this->normalizePath($relativePath);
        }

        $basePath = base_path($path);

        return $this->normalizePath($basePath);
    }

    public function resolveGlobPath(string $path, string $relativeTo): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $relativeTo . DIRECTORY_SEPARATOR . $path;
    }

    public function normalizePath(string $path): string
    {
        return realpath($path) ?: $path;
    }

    public function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Z]:\\\\/i', $path) === 1;
    }

    public function isWildcardPath(string $path): bool
    {
        return str_contains($path, '*');
    }
}
