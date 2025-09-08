<?php

namespace Surabayacoder\Sage\Drivers;

use Illuminate\Support\Facades\Storage;
use Surabayacoder\Sage\Contracts\VectorStore;

class FileVectorStore implements VectorStore
{
    private string $path;

    public function __construct(array $config)
    {
        $this->path = $config['path'];
    }

    public function save(array $vectors): void
    {
        Storage::put($this->path, json_encode($vectors, JSON_PRETTY_PRINT));
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
                'similarity' => $this->cosineSimilarity($queryVector, $item['embedding']),
            ];
        }

        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similarities, 0, $limit);
    }

    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0;
        $magA = 0;
        $magB = 0;
        $count = count($vecA);

        if ($count === 0 || $count !== count($vecB)) {
            return 0;
        }

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $magA += $vecA[$i] * $vecA[$i];
            $magB += $vecB[$i] * $vecB[$i];
        }

        $magA = sqrt($magA);
        $magB = sqrt($magB);

        if ($magA == 0 || $magB == 0) {
            return 0;
        }

        return $dotProduct / ($magA * $magB);
    }
}
