<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaImageService
{
    private const THUMBNAIL_VERSION = 'v1';

    public function disk(): FilesystemAdapter
    {
        return Storage::disk('public');
    }

    public function resolveImage(string $path): array
    {
        $normalizedPath = $this->normalizePath($path);
        $root = realpath($this->disk()->path(''));
        $absolutePath = realpath($this->disk()->path($normalizedPath));

        if ($root === false || $absolutePath === false || ! is_file($absolutePath)) {
            throw new RuntimeException('File gambar tidak ditemukan.');
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($absolutePath, $rootPrefix)) {
            throw new RuntimeException('Lokasi file gambar tidak valid.');
        }

        $mimeType = function_exists('mime_content_type')
            ? (mime_content_type($absolutePath) ?: 'application/octet-stream')
            : ((@getimagesize($absolutePath)['mime'] ?? null) ?: 'application/octet-stream');
        if (! str_starts_with($mimeType, 'image/')) {
            throw new RuntimeException('File bukan gambar yang didukung.');
        }

        return [
            'path' => $normalizedPath,
            'absolute_path' => $absolutePath,
            'mime_type' => $mimeType,
            'modified_at' => (int) filemtime($absolutePath),
        ];
    }

    public function ensureThumbnail(string $path, int $size = 160): ?array
    {
        $source = $this->resolveImage($path);
        $size = max(48, min($size, 1200));

        if (! extension_loaded('gd') || ! function_exists('getimagesize')) {
            return null;
        }

        $imageInfo = @getimagesize($source['absolute_path']);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            return null;
        }

        $cacheExtension = function_exists('imagewebp') ? 'webp' : ($source['mime_type'] === 'image/png' ? 'png' : 'jpg');
        $cacheKey = hash('sha256', implode('|', [
            self::THUMBNAIL_VERSION,
            $source['path'],
            $source['modified_at'],
            $size,
        ]));
        $relativePath = ".thumbnails/{$size}/{$cacheKey}.{$cacheExtension}";
        $absolutePath = $this->disk()->path($relativePath);

        if (! is_file($absolutePath)) {
            $directory = dirname($absolutePath);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return null;
            }

            if (! $this->createThumbnail(
                $source['absolute_path'],
                $source['mime_type'],
                (int) $imageInfo[0],
                (int) $imageInfo[1],
                $absolutePath,
                $size,
                $cacheExtension,
            )) {
                return null;
            }
        }

        return [
            'path' => $relativePath,
            'absolute_path' => $absolutePath,
            'mime_type' => match ($cacheExtension) {
                'webp' => 'image/webp',
                'png' => 'image/png',
                default => 'image/jpeg',
            },
            'modified_at' => (int) filemtime($absolutePath),
        ];
    }

    public function supportsThumbnailGeneration(): bool
    {
        return extension_loaded('gd') && function_exists('getimagesize');
    }

    private function normalizePath(string $path): string
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');

        if ($normalized === '' || str_contains($normalized, "\0")) {
            throw new RuntimeException('Lokasi file gambar tidak valid.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                throw new RuntimeException('Lokasi file gambar tidak valid.');
            }
        }

        return $normalized;
    }

    private function createThumbnail(
        string $sourcePath,
        string $mimeType,
        int $sourceWidth,
        int $sourceHeight,
        string $destinationPath,
        int $size,
        string $extension,
    ): bool {
        $sourceImage = match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($sourcePath) : false,
            default => false,
        };

        if ($sourceImage === false) {
            return false;
        }

        $scale = min(1, $size / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($thumbnail === false) {
            imagedestroy($sourceImage);
            return false;
        }

        if (in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefilledrectangle($thumbnail, 0, 0, $targetWidth, $targetHeight, $transparent);
        } else {
            $white = imagecolorallocate($thumbnail, 255, 255, 255);
            imagefilledrectangle($thumbnail, 0, 0, $targetWidth, $targetHeight, $white);
        }

        $resampled = imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        $saved = $resampled && match ($extension) {
            'webp' => @imagewebp($thumbnail, $destinationPath, 78),
            'png' => @imagepng($thumbnail, $destinationPath, 6),
            default => @imagejpeg($thumbnail, $destinationPath, 82),
        };

        imagedestroy($thumbnail);
        imagedestroy($sourceImage);

        if (! $saved && is_file($destinationPath)) {
            @unlink($destinationPath);
        }

        return $saved;
    }
}
