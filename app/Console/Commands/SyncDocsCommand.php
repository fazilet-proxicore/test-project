<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SyncDocsCommand extends Command
{
    protected $signature = 'docs:sync {module}';
    protected $description = 'Sync documentation files to Nuclino';

    public function handle(): int
    {
        $target = 'nuclino';
        $module = trim((string) $this->argument('module'));

        $this->info('Docs sync started');
        $this->line("Target: {$target}");
        $this->line("Module: {$module}");

        $apiKey = env('NUCLINO_API_KEY');
        $workspaceName = env('NUCLINO_WORKSPACE_NAME');
        $docsPath = base_path('docs');

        if (! $apiKey || ! $workspaceName) {
            $this->error('NUCLINO_API_KEY veya NUCLINO_WORKSPACE_NAME eksik.');
            return self::FAILURE;
        }

        if (! File::exists($docsPath)) {
            $this->error("docs klasoru bulunamadi: {$docsPath}");
            return self::FAILURE;
        }

        $markdownFiles = collect(File::files($docsPath))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'md')
            ->values();

        if ($markdownFiles->isEmpty()) {
            $this->warn('docs klasorunde .md dosyasi bulunamadi.');
            return self::SUCCESS;
        }

        $headers = [
            'Authorization' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // 1) Workspace bul
        $workspaceResponse = Http::withHeaders($headers)
            ->get('https://api.nuclino.com/v0/workspaces');

        if (! $workspaceResponse->successful()) {
            $this->error('Workspace request basarisiz.');
            $this->line($workspaceResponse->body());
            return self::FAILURE;
        }

        $workspaces = data_get($workspaceResponse->json(), 'data.results', []);

        $workspace = collect($workspaces)->first(function ($item) use ($workspaceName) {
            return isset($item['name']) && trim($item['name']) === trim($workspaceName);
        });

        if (! $workspace) {
            $this->error("Workspace bulunamadi: {$workspaceName}");
            return self::FAILURE;
        }

        $workspaceId = $workspace['id'];
        $workspaceChildIds = $workspace['childIds'] ?? [];

        $this->info("Workspace bulundu: {$workspaceName}");

        // 2) Workspace item/collection listesini cek
        $itemsResponse = Http::withHeaders($headers)
            ->get('https://api.nuclino.com/v0/items', [
                'workspaceId' => $workspaceId,
                'limit' => 100,
            ]);

        if (! $itemsResponse->successful()) {
            $this->error('Items request basarisiz.');
            $this->line($itemsResponse->body());
            return self::FAILURE;
        }

        $items = collect(data_get($itemsResponse->json(), 'data.results', []));

        if ($items->isEmpty()) {
            $this->error('Workspace icinde hic item/collection bulunamadi.');
            return self::FAILURE;
        }

        // 3) Module Docs collection'ini bul
        $moduleDocs = $items->first(function ($item) use ($workspaceChildIds) {
            return ($item['object'] ?? null) === 'collection'
                && ($item['title'] ?? null) === 'Module Docs'
                && in_array($item['id'] ?? null, $workspaceChildIds, true);
        });

        if (! $moduleDocs) {
            $this->error('Module Docs collection bulunamadi.');
            return self::FAILURE;
        }

        $moduleDocsId = $moduleDocs['id'];
        $moduleDocsChildIds = $moduleDocs['childIds'] ?? [];

        $this->info('Module Docs bulundu');

        // 4) Module collection'ini, Module Docs altindaki childIds icinden bul
        $moduleFolder = $items->first(function ($item) use ($module, $moduleDocsChildIds) {
            return ($item['object'] ?? null) === 'collection'
                && in_array($item['id'] ?? null, $moduleDocsChildIds, true)
                && strtolower(trim((string) ($item['title'] ?? ''))) === strtolower($module);
        });

        if (! $moduleFolder) {
            $this->error("Module klasoru bulunamadi: {$module}");
            $this->line('Module Docs child IDs: ' . json_encode($moduleDocsChildIds));
            return self::FAILURE;
        }

        $moduleFolderId = $moduleFolder['id'];
        $moduleFolderChildIds = $moduleFolder['childIds'] ?? [];

        $this->info("Module klasoru bulundu: {$module}");

        // 5) Bu module klasorunun altindaki mevcut item'lari ayir
        $existingModuleItems = $items
            ->filter(function ($item) use ($moduleFolderChildIds) {
                return ($item['object'] ?? null) === 'item'
                    && in_array($item['id'] ?? null, $moduleFolderChildIds, true);
            })
            ->values();

        // 6) Her md dosyasi icin update veya create
        foreach ($markdownFiles as $file) {
            $title = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $content = File::get($file->getPathname());

            $existingItem = $existingModuleItems->first(function ($item) use ($title) {
                return strtolower(trim((string) ($item['title'] ?? ''))) === strtolower(trim($title));
            });

            if ($existingItem) {
                $itemId = $existingItem['id'];

                $this->line("Update ediliyor: {$title}");

                $updateResponse = Http::withHeaders($headers)
                    ->put("https://api.nuclino.com/v0/items/{$itemId}", [
                        'title' => $title,
                        'content' => $content,
                    ]);

                if ($updateResponse->successful()) {
                    $this->info("Updated: {$title}");
                } else {
                    $this->error("Update hatasi: {$title}");
                    $this->line($updateResponse->body());
                }
            } else {
                $this->line("Create ediliyor: {$title}");

                $createResponse = Http::withHeaders($headers)
                    ->post('https://api.nuclino.com/v0/items', [
                        'parentId' => $moduleFolderId,
                        'object' => 'item',
                        'title' => $title,
                        'content' => $content,
                    ]);

                if ($createResponse->successful()) {
                    $this->info("Created: {$title}");
                } else {
                    $this->error("Create hatasi: {$title}");
                    $this->line($createResponse->body());
                }
            }

            $this->line(str_repeat('-', 30));
        }

        $this->info('Sync tamamlandi');

        return self::SUCCESS;
    }
}
