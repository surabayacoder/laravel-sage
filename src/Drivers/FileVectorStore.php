<?php

namespace Surabayacoder\Sage\Drivers;

use Illuminate\Support\Facades\Storage;
use Surabayacoder\Sage\Contracts\VectorStore;
use Surabayacoder\Sage\Support\VectorHelper;

class FileVectorStore implements VectorStore
{
    private string $path;

    public function __construct(array $config)
    {
        $this->path = $config['path'];
    }

    public function save(array $vectors): void
    {
        $existingVectors = [];

        if (Storage::exists($this->path)) {
            $existingVectors = json_decode(Storage::get($this->path), true) ?? [];
        }

        $newSources = array_unique(array_column($vectors, 'source'));

        // Filter: Buang vektor lama yang source-nya ada di daftar update baru
        $existingVectors = array_filter($existingVectors, function ($item) use ($newSources) {
            return !in_array($item['source'], $newSources);
        });

        // Gabungkan
        $finalVectors = array_merge($existingVectors, $vectors);

        Storage::put($this->path, json_encode($finalVectors, JSON_PRETTY_PRINT));
    }

    public function search(array $queryVector, int $limit = 3): array
    {
        if (!Storage::exists($this->path)) {
            return [];
        }

        $allVectors = json_decode(Storage::get($this->path), true);
        if (empty($allVectors)) {
            return [];
        }

        $similarities = [];

        foreach ($allVectors as $item) {
            $similarities[] = [
                'content' => $item['content'],
                'source' => $item['source'],
                'similarity' => VectorHelper::cosineSimilarity($queryVector, $item['embedding']),
            ];
        }

        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similarities, 0, $limit);
    }
}
