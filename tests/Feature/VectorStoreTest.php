<?php

namespace Surabayacoder\Sage\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Surabayacoder\Sage\Drivers\FileVectorStore;
use Surabayacoder\Sage\Tests\TestCase;

class VectorStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        Storage::disk('local')->delete('test_vector_db.json');
        parent::tearDown();
    }

    public function test_file_vector_store_can_save_and_search()
    {
        Storage::fake('local');

        $config = ['path' => 'test_vector_db.json'];
        $store = new FileVectorStore($config);

        $vectors = [
            [
                'content' => 'Hello World',
                'source' => 'hello.txt',
                'embedding' => [0.1, 0.2, 0.3]
            ],
            [
                'content' => 'Foo Bar',
                'source' => 'foo.txt',
                'embedding' => [0.9, 0.8, 0.7]
            ]
        ];

        $store->save($vectors);

        Storage::disk('local')->assertExists('test_vector_db.json');

        // Search closest to first vector
        $results = $store->search([0.1, 0.2, 0.31]);

        $this->assertCount(2, $results);
        $this->assertEquals('Hello World', $results[0]['content']);
    }

    public function test_file_vector_store_upsert_logic()
    {
        Storage::fake('local');
        $config = ['path' => 'test_vector_db.json'];
        $store = new FileVectorStore($config);

        // 1. Ingest Initial Data (2 files)
        $store->save([
            ['content' => 'Data A', 'source' => 'file1.txt', 'embedding' => [0.1]],
            ['content' => 'Data B', 'source' => 'file2.txt', 'embedding' => [0.2]]
        ]);

        // Verify both exist
        $content = json_decode(Storage::disk('local')->get('test_vector_db.json'), true);
        $this->assertCount(2, $content);

        // 2. Re-ingest file1.txt with NEW content
        // This should REPLACE the old 'Data A'
        $store->save([
             ['content' => 'Data A Updated', 'source' => 'file1.txt', 'embedding' => [0.15]],
        ]);

        $newContent = json_decode(Storage::disk('local')->get('test_vector_db.json'), true);

        $this->assertCount(2, $newContent); // Should still be 2 items (file1 + file2)

        foreach ($newContent as $item) {
            if ($item['source'] === 'file1.txt') {
                $this->assertEquals('Data A Updated', $item['content']);
            }
            if ($item['source'] === 'file2.txt') {
                $this->assertEquals('Data B', $item['content']);
            }
        }
    }
}
