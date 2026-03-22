<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaStorage
{
    protected static ?string $resolvedDisk = null;

    public static function disk(): string
    {
        if (self::$resolvedDisk) {
            return self::$resolvedDisk;
        }

        foreach (self::preferredDisks() as $disk) {
            if (self::canResolveDisk($disk)) {
                return self::$resolvedDisk = $disk;
            }
        }

        return self::$resolvedDisk = 'public';
    }

    public static function putFile(string $directory, UploadedFile $file): string
    {
        $disk = self::disk();
        $storage = Storage::disk($disk);
        $driver = config("filesystems.disks.{$disk}.driver");

        $shouldForcePublicVisibility = $disk === 'public' || $driver === 'local';

        $path = $shouldForcePublicVisibility
            ? $storage->putFile($directory, $file, 'public')
            : $storage->putFile($directory, $file);

        if (! $path) {
            Log::error('MediaStorage failed to store uploaded file.', [
                'disk' => $disk,
                'directory' => $directory,
                'driver' => $driver,
            ]);

            throw new RuntimeException("Failed to store uploaded file on [{$disk}] disk.");
        }

        return $path;
    }

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $disk = self::disk();
        $storage = Storage::disk($disk);
        $driver = config("filesystems.disks.{$disk}.driver");

        if ($driver === 's3' && method_exists($storage, 'temporaryUrl')) {
            try {
                $ttl = (int) config('filesystems.temporary_url_ttl', 15);
                return $storage->temporaryUrl($path, now()->addMinutes($ttl));
            } catch (Throwable $e) {
                Log::warning('MediaStorage temporary URL generation failed.', [
                    'disk' => $disk,
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($storage->exists($path)) {
            return $storage->url($path);
        }

        if ($disk !== 'public' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }

    protected static function preferredDisks(): array
    {
        $disks = [];

        if ($configured = config('filesystems.uploads_disk')) {
            $disks[] = $configured;
        }

        if (self::s3Configured()) {
            $disks[] = 's3';
        }

        $disks[] = config('filesystems.default', 'public');
        $disks[] = 'public';

        return array_values(array_unique(array_filter($disks)));
    }

    protected static function s3Configured(): bool
    {
        $s3 = config('filesystems.disks.s3', []);

        return filled($s3['bucket'] ?? null)
            && filled($s3['key'] ?? null)
            && filled($s3['secret'] ?? null);
    }

    protected static function canResolveDisk(string $disk): bool
    {
        try {
            Storage::disk($disk);
            return true;
        } catch (Throwable $e) {
            Log::warning('MediaStorage disk unavailable', [
                'disk' => $disk,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
