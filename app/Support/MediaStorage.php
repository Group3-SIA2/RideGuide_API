<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /**
     * @param  'image'|'document'  $category
     */
    public static function putFile(string $directory, UploadedFile $file, string $category = 'image'): string
    {
        self::assertAllowedUpload($file, $category);

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

    /**
     * Shared validation rules for image uploads at API endpoint level.
     */
    public static function imageValidationRules(): array
    {
        $extensions = implode(',', self::allowedImageExtensions());
        $mimeTypes = implode(',', self::allowedImageMimeTypes());

        return [
            'file',
            "mimes:{$extensions}",
            "mimetypes:{$mimeTypes}",
            'max:'.self::maxUploadSizeKilobytes(),
        ];
    }

    /**
     * Shared validation rules for document uploads at API endpoint level.
     */
    public static function documentValidationRules(): array
    {
        $extensions = implode(',', self::allowedDocumentExtensions());
        $mimeTypes = implode(',', self::allowedDocumentMimeTypes());

        return [
            'file',
            "mimes:{$extensions}",
            "mimetypes:{$mimeTypes}",
            'max:'.self::maxUploadSizeKilobytes(),
        ];
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

    protected static function assertAllowedUpload(UploadedFile $file, string $category): void
    {
        if (! in_array($category, ['image', 'document'], true)) {
            throw new RuntimeException("Unsupported media category [{$category}].");
        }

        $size = $file->getSize();
        $maxBytes = self::maxUploadSizeKilobytes() * 1024;
        if (! is_int($size) || $size <= 0 || $size > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => ['Uploaded file exceeds the 10MB limit or is invalid.'],
            ]);
        }

        $clientExtension = Str::lower($file->getClientOriginalExtension() ?? '');
        $mimeType = Str::lower((string) $file->getMimeType());

        $allowedExtensions = $category === 'document'
            ? self::allowedDocumentExtensions()
            : self::allowedImageExtensions();

        $allowedMimeTypes = $category === 'document'
            ? self::allowedDocumentMimeTypes()
            : self::allowedImageMimeTypes();

        if (! in_array($clientExtension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported file extension.'],
            ]);
        }

        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported file content type.'],
            ]);
        }
    }

    protected static function allowedImageExtensions(): array
    {
        return config('media_uploads.images.extensions', ['jpg', 'jpeg', 'png', 'webp']);
    }

    protected static function allowedImageMimeTypes(): array
    {
        return config('media_uploads.images.mimetypes', ['image/jpeg', 'image/png', 'image/webp']);
    }

    protected static function allowedDocumentExtensions(): array
    {
        return config('media_uploads.documents.extensions', ['pdf', 'csv']);
    }

    protected static function allowedDocumentMimeTypes(): array
    {
        return config('media_uploads.documents.mimetypes', [
            'application/pdf',
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'text/comma-separated-values',
        ]);
    }

    protected static function maxUploadSizeKilobytes(): int
    {
        return (int) config('media_uploads.max_file_size_kb', 10240);
    }
}
