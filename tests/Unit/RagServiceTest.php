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

class RagServiceTest extends TestCase
{
    public function test_it_can_ask_question_and_return_answer()
    {
        // 1. Mock VectorStore
        $vectorStore = Mockery::mock(VectorStore::class);
        $vectorStore->shouldReceive('search')
            ->once()
            ->andReturn([
                ['content' => 'Laravel Sage adalah package untuk RAG.', 'source' => 'readme.md'],
            ]);

        // 2. Mock Gemini Client parts
        // Use real Response objects because they are final classes
        $fakeEmbedResponse = EmbedContentResponse::from([
            'embedding' => ['values' => [0.1, 0.2, 0.3]]
        ]);

        $fakeGenerateResponse = GenerateContentResponse::from([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Sage adalah package RAG.']
                        ],
                        'role' => 'model'
                    ],
                    'finishReason' => 'STOP',
                    'index' => 0
                ]
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'totalTokenCount' => 20,
                'candidatesTokenCount' => 10,
                'cachedContentTokenCount' => 0
            ]
        ]);

        // Models (Interfaces now):
        $embeddingModel = Mockery::mock(EmbeddingModalContract::class);
        $embeddingModel->shouldReceive('embedContent')
            ->with('Apa itu Sage?')
            ->andReturn($fakeEmbedResponse);

        $generativeModel = Mockery::mock(GenerativeModelContract::class);
        $generativeModel->shouldReceive('generateContent')
            ->once()
            ->andReturn($fakeGenerateResponse);

        // Client (Interface now):
        $client = Mockery::mock(ClientContract::class);
        $client->shouldReceive('embeddingModel')
            ->with('embedding-001')
            ->andReturn($embeddingModel);

        $client->shouldReceive('generativeModel')
            ->with('gemini-1.5-flash')
            ->andReturn($generativeModel);

        // 3. Config
        $config = [
            'api_key' => 'fake-key',
            'models' => [
                'embedding' => 'embedding-001',
                'chat' => 'gemini-1.5-flash',
            ],
            'prompts' => [
                'answer' => '{context} - {question}'
            ]
        ];

        // 4. Instantiate Service
        $service = new RagService($vectorStore, $client, $config);

        // 5. Act
        $result = $service->ask('Apa itu Sage?');

        // 6. Assert
        $this->assertEquals('Sage adalah package RAG.', $result['answer']);
        $this->assertEquals(['readme.md'], $result['sources']);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
