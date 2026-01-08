<?php

namespace Surabayacoder\Sage\Drivers;

use Illuminate\Support\Facades\DB;
use Surabayacoder\Sage\Contracts\VectorStore;

class PgvectorStore implements VectorStore
{
    protected string $table;

    public function __construct(array $config)
    {
        $this->table = $config['table'];
    }

    public function save(array $vectors): void
    {
        // Ambil daftar source unik dari vectors baru
        $sources = array_unique(array_column($vectors, 'source'));

        if (empty($sources)) {
            return;
        }

        // Hapus data lama yang punya source sama
        DB::table($this->table)->whereIn('source', $sources)->delete();

        // Format data untuk bulk insert
        $dataToInsert = array_map(function ($item) {
            return [
                'content' => $item['content'],
                'source' => $item['source'],
                // pgvector menerima vektor dalam format string '[1,2,3,...]'
                'embedding' => '[' . implode(',', $item['embedding']) . ']',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $vectors);

        // Lakukan bulk insert per 500 baris untuk efisiensi
        foreach (array_chunk($dataToInsert, 500) as $chunk) {
            DB::table($this->table)->insert($chunk);
        }
    }

    public function search(array $queryVector, int $limit = 3): array
    {
        $queryEmbedding = '[' . implode(',', $queryVector) . ']';

        // <=> adalah operator Cosine Distance dari pgvector.
        // Hasil 0 berarti identik, 2 berarti berlawanan.
        $results = DB::table($this->table)
            ->select('content', 'source')
            ->selectRaw('embedding <=> ? AS distance', [$queryEmbedding])
            ->orderBy('distance', 'asc') // Urutkan dari jarak terdekat
            ->limit($limit)
            ->get();

        // Ubah format hasil agar sesuai dengan yang diharapkan
        return $results->map(function ($item) {
            return [
                'content' => $item->content,
                'source' => $item->source,
                // Kita tidak butuh similarity score di sini, tapi bisa dihitung jika perlu
                // Similarity = 1 - Distance
            ];
        })->toArray();
    }
}
