<?php

namespace Support\SyncDocs;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class NuclinoClient
{
    protected string $baseUrl = 'https://api.nuclino.com/v0';
    protected array $headers;

    public function __construct()
    {
        $apiKey = env('NUCLINO_API_KEY', );

        $this->headers = [
            'Authorization' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function getWorkspaces(): array
    {
        $response = Http::withHeaders($this->headers)
            ->get("{$this->baseUrl}/workspaces");

        return [
            'successful' => $response->successful(),
            'data' => data_get($response->json(), 'data.results', []),
            'body' => $response->body(),
        ];
    }

    public function getItems(string $workspaceId): array
    {
        $response = Http::withHeaders($this->headers)
            ->get("{$this->baseUrl}/items", [
                'workspaceId' => $workspaceId,
                'limit' => 100,
            ]);

        return [
            'successful' => $response->successful(),
            'data' => data_get($response->json(), 'data.results', []),
            'body' => $response->body(),
        ];
    }

    public function createItem(string $parentId, string $title, string $content): array
    {
        $response = Http::withHeaders($this->headers)
            ->post("{$this->baseUrl}/items", [
                'parentId' => $parentId,
                'object' => 'item',
                'title' => $title,
                'content' => $content,
            ]);

        return [
            'successful' => $response->successful(),
            'data' => $response->json(),
            'body' => $response->body(),
        ];
    }

    public function updateItem(string $itemId, string $title, string $content): array
    {
        $response = Http::withHeaders($this->headers)
            ->put("{$this->baseUrl}/items/{$itemId}", [
                'title' => $title,
                'content' => $content,
            ]);

        return [
            'successful' => $response->successful(),
            'data' => $response->json(),
            'body' => $response->body(),
        ];
    }

    public function findWorkspaceByName(string $workspaceName): ?array
    {
        $result = $this->getWorkspaces();

        if (!$result['successful']) {
            return null;
        }

        return collect($result['data'])->first(function ($workspace) use ($workspaceName) {
            return isset($workspace['name'])
                && trim($workspace['name']) === trim($workspaceName);
        });
    }

    public function findModuleDocs(Collection $items, array $workspace): ?array
    {
        $workspaceChildIds = $workspace['childIds'] ?? [];

        return $items->first(function ($item) use ($workspaceChildIds) {
            return ($item['object'] ?? null) === 'collection'
                && ($item['title'] ?? null) === 'Module Docs'
                && in_array($item['id'] ?? null, $workspaceChildIds, true);
        });
    }

    public function findModuleFolder(Collection $items, array $moduleDocs, string $module): ?array
    {
        $moduleDocsChildIds = $moduleDocs['childIds'] ?? [];

        return $items->first(function ($item) use ($moduleDocsChildIds, $module) {
            return ($item['object'] ?? null) === 'collection'
                && in_array($item['id'] ?? null, $moduleDocsChildIds, true)
                && strtolower(trim((string) ($item['title'] ?? ''))) === strtolower(trim($module));
        });
    }
}
