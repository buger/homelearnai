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
        $tempPath = $tempDir.'/'.uniqid().'_'.$filename;
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
