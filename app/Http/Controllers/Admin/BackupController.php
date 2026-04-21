<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BackupController extends Controller
{
    // Supabase
    private string $supabaseUrl;
    private string $supabaseKey;
    private string $bucket = 'RideGuide';

    public function __construct()
    {
        $this->supabaseUrl = env('SUPABASE_URL', '');
        $this->supabaseKey = env('SUPABASE_KEY', '');
    }
       
    public function index(Request $request): View|JsonResponse
    {
        $this->authorizePermissions($request, 'view_backups');

        $backups = collect();
        $error   = null;

        if (!$this->supabaseUrl || !$this->supabaseKey) {
            $error = 'Supabase credentials are not configured.';
        } else {
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
                    $error = 'Failed to fetch backups from Supabase.';
                    Log::error('Supabase backup list error: ' . $response->body());
                } else {
                    $files = $response->json();

                    // filter only .sql files and date time format only
                    $backups = collect($files)
                        ->filter(fn($file) => isset($file['name']) && str_ends_with($file['name'], '.sql'))
                        ->map(fn($file) => [
                            'name'       => $file['name'],
                            'size'       => isset($file['metadata']['size'])
                                ? round($file['metadata']['size'] / 1024, 2) . ' KB'
                                : 'N/A',
                            'created_at' => $file['created_at'] ?? null,
                            'updated_at' => $file['updated_at'] ?? null,
                        ])
                        ->values();
                }
            } catch (\Exception $e) {
                Log::error('Backup list fetch failed: ' . $e->getMessage());
                $error = 'Failed to retrieve backup list.';
            }
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => is_null($error),
                'data'    => $backups,
                'total'   => $backups->count(),
                'message' => $error,
                'rows'    => view('admin.backups._rows', compact('backups'))->render(),
            ]);
        }

        return view('admin.backups.index', compact('backups', 'error'));
    }

    // Donwload a backup
    public function download(Request $request, string $filename): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizePermissions($request, 'download_backups');

        if (!str_ends_with($filename, '.sql')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid backup filename. Must be a .sql file.',
            ], 400);
        }

        try {
            $downloadUrl = "{$this->supabaseUrl}/storage/v1/object/{$this->bucket}/{$filename}";

            $response = Http::withHeaders([
                'apikey'        => $this->supabaseKey,
                'Authorization' => 'Bearer ' . $this->supabaseKey,
            ])->get($downloadUrl);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found in Supabase.',
                    'error'   => $response->body(),
                ], 404);
            }

            return response()->streamDownload(function () use ($response) {
                echo $response->body();
            }, $filename, [
                'Content-Type'        => 'application/sql',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            Log::error('Backup download failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to download backup file.',
            ], 500);
        }
    }

    // Restore a backup from Supabase Storage
    public function restore(Request $request, string $filename): JsonResponse
    {
        $this->authorizePermissions($request, 'restore_backups');

        $user = $request->user();

        if (!str_ends_with($filename, '.sql')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid backup filename. Must be a .sql file.',
            ], 400);
        }

        try {
            Log::info("Database restore initiated by super admin: {$user->email}, file: {$filename}");

            // Create Backup first para safe
            Log::info("Creating pre-restore backup before overwriting database...");
            $backupExitCode = Artisan::call('app:backup-database');
            $backupOutput   = Artisan::output();

            if ($backupExitCode !== 0) {
                Log::error("Pre-restore backup failed: {$backupOutput}");

                return response()->json([
                    'success' => false,
                    'message' => 'Restore aborted — failed to create a safety backup before restoring.',
                    'error'   => trim($backupOutput),
                ], 500);
            }

            Log::info("Pre-restore backup created successfully.");

            // Run restore command
            $exitCode = Artisan::call('app:restore-database', [
                '--file'  => $filename,
                '--force' => true,
            ]);

            $output = Artisan::output();

            if ($exitCode === 0) {
                Log::info("Database restore completed successfully: {$filename}");

                return response()->json([
                    'success' => true,
                    'message' => 'Database restored successfully.',
                    'data'    => [
                        'file'       => $filename,
                        'output'     => trim($output),
                        'restored_by' => $user->email,
                        'restored_at' => now()->toDateTimeString(),
                    ],
                ]);
            }

            Log::error("Database restore failed: {$output}");

            return response()->json([
                'success' => false,
                'message' => 'Database restore failed.',
                'error'   => trim($output),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Database restore exception: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during database restore.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // Create new backup manually
    public function create(Request $request): JsonResponse
    {
        $this->authorizePermissions($request, 'create_backups');

        $user = $request->user();

        try {
            Log::info("Manual database backup initiated by super admin: {$user->email}");

            $exitCode = Artisan::call('app:backup-database');
            $output   = Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Database backup created successfully.',
                    'data'    => [
                        'output'     => trim($output),
                        'created_by' => $user->email,
                        'created_at' => now()->toDateTimeString(),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Database backup failed.',
                'error'   => trim($output),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Database backup exception: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during database backup.',
            ], 500);
        }
    }
}