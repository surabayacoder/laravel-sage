<?php

namespace Surabayacoder\Sage\Services;

use Gemini\Contracts\ClientContract;
use Surabayacoder\Sage\Contracts\VectorStore;

class RagService
{
    protected ClientContract $client;
    protected VectorStore $vectorStore;
    protected array $config;

    public function __construct(VectorStore $vectorStore, ClientContract $client, array $config)
    {
        $this->config = $config;
        $this->vectorStore = $vectorStore;
        $this->client = $client;
    }

    public function ask(string $question): array
    {
        $embeddingModel = $this->config['models']['embedding'];

        $chatModel = $this->config['models']['chat'];

        $questionEmbedding = $this->client->embeddingModel($embeddingModel)
            ->embedContent($question)
            ->embedding
            ->values;

        $contexts = $this->vectorStore->search($questionEmbedding);

        if (empty($contexts)) {
            return [
                'answer' => "Maaf, saya tidak memiliki informasi yang cukup untuk menjawab pertanyaan itu.",
                'sources' => []
            ];
        }

        $contextText = implode("\n\n---\n\n", array_column($contexts, 'content'));

        $sources = array_unique(array_column($contexts, 'source'));

        $promptTemplate = $this->config['prompts']['answer'] ??
            "Jawab pertanyaan berikut hanya berdasarkan konteks yang diberikan.\n\nKonteks:\n{context}\n\nPertanyaan: {question}\n\nJawaban:";

        $prompt = str_replace(
            ['{context}', '{question}'],
            [$contextText, $question],
            $promptTemplate
        );

        $answer = $this->client->generativeModel($chatModel)
            ->generateContent($prompt)
            ->text();

        $result = [
            'answer' => $answer,
            'sources' => array_values($sources),
        ];

        return $result;
    }
}
