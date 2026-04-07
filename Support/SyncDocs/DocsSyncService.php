<?php

namespace Support\SyncDocs;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DocsSyncService
{
    public function __construct(
        protected NuclinoClient $nuclinoClient
    ) {}

    public function syncModuleDocs(string $module, Command $command): int
    {
        $command->info('Docs sync started');
        $command->line('Target: nuclino');
        $command->line("Module: {$module}");

        $workspaceName = env('NUCLINO_WORKSPACE_NAME');
        $docsPath = base_path('docs');

        if (!env('NUCLINO_API_KEY') || !$workspaceName) {
            $command->error('NUCLINO_API_KEY veya NUCLINO_WORKSPACE_NAME eksik.');

            return Command::FAILURE;
        }

        if (!File::exists($docsPath)) {
            $command->error("docs klasoru bulunamadi: {$docsPath}");

            return Command::FAILURE;
        }

        $markdownFiles = collect(File::files($docsPath))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'md')
            ->values();

        if ($markdownFiles->isEmpty()) {
            $command->warn('docs klasorunde .md dosyasi bulunamadi.');

            return Command::SUCCESS;
        }

        $workspace = $this->nuclinoClient->findWorkspaceByName($workspaceName);

        if (!$workspace) {
            $command->error("Workspace bulunamadi: {$workspaceName}");

            return Command::FAILURE;
        }

        $workspaceId = $workspace['id'];

        $command->info("Workspace bulundu: {$workspaceName}");

        $itemsResult = $this->nuclinoClient->getItems($workspaceId);

        if (!$itemsResult['successful']) {
            $command->error('Items request basarisiz.');
            $command->line($itemsResult['body']);

            return Command::FAILURE;
        }

        $items = collect($itemsResult['data']);

        if ($items->isEmpty()) {
            $command->error('Workspace icinde hic item/collection bulunamadi.');

            return Command::FAILURE;
        }

        $moduleDocs = $this->nuclinoClient->findModuleDocs($items, $workspace);

        if (!$moduleDocs) {
            $command->error('Module Docs collection bulunamadi.');

            return Command::FAILURE;
        }

        $command->info('Module Docs bulundu');

        $moduleFolder = $this->nuclinoClient->findModuleFolder($items, $moduleDocs, $module);

        if (!$moduleFolder) {
            $command->error("Module klasoru bulunamadi: {$module}");

            return Command::FAILURE;
        }

        $moduleFolderId = $moduleFolder['id'];
        $moduleFolderChildIds = $moduleFolder['childIds'] ?? [];

        $command->info("Module klasoru bulundu: {$module}");

        $existingModuleItems = $items
            ->filter(function ($item) use ($moduleFolderChildIds) {
                return ($item['object'] ?? null) === 'item'
                    && in_array($item['id'] ?? null, $moduleFolderChildIds, true);
            })
            ->values();

        foreach ($markdownFiles as $file) {
            $title = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $content = File::get($file->getPathname());

            $existingItem = $existingModuleItems->first(function ($item) use ($title) {
                return strtolower(trim((string) ($item['title'] ?? ''))) === strtolower(trim($title));
            });

            if ($existingItem) {
                $itemId = $existingItem['id'];

                $command->line("Update ediliyor: {$title}");

                $updateResult = $this->nuclinoClient->updateItem($itemId, $title, $content);

                if ($updateResult['successful']) {
                    $command->info("Updated: {$title}");
                } else {
                    $command->error("Update hatasi: {$title}");
                    $command->line($updateResult['body']);
                }
            } else {
                $command->line("Create ediliyor: {$title}");

                $createResult = $this->nuclinoClient->createItem($moduleFolderId, $title, $content);

                if ($createResult['successful']) {
                    $command->info("Created: {$title}");
                } else {
                    $command->error("Create hatasi: {$title}");
                    $command->line($createResult['body']);
                }
            }

            $command->line(str_repeat('-', 30));
        }

        $command->info('Sync tamamlandi');

        return Command::SUCCESS;
    }
}
