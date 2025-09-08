<?php

namespace Surabayacoder\Sage\Services;

use Gemini;
use Surabayacoder\Sage\Contracts\VectorStore;

class RagService
{
    protected VectorStore $vectorStore;
    protected array $config;

    public function __construct(VectorStore $vectorStore, array $config)
    {
        $this->config = $config;
        $this->vectorStore = $vectorStore;
    }

    public function ask(string $question): array
    {
        $embeddingModel = $this->config['models']['embedding'];

        $chatModel = $this->config['models']['chat'];

        $apiKey = $this->config['api_key'];

        $client = Gemini::client($apiKey);

        $questionEmbedding = $client->embeddingModel($embeddingModel)
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

        $prompt = "Jawab pertanyaan berikut hanya berdasarkan konteks yang diberikan.\n\n" .
                  "Konteks:\n{$contextText}\n\n" .
                  "Pertanyaan: {$question}\n\n" .
                  "Jawaban:";

        $answer = $client->generativeModel($chatModel)
            ->generateContent($prompt)
            ->text();

        $result = [
            'answer' => $answer,
            'sources' => array_values($sources),
        ];

        return $result;
    }
}
