<?php

namespace Surabayacoder\Sage\Drivers;

use Illuminate\Support\Facades\DB;
use Surabayacoder\Sage\Contracts\VectorStore;
use Surabayacoder\Sage\Support\VectorHelper;

class MySqlVectorStore implements VectorStore
{
    protected string $table;

    public function __construct(array $config)
    {
        $this->table = $config['table'];
    }

    public function save(array $vectors): void
    {
        // Ambil daftar source unik dari input data baru
        $sources = array_unique(array_column($vectors, 'source'));

        if (empty($sources)) {
            return;
        }

        // Hapus data lama yang memiliki source yang sama (Upsert Logic)
        DB::table($this->table)->whereIn('source', $sources)->delete();

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
                'similarity' => VectorHelper::cosineSimilarity($queryVector, $dbEmbedding),
            ];
        }

        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similarities, 0, $limit);
    }
}
