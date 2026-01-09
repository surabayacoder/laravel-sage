<?php

namespace Surabayacoder\Sage\Tests\Unit;

use Mockery;
use Gemini\Contracts\ClientContract;
use Gemini\Contracts\Resources\GenerativeModelContract;
use Gemini\Contracts\Resources\EmbeddingModalContract;
use Surabayacoder\Sage\Contracts\VectorStore;
use Surabayacoder\Sage\Services\RagService;
use Surabayacoder\Sage\Tests\TestCase;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Gemini\Responses\GenerativeModel\EmbedContentResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RagServiceTest extends TestCase
{
    use RefreshDatabase; // Diperlukan untuk verifikasi persistensi data

    public function test_it_can_ask_question_stateless()
    {
        // 1. Mock VectorStore
        $vectorStore = Mockery::mock(VectorStore::class);
        $vectorStore->shouldReceive('search')
            ->once()
            ->andReturn([
                ['content' => 'Laravel Sage adalah package untuk RAG.', 'source' => 'readme.md'],
            ]);

        // Helper untuk response
        $fakeEmbedResponse = EmbedContentResponse::from([
            'embedding' => ['values' => [0.1, 0.2, 0.3]]
        ]);

        $fakeGenerateResponse = GenerateContentResponse::from([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'Sage adalah package RAG.']],
                        'role' => 'model'
                    ],
                    'finishReason' => 'STOP',
                    'index' => 0
                ]
            ],
            'usageMetadata' => ['promptTokenCount' => 10, 'totalTokenCount' => 20, 'candidatesTokenCount' => 10, 'cachedContentTokenCount' => 0]
        ]);

        // Models
        $embeddingModel = Mockery::mock(EmbeddingModalContract::class);
        $embeddingModel->shouldReceive('embedContent')
            ->with('Apa itu Sage?')
            ->andReturn($fakeEmbedResponse);

        $generativeModel = Mockery::mock(GenerativeModelContract::class);
        $generativeModel->shouldReceive('generateContent')
            ->once()
            ->andReturn($fakeGenerateResponse);

        $client = Mockery::mock(ClientContract::class);
        $client->shouldReceive('embeddingModel')->andReturn($embeddingModel);
        $client->shouldReceive('generativeModel')->andReturn($generativeModel);

        $config = [
            'api_key' => 'fake-key',
            'models' => ['embedding' => 'e', 'chat' => 'c'],
            'prompts' => ['answer' => '{context} - {question}']
        ];

        // Act
        $service = new RagService($vectorStore, $client, $config);
        $result = $service->ask('Apa itu Sage?');

        // Assert
        $this->assertEquals('Sage adalah package RAG.', $result['answer']);
        $this->assertEquals(['readme.md'], $result['sources']);
    }

    public function test_it_can_maintain_chat_session()
    {
        // 1. Setup DB
        $migration = include __DIR__ . '/../../database/migrations/create_sage_chat_histories_table.php.stub';
        $migration->up();
        $sessionId = 'session-123';

        // 2. Mock VectorStore
        $vectorStore = Mockery::mock(VectorStore::class);
        $vectorStore->shouldReceive('search')->andReturn([['content' => 'Dummy', 'source' => 'doc']]);

        // 3. Mock Transporter (Inti dari strategi ini)
        $transporter = Mockery::mock(\Gemini\Contracts\TransporterContract::class);

        // Ekspektasi Embedding Request
        $transporter->shouldReceive('request')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \Gemini\Requests\GenerativeModel\EmbedContentRequest;
            }))
            ->andReturn(new \Gemini\Transporters\DTOs\ResponseDTO([
                'embedding' => ['values' => [0.1]]
            ]));

        // Ekspektasi Chat Message Request (GenerateContentRequest)
        // ChatSession memanggil generateContent pada model
        $transporter->shouldReceive('request')
            ->with(Mockery::on(function ($request) {
                return $request instanceof \Gemini\Requests\GenerativeModel\GenerateContentRequest;
            }))
            ->andReturn(new \Gemini\Transporters\DTOs\ResponseDTO([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'I am Sage']], 'role' => 'model'],
                    'finishReason' => 'STOP',
                    'index' => 0
                ]],
                'usageMetadata' => ['promptTokenCount' => 0, 'totalTokenCount' => 0]
            ]));

        // 4. Buat Object Asli dengan Transporter Mock
        $generativeModel = new \Gemini\Resources\GenerativeModel($transporter, 'gemini-1.5-flash');
        $embeddingModel = new \Gemini\Resources\EmbeddingModel($transporter, 'embedding-001');

        // 5. Mock Client untuk mengembalikan object asli ini
        $client = Mockery::mock(ClientContract::class);
        $client->shouldReceive('embeddingModel')->andReturn($embeddingModel);
        $client->shouldReceive('generativeModel')->andReturn($generativeModel);

        $config = ['api_key' => 'x', 'models' => ['embedding' => 'e', 'chat' => 'c'], 'prompts' => []];

        // 6. Act
        $service = new RagService($vectorStore, $client, $config);
        $result = $service->ask('Who are you?', $sessionId);

        // 7. Assert
        $this->assertEquals('I am Sage', $result['answer']);
        $this->assertDatabaseHas('sage_chat_histories', ['session_id' => $sessionId, 'content' => 'Who are you?']);
        $this->assertDatabaseHas('sage_chat_histories', ['session_id' => $sessionId, 'content' => 'I am Sage']);
    }

    public function test_it_rewrites_query_with_history()
    {
        // 0. Setup DB
        $migration = include __DIR__ . '/../../database/migrations/create_sage_chat_histories_table.php.stub';
        $migration->up();

        $sessionId = 'session-rewrite';

        // 1. Seed Riwayat
        \Illuminate\Support\Facades\DB::table('sage_chat_histories')->insert([
            ['session_id' => $sessionId, 'role' => 'user', 'content' => 'Who is Edison?', 'created_at' => now(), 'updated_at' => now()],
            ['session_id' => $sessionId, 'role' => 'model', 'content' => 'Edison is an inventor.', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Mock VectorStore (Ekspektasi pencarian query yang sudah direwrite)
        $vectorStore = Mockery::mock(VectorStore::class);
        $vectorStore->shouldReceive('search')
            ->once() // Kita mengejek embedding di bawah ini agar sesuai dengan query yang ditulis ulang
            ->andReturn([['content' => 'Edison was born in 1847', 'source' => 'bio.txt']]);

        // 3. Mock Transporter
        $transporter = Mockery::mock(\Gemini\Contracts\TransporterContract::class);

        // Helper untuk mengakses properti terlindungi dengan benar untuk jenis request yang berbeda
        $getProperty = function ($object, $property) {
            $ref = new \ReflectionClass($object);
            if ($ref->hasProperty($property)) {
                $prop = $ref->getProperty($property);
                $prop->setAccessible(true);
                return $prop->getValue($object);
            }
            return null;
        };

        // A. Rewrite Request (GenerateContentRequest -> punya 'parts')
        $transporter->shouldReceive('request')
            ->with(Mockery::on(function ($request) use ($getProperty) {
                if (!$request instanceof \Gemini\Requests\GenerativeModel\GenerateContentRequest) return false;

                $parts = $getProperty($request, 'parts');
                // Ekstrak teks dari parts untuk memeriksa prompt
                $text = '';
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                         if (is_object($part) && method_exists($part, 'text')) $text .= $part->text();
                         elseif (is_string($part)) $text .= $part;
                         elseif (is_array($part)) $text .= json_encode($part);

                         if ($part instanceof \Gemini\Data\Content) {
                             foreach ($part->parts as $p) $text .= $p->text;
                         }
                    }
                }

                // Rewrite prompt harus berisi instruksi spesifik
                return str_contains($text, 'tulis ulang pertanyaan terakhir');
            }))
            ->once()
            ->andReturn(new \Gemini\Transporters\DTOs\ResponseDTO([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'When was Edison born?']], 'role' => 'model'],
                    'finishReason' => 'STOP', 'index' => 0
                ]],
                'usageMetadata' => ['promptTokenCount' => 0, 'totalTokenCount' => 0]
            ]));

        // B. Embedding Request (EmbedContentRequest -> punya 'part' tunggal)
        // Harus menggunakan Query yang Ditulis Ulang "When was Edison born?"
        $transporter->shouldReceive('request')
            ->with(Mockery::on(function ($request) use ($getProperty) {
                if (!$request instanceof \Gemini\Requests\GenerativeModel\EmbedContentRequest) return false;

                $part = $getProperty($request, 'part');

                $text = '';
                if ($part instanceof \Gemini\Data\Content) {
                     foreach ($part->parts as $p) $text .= $p->text;
                } elseif (is_string($part)) {
                     $text .= $part;
                }

                // Jika teks masih kosong, mungkin itu hanya string mentah di $part
                if (empty($text) && is_string($part)) $text = $part;

                return $text === 'When was Edison born?';
            }))
            ->once()
            ->andReturn(new \Gemini\Transporters\DTOs\ResponseDTO([
                'embedding' => ['values' => [0.1]]
            ]));

        // C. Chat Answer Request (GenerateContentRequest -> punya 'parts')
        // Seharusnya BUKAN request rewrite
        $transporter->shouldReceive('request')
            ->with(Mockery::on(function ($request) use ($getProperty) {
                // Harus memastikan kita tidak sengaja mencocokkan embedding request jika tipenya sama (padahal tidak)
                if (!$request instanceof \Gemini\Requests\GenerativeModel\GenerateContentRequest) return false;

                $parts = $getProperty($request, 'parts');
                $text = '';
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                         if (is_object($part) && method_exists($part, 'text')) $text .= $part->text();
                         elseif (is_string($part)) $text .= $part;

                         if ($part instanceof \Gemini\Data\Content) {
                             foreach ($part->parts as $p) $text .= $p->text;
                         }
                    }
                }

                return !str_contains($text, 'tulis ulang pertanyaan terakhir');
            }))
            ->once()
            ->andReturn(new \Gemini\Transporters\DTOs\ResponseDTO([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '1847']], 'role' => 'model'],
                    'finishReason' => 'STOP', 'index' => 0
                ]],
                'usageMetadata' => ['promptTokenCount' => 0, 'totalTokenCount' => 0]
            ]));

        // 4. Buat Object Asli
        $generativeModel = new \Gemini\Resources\GenerativeModel($transporter, 'gemini-1.5-flash');
        $embeddingModel = new \Gemini\Resources\EmbeddingModel($transporter, 'embedding-001');

        $client = Mockery::mock(ClientContract::class);
        $client->shouldReceive('embeddingModel')->andReturn($embeddingModel);
        $client->shouldReceive('generativeModel')->andReturn($generativeModel);

        $config = [
            'api_key' => 'x',
            'models' => ['embedding' => 'e', 'chat' => 'c'],
            'prompts' => ['rewrite' => 'Instruksi: tulis ulang pertanyaan terakhir']
        ];

        // 5. Act (Tanyakan pertanyaan ambigu)
        $service = new RagService($vectorStore, $client, $config);
        $result = $service->ask('When was he born?', $sessionId);

        // 6. Assert
        $this->assertEquals('1847', $result['answer']);
    }

    public function tearDown(): void
    {
        // Logika migration down diperlukan jika menggunakan DB yang sama, tapi SQLite in-memory menanganinya via RefreshDatabase/Transactions biasanya.
        // Tapi karena kita menjalankan migrasi secara manual, lebih aman untuk tidak mengandalkan auto rollback.
        // Sebenarnya trait RefreshDatabase menangani rollback transaksi, tapi create table adalah DDL yang auto-commit di beberapa DB,
        // tapi SQLite mendukung DDL transaksional.
        parent::tearDown();
        Mockery::close();
    }
}
