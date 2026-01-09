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

    public function ask(string $question, ?string $sessionId = null): array
    {
        $embeddingModel = $this->config['models']['embedding'];
        $chatModel = $this->config['models']['chat'];

        // 1. Siapkan Riwayat jika Session ID ada (Dipindahkan ke atas untuk Query Rewriting)
        $history = [];
        $historyRecords = []; // Simpan record mentah untuk manajemen window konteks jika diperlukan

        if ($sessionId) {
            $historyRecords = \Illuminate\Support\Facades\DB::table('sage_chat_histories')
                ->where('session_id', $sessionId)
                ->orderBy('created_at', 'asc') // Gemini membutuhkan urutan kronologis
                ->get();

            foreach ($historyRecords as $record) {
                // Tentukan role: 'user' dipetakan ke 'user', 'model' dipetakan ke 'model'
                $roleString = $record->role === 'user' ? 'user' : 'model';
                $role = \Gemini\Enums\Role::tryFrom($roleString) ?? \Gemini\Enums\Role::USER;
                $history[] = \Gemini\Data\Content::parse(part: $record->content, role: $role);
            }
        }

        // 2. Kontekstualisasi (Rewrite) Query jika Riwayat ada
        $searchQuery = $question;
        if (!empty($history)) {
             $searchQuery = $this->contextualizeQuery($question, $historyRecords);
        }

        // 3. Pencarian Vektor
        $questionEmbedding = $this->client->embeddingModel($embeddingModel)
            ->embedContent($searchQuery)
            ->embedding
            ->values;

        $contexts = $this->vectorStore->search($questionEmbedding);

        if (empty($contexts)) {
            // Jika konteks kosong, kita mungkin masih ingin menjawab dengan pengetahuan internal LLM?
            // Tapi RAG strict biasanya lebih memilih "Saya tidak tahu".
            // Mari kita tetap pada "Saya tidak tahu" untuk mencegah halusinasi, atau kembalikan respons generik.
            // Tapi dengan query rewriting, kita berharap menemukan konteks.
            return [
                'answer' => "Maaf, saya tidak memiliki informasi yang cukup untuk menjawab pertanyaan itu.",
                'sources' => []
            ];
        }

        $contextText = implode("\n\n---\n\n", array_column($contexts, 'content'));
        $sources = array_unique(array_column($contexts, 'source'));

        // 4. Buat Prompt dengan Konteks
        $promptTemplate = $this->config['prompts']['answer'] ??
            "Jawab pertanyaan berikut hanya berdasarkan konteks yang diberikan.\n\nKonteks:\n{context}";

        // Masukkan konteks.
        // Kita menggunakan Query yang Ditulis Ulang ($searchQuery) dalam prompt untuk memastikan kejelasan,
        // TAPI kita harus berhati-hati. Jika pengguna bertanya "Kapan dia lahir?" dan kita meneruskan "Kapan Edison lahir?", itu tidak masalah.
        $finalPrompt = str_replace('{context}', $contextText, $promptTemplate) . "\n\n" . $searchQuery;

        // 5. Generate Jawaban
        if ($sessionId) {
            $chat = $this->client->generativeModel($chatModel)->startChat(history: $history);
            $response = $chat->sendMessage($finalPrompt);
            $answer = $response->text();

            // 6. Simpan Riwayat
            // Simpan Pertanyaan Pengguna (Asli, agar pengguna melihat apa yang mereka ketik)
            \Illuminate\Support\Facades\DB::table('sage_chat_histories')->insert([
                'session_id' => $sessionId,
                'role' => 'user',
                'content' => $question,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Simpan Jawaban Model
            \Illuminate\Support\Facades\DB::table('sage_chat_histories')->insert([
                'session_id' => $sessionId,
                'role' => 'model',
                'content' => $answer,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } else {
            // Mode Stateless
             $statelessPrompt = str_replace(
                '{context}',
                $contextText,
                $this->config['prompts']['answer'] ?? "Konteks:\n{context}"
             ) . "\n\nPertanyaan: " . $searchQuery;

            $answer = $this->client->generativeModel($chatModel)
                ->generateContent($statelessPrompt)
                ->text();
        }

        return [
            'answer' => $answer,
            'sources' => array_values($sources),
        ];
    }

    protected function contextualizeQuery(string $question, $historyRecords): string
    {
        // Rewrite sederhana menggunakan Gemini (bisa menggunakan model yang lebih murah jika dikonfigurasi)
        // Ambil beberapa giliran terakhir sebagai teks untuk menghemat token
        $contextStr = "";
        foreach ($historyRecords->slice(-6) as $record) { // 3 giliran terakhir (6 pesan)
            $role = $record->role === 'user' ? 'User' : 'Assistant';
            $contextStr .= "{$role}: {$record->content}\n";
        }

        $promptTemplate = $this->config['prompts']['rewrite'] ??
            "Given the following conversation history, rewrite the last user question to be a standalone question that can be understood without the history. Do NOT answer the question, just rewrite it. If no rewrite is needed, return the original question.\n\nHistory:\n{history}\nUser: {question}\n\nRewritten Question:";

        $prompt = str_replace(
            ['{history}', '{question}'],
            [$contextStr, $question],
            $promptTemplate
        );

        try {
            // Gunakan Model Chat atau model khusus untuk rewriting
            $response = $this->client->generativeModel($this->config['models']['chat'])
                ->generateContent($prompt);

            return trim($response->text());
        } catch (\Exception $e) {
            // Fallback ke pertanyaan asli jika rewrite gagal
            return $question;
        }
    }
}
