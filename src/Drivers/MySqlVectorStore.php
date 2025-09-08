<?php

namespace Surabayacoder\Sage\Drivers;

use Illuminate\Support\Facades\DB;
use Surabayacoder\Sage\Contracts\VectorStore;

class MySqlVectorStore implements VectorStore
{
    protected string $table;

    public function __construct(array $config)
    {
        $this->table = $config['table'];
    }

    public function save(array $vectors): void
    {
        DB::table($this->table)->truncate();

        $dataToInsert = array_map(function ($item) {
            return [
                'content' => $item['content'],
                'source' => $item['source'],
                'embedding' => json_encode($item['embedding']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $vectors);

        foreach (array_chunk($dataToInsert, 500) as $chunk) {
            DB::table($this->table)->insert($chunk);
        }
    }

    public function search(array $queryVector, int $limit = 3): array
    {
        $allVectors = DB::table($this->table)->get();
        $similarities = [];

        foreach ($allVectors as $item) {
            $dbEmbedding = json_decode($item->embedding, true);
            $similarities[] = [
                'content' => $item->content,
                'source' => $item->source,
                'similarity' => $this->cosineSimilarity($queryVector, $dbEmbedding),
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
