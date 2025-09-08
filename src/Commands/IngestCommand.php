<?php

namespace Surabayacoder\Sage\Commands;

use Gemini;
use Spatie\PdfToText\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\CommonMarkConverter;
use Surabayacoder\Sage\Contracts\VectorStore;

class IngestCommand extends Command
{
    protected $signature = 'sage:ingest';

    protected $description = 'Ingest documents into the vector store.';

    public function handle(VectorStore $vectorStore)
    {
        $this->info('Starting ingestion process...');

        $sourcePath = config('sage.ingestion.source_path');

        $embeddingModel = config('sage.models.embedding');

        $apiKey = config('sage.api_key');

        $files = Storage::disk('public')->files($sourcePath);

        $allVectors = [];

        if (empty($files)) {
            $this->warn('No documents found in ' . storage_path($sourcePath));

            return 1;
        }

        foreach ($files as $file) {
            $this->line("Processing: {$file}");

            $extension = pathinfo($file, PATHINFO_EXTENSION);

            $text = '';

            if ($extension === 'pdf') {
                $filePath = Storage::disk('public')->path($file);

                $text = Pdf::getText($filePath);
            } elseif ($extension === 'md') {
                $converter = new CommonMarkConverter();

                $fileContent = Storage::disk('public')->get($file);

                $html = $converter->convert($fileContent);

                $text = strip_tags($html);
            } else {
                $this->warn("Skipping unsupported file type: {$file}");

                continue;
            }

            $chunks = str_split($text, 1500);

            $client = Gemini::client($apiKey);

            $model = $client->embeddingModel($embeddingModel);

            foreach ($chunks as $index => $chunk) {
                $response = $model->embedContent($chunk);

                $allVectors[] = [
                    'content' => $chunks[$index],
                    'embedding' => $response->embedding->values,
                    'source' => basename($file),
                ];
            }
        }

        $vectorStore->save($allVectors);

        $this->info('Ingestion complete!');

        return 0;
    }
}
