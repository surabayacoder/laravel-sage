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
