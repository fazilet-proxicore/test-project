<?php

namespace Support\JunieGuidelines;

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

    public function resolveFolderPath(string $folderPath, string $relativeTo): string
    {
        if ($this->isAbsolutePath($folderPath)) {
            return rtrim($folderPath, DIRECTORY_SEPARATOR);
        }

        $relativePath = $relativeTo . DIRECTORY_SEPARATOR . $folderPath;

        if (File::isDirectory($relativePath)) {
            return $this->normalizePath($relativePath);
        }

        return $this->normalizePath(base_path($folderPath));
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
}
