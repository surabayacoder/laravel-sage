<?php

namespace Surabayacoder\Sage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Surabayacoder\Sage\Services\RagService;
use Surabayacoder\Sage\Contracts\VectorStore;
use Surabayacoder\Sage\Drivers\PgvectorStore;
use Surabayacoder\Sage\Commands\IngestCommand;
use Surabayacoder\Sage\Drivers\FileVectorStore;
use Surabayacoder\Sage\Drivers\MySqlVectorStore;

class SageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sage.php', 'sage');

        // Daftarkan implementasi VectorStore ke container
        $this->app->singleton(VectorStore::class, function ($app) {
            $config = $app['config']['sage'];
            $driver = $config['vector_store']['driver'];
            $driverConfig = $config['vector_store']['drivers'][$driver];

            return match ($driver) {
                'file' => new FileVectorStore($driverConfig),
                'pgvector' => new PgvectorStore($driverConfig),
                'mysql' => new MySqlVectorStore($driverConfig),
                default => throw new \Exception("Driver [{$driver}] tidak didukung."),
            };
        });

        // Daftarkan Service utama ke container
        $this->app->singleton(RagService::class, function ($app) {
            return new RagService(
                $app->make(VectorStore::class),
                $app['config']['sage']
            );
        });

        // Daftarkan Facade
        $this->app->bind('sage', fn ($app) => $app->make(RagService::class));
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/sage.php' => config_path('sage.php'),
        ], 'sage-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                IngestCommand::class,
            ]);

            $this->publishMigrations();
        }

        if (!Storage::disk('public')->exists('rag_sources')) {
            Storage::disk('public')->makeDirectory('rag_sources');
        }
    }

    /**
     * Mendaftarkan migrasi yang bisa di-publish berdasarkan driver yang dipilih.
     */
    protected function publishMigrations(): void
    {
        // Baca driver yang sedang aktif dari file konfigurasi
        $driver = config('sage.vector_store.driver');
        $timestamp = date('Y_m_d_His', time());

        // Tentukan file stub migrasi mana yang akan digunakan
        $migrationStub = match ($driver) {
            'mysql' => 'create_sage_mysql_embeddings_table.php.stub',
            'pgvector' => 'create_sage_pgvector_embeddings_table.php.stub',
            default => null, // Jangan publish apa-apa jika drivernya 'file' atau tidak didukung
        };

        if ($migrationStub) {
            $this->publishes([
                __DIR__.'/../database/migrations/'.$migrationStub => database_path('migrations/'.$timestamp.'_create_sage_embeddings_table.php'),
            ], 'sage-migrations');
        }
    }
}
