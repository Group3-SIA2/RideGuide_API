<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class RestoreDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:restore-database
                            {--file= : The specific backup filename to restore from Supabase}
                            {--list : List all available backups from Supabase}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore the MySQL database from a .sql backup file stored in Supabase Storage';

    /**
     * Supabase configuration.
     */
    private string $supabaseUrl;
    private string $supabaseKey;
    private string $bucket = 'RideGuide';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Validate Supabase credentials
        $this->supabaseUrl = env('SUPABASE_URL');
        $this->supabaseKey = env('SUPABASE_KEY');

        if (!$this->supabaseUrl || !$this->supabaseKey) {
            $this->error('Supabase credentials are not configured in .env');
            return Command::FAILURE;
        }

        // If --list flag is set, just list backups and exit
        if ($this->option('list')) {
            return $this->listBackups();
        }

        // Determine which file to restore
        $fileName = $this->option('file');

        if (!$fileName) {
            // Fetch available backups and let the user choose
            $backups = $this->fetchBackupList();

            if (empty($backups)) {
                $this->error('No backups found in Supabase Storage.');
                return Command::FAILURE;
            }

            $fileName = $this->choice(
                'Select a backup file to restore:',
                array_column($backups, 'name'),
                0
            );
        }

        $this->info("Selected backup: {$fileName}");

        // Confirmation prompt
        if (!$this->option('force')) {
            $dbName = env('DB_DATABASE', 'rideguide');
            if (!$this->confirm("This will OVERWRITE the database '{$dbName}'. Are you sure you want to continue?")) {
                $this->info('Restore cancelled.');
                return Command::SUCCESS;
            }
        }

        // Step 1: Download the backup file from Supabase
        $this->info('Downloading backup from Supabase Storage...');
        $localPath = $this->downloadBackup($fileName);

        if (!$localPath) {
            return Command::FAILURE;
        }

        // Step 2: Restore the database using mysql CLI
        $this->info('Restoring database...');
        $result = $this->restoreFromFile($localPath);

        // Step 3: Clean up the downloaded file
        Storage::delete($localPath);

        if ($result) {
            $this->info('✅ Database restored successfully from: ' . $fileName);
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    /**
     * List all available backups from Supabase.
     */
    private function listBackups(): int
    {
        $backups = $this->fetchBackupList();

        if (empty($backups)) {
            $this->info('No backups found in Supabase Storage.');
            return Command::SUCCESS;
        }

        $this->info('Available backups in Supabase Storage:');
        $this->newLine();

        $rows = [];
        foreach ($backups as $index => $backup) {
            $size = isset($backup['metadata']['size'])
                ? round($backup['metadata']['size'] / 1024, 2) . ' KB'
                : 'N/A';

            $created = $backup['created_at'] ?? 'N/A';

            $rows[] = [
                $index + 1,
                $backup['name'],
                $size,
                $created,
            ];
        }

        $this->table(['#', 'Filename', 'Size', 'Created At'], $rows);

        return Command::SUCCESS;
    }

    /**
     * Fetch the list of backup files from Supabase Storage bucket.
     */
    private function fetchBackupList(): array
    {
        try {
            $response = Http::withHeaders([
                'apikey'        => $this->supabaseKey,
                'Authorization' => 'Bearer ' . $this->supabaseKey,
            ])->post("{$this->supabaseUrl}/storage/v1/object/list/{$this->bucket}", [
                'prefix' => '',
                'limit'  => 100,
                'offset' => 0,
                'sortBy' => [
                    'column' => 'created_at',
                    'order'  => 'desc',
                ],
            ]);

            if (!$response->successful()) {
                $this->error('Failed to fetch backup list from Supabase: ' . $response->body());
                return [];
            }

            $files = $response->json();

            // Filter only .sql files
            return array_values(array_filter($files, function ($file) {
                return isset($file['name']) && str_ends_with($file['name'], '.sql');
            }));
        } catch (\Exception $e) {
            $this->error('Failed to fetch backup list: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Download a backup file from Supabase Storage.
     */
    private function downloadBackup(string $fileName): ?string
    {
        try {
            $downloadUrl = "{$this->supabaseUrl}/storage/v1/object/{$this->bucket}/{$fileName}";

            $response = Http::withHeaders([
                'apikey'        => $this->supabaseKey,
                'Authorization' => 'Bearer ' . $this->supabaseKey,
            ])->get($downloadUrl);

            if (!$response->successful()) {
                $this->error('Failed to download backup (HTTP ' . $response->status() . '): ' . $response->body());
                return null;
            }

            $localDir  = 'RideguideRestore';
            $localPath = "{$localDir}/{$fileName}";

            Storage::makeDirectory($localDir);
            Storage::put($localPath, $response->body());

            $fullPath = Storage::path($localPath);
            $fileSize = round(filesize($fullPath) / 1024, 2);

            $this->info("Downloaded: {$fileName} ({$fileSize} KB)");

            return $localPath;
        } catch (\Exception $e) {
            $this->error('Download failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Restore the database from a local .sql file using mysql CLI.
     */
    private function restoreFromFile(string $localPath): bool
    {
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbPort = env('DB_PORT', '3306');
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');

        if (!$dbName || !$dbUser) {
            $this->error('Database credentials are not configured.');
            return false;
        }

        $fullLocalPath = Storage::path($localPath);

        // Build the mysql import command
        $command = [
            'mysql',
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--user=' . $dbUser,
            '--password=' . $dbPass,
            $dbName,
            '-e',
            'source ' . $fullLocalPath,
        ];

        $process = new Process($command);
        $process->setTimeout(600);

        $this->info('Running mysql restore...');

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->warn('  > ' . trim($buffer));
            } else {
                $this->line('  > ' . trim($buffer));
            }
        });

        if (!$process->isSuccessful()) {
            $this->error('MySQL restore failed: ' . $process->getErrorOutput());
            return false;
        }

        return true;
    }
}
