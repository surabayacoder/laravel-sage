<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gemini API Key
    |--------------------------------------------------------------------------
    */
    'api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Model Konfigurasi
    |--------------------------------------------------------------------------
    */
    'models' => [
        'embedding' => 'embedding-001',
        'chat' => 'gemini-1.5-flash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompts
    |--------------------------------------------------------------------------
    |
    | Template prompt yang digunakan untuk bertanya ke Gemini.
    | Placeholder {context} dan {question} akan digantikan dengan data asli.
    |
    */
    'prompts' => [
        'answer' => "Jawab pertanyaan berikut hanya berdasarkan konteks yang diberikan.\n\nKonteks:\n{context}\n\nPertanyaan: {question}\n\nJawaban:",
        'rewrite' => "Diberikan riwayat percakapan berikut, tulis ulang pertanyaan terakhir pengguna menjadi pertanyaan mandiri yang dapat dipahami tanpa melihat riwayat. JANGAN menjawab pertanyaan tersebut, cukup tulis ulang saja. Jika tidak perlu ditulis ulang, kembalikan pertanyaan aslinya.\n\nRiwayat:\n{history}\nPengguna: {question}\n\nPertanyaan yang Ditulis Ulang:",
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Sumber Data untuk Indexing
    |--------------------------------------------------------------------------
    */
    'ingestion' => [
        'source_path' => 'rag_sources', // path relatif dari storage_path()
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Store Driver
    |--------------------------------------------------------------------------
    |
    | Driver yang didukung: "file", "pgvector" (belum diimplementasikan)
    |
    */
    'vector_store' => [
        'driver' => env('RAG_VECTOR_DRIVER', 'file'),

        'drivers' => [
            'file' => [
                'path' => 'sage_db/vector_db.json', // path relatif dari storage_path()
            ],
            'pgvector' => [
                'connection' => env('DB_CONNECTION', 'pgsql'),
                'table' => 'sage_embeddings',
            ],
            'mysql' => [
                'connection' => env('DB_CONNECTION', 'mysql'),
                'table' => 'sage_embeddings',
            ],
        ],
    ],
];
