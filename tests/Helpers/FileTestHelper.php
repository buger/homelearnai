<?php

namespace Tests\Helpers;

use Illuminate\Http\UploadedFile;

class FileTestHelper
{
    /**
     * Create an UploadedFile instance from content for testing
     * This is a workaround for CI environments where tmpfile() fails
     */
    public static function createUploadedFileWithContent(string $filename, string $content, string $mimeType = 'text/plain'): UploadedFile
    {
        // Create temp directory if it doesn't exist
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Create a temporary file with unique name
        // Handle long filenames by truncating them
        $safeFilename = strlen($filename) > 100 ? substr($filename, 0, 100) : $filename;
        $tempPath = $tempDir.'/'.uniqid().'_'.$safeFilename;
        file_put_contents($tempPath, $content);

        // Create UploadedFile instance with proper error status
        return new UploadedFile(
            $tempPath,
            $filename,
            $mimeType,
            UPLOAD_ERR_OK, // No error
            true // test mode
        );
    }

    /**
     * Create an image UploadedFile for testing
     * This creates a minimal valid image file that works in CI environments
     */
    public static function createImageFile(string $filename, int $width = 100, int $height = 100, string $format = 'png'): UploadedFile
    {
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $safeFilename = strlen($filename) > 100 ? substr($filename, 0, 100) : $filename;
        $tempPath = $tempDir.'/'.uniqid().'_'.$safeFilename;

        // Create minimal valid image data based on format
        switch (strtolower($format)) {
            case 'png':
                // Minimal 1x1 PNG (base64 decoded)
                $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');
                $mimeType = 'image/png';
                break;
            case 'jpg':
            case 'jpeg':
                // Minimal 1x1 JPEG
                $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A8A');
                $mimeType = 'image/jpeg';
                break;
            case 'gif':
                // Minimal 1x1 GIF
                $imageData = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
                $mimeType = 'image/gif';
                break;
            case 'webp':
                // Minimal 1x1 WebP
                $imageData = base64_decode('UklGRhIAAABXRUJQVlA4IAYAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA=');
                $mimeType = 'image/webp';
                break;
            case 'svg':
                // Minimal SVG
                $imageData = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1" fill="black"/></svg>';
                $mimeType = 'image/svg+xml';
                break;
            default:
                throw new \InvalidArgumentException("Unsupported image format: {$format}");
        }

        file_put_contents($tempPath, $imageData);

        return new UploadedFile(
            $tempPath,
            $filename,
            $mimeType,
            UPLOAD_ERR_OK,
            true
        );
    }

    /**
     * Create a file with specific size for testing
     */
    public static function createFileWithSize(string $filename, int $sizeInBytes, string $mimeType = 'application/octet-stream'): UploadedFile
    {
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $safeFilename = strlen($filename) > 100 ? substr($filename, 0, 100) : $filename;
        $tempPath = $tempDir.'/'.uniqid().'_'.$safeFilename;

        // Create file with specified size
        $content = str_repeat('A', $sizeInBytes);
        file_put_contents($tempPath, $content);

        return new UploadedFile(
            $tempPath,
            $filename,
            $mimeType,
            UPLOAD_ERR_OK,
            true
        );
    }

    /**
     * Clean up temporary test files
     */
    public static function cleanupTempFiles(): void
    {
        $tempDir = storage_path('app/temp');
        if (is_dir($tempDir)) {
            $files = glob($tempDir.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
