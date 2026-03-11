<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup the MySQL database and upload the .sql file to Supabase Storage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');

        // Database credentials from .env
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbPort = env('DB_PORT', '3306');
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');

        if (!$dbName || !$dbUser) {
            $this->error('Database credentials are not configured.');
            return Command::FAILURE;
        }

        // Generate timestamped filename
        $timestamp = now()->format('Y-m-d_H-i-s');
        $fileName = "backup_{$dbName}_{$timestamp}.sql";
        $localDir = 'RideguideBackup';
        $localPath = "{$localDir}/{$fileName}";

        // Ensure the backup directory exists
        Storage::makeDirectory($localDir);
        $fullLocalPath = Storage::path($localPath);

        // Build the mysqldump command
        $command = [
            'mysqldump',
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--user=' . $dbUser,
            '--password=' . $dbPass,
            '--result-file=' . $fullLocalPath,
            '--single-transaction',
            '--routines',
            '--triggers',
            $dbName,
        ];

        $this->info('Running mysqldump...');

        $process = new Process($command);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('mysqldump failed: ' . $process->getErrorOutput());
            return Command::FAILURE;
        }

        // Verify if the dump is uploaded
        if (!file_exists($fullLocalPath) || filesize($fullLocalPath) === 0) {
            $this->error('Backup file was not created or is empty.');
            return Command::FAILURE;
        }

        $fileSize = round(filesize($fullLocalPath) / 1024, 2);
        $this->info("Database dump created: {$fileName} ({$fileSize} KB)");

        // Upload to Supabase Storage
        $this->info('Uploading backup to Supabase Storage...');

        try {
            $supabaseUrl = env('SUPABASE_URL');
            $supabaseKey = env('SUPABASE_KEY');
            $bucket = 'RideGuide';

            if (!$supabaseUrl || !$supabaseKey) {
                $this->error('Supabase credentials are not configured in .env');
                return Command::FAILURE;
            }

            $response = Http::withHeaders([
                'apikey' => $supabaseKey,
                'Authorization' => 'Bearer ' . $supabaseKey,
                'Content-Type' => 'application/sql',
                'x-upsert' => 'true',
            ])->withBody(
                file_get_contents($fullLocalPath),
                'application/sql'
            )->post("{$supabaseUrl}/storage/v1/object/{$bucket}/{$fileName}");

            // Clean up local file after upload
            Storage::delete($localPath);

            if ($response->successful()) {
                $this->info("Backup uploaded successfully to Supabase: {$fileName}");
                return Command::SUCCESS;
            }

            $this->error('Supabase upload failed: ' . $response->body());
            return Command::FAILURE;
        } catch (\Exception $e) {
            // Clean up local file on error
            Storage::delete($localPath);

            $this->error('Supabase upload failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
