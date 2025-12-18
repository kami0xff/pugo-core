<?php
/**
 * Pugo - Upload Media Action
 * 
 * Handles media file uploads.
 */

namespace Pugo\Actions\Media;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class UploadMediaAction
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'mp4', 'webm'];
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    public function __construct(
        private string $imagesDir
    ) {}

    /**
     * Upload a media file
     * 
     * @param array $file The $_FILES array entry
     * @param string|null $subdirectory Target subdirectory
     * @param string|null $customName Custom filename (without extension)
     */
    public function handle(array $file, ?string $subdirectory = null, ?string $customName = null): ActionResult
    {
        // Validate file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ActionResult::failure('No file was uploaded');
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ActionResult::failure($this->getUploadError($file['error']));
        }

        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ActionResult::failure('File is too large. Maximum size is 50MB.');
        }

        // Validate extension
        $originalName = $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
            return ActionResult::failure('Invalid file type. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS));
        }

        // Determine target directory
        $targetDir = $this->imagesDir;
        if ($subdirectory) {
            $subdirectory = trim($subdirectory, '/');
            $targetDir .= '/' . $subdirectory;
        }

        // Create directory if needed
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return ActionResult::failure('Could not create target directory');
            }
        }

        // Determine filename
        if ($customName) {
            $filename = $this->sanitizeFilename($customName) . '.' . $ext;
        } else {
            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $filename = $this->sanitizeFilename($baseName) . '-' . time() . '.' . $ext;
        }

        $targetPath = $targetDir . '/' . $filename;

        // Handle duplicate names
        if (file_exists($targetPath) && !$customName) {
            $filename = $this->sanitizeFilename(pathinfo($originalName, PATHINFO_FILENAME)) 
                      . '-' . time() . '-' . mt_rand(1000, 9999) . '.' . $ext;
            $targetPath = $targetDir . '/' . $filename;
        }

        try {
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return ActionResult::failure('Failed to save uploaded file');
            }

            // Set permissions
            chmod($targetPath, 0644);

            $relativePath = '/images/' . ($subdirectory ? $subdirectory . '/' : '') . $filename;

            return ActionResult::success(
                message: 'File uploaded successfully',
                data: [
                    'filename' => $filename,
                    'path' => $relativePath,
                    'full_path' => $targetPath,
                    'size' => filesize($targetPath),
                    'type' => in_array($ext, ['mp4', 'webm']) ? 'video' : 'image'
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error uploading file: ' . $e->getMessage());
        }
    }

    private function sanitizeFilename(string $name): string
    {
        // Remove path components
        $name = basename($name);
        
        // Convert to lowercase
        $name = strtolower($name);
        
        // Replace spaces and special chars with hyphens
        $name = preg_replace('/[^a-z0-9\-_]/', '-', $name);
        
        // Remove multiple consecutive hyphens
        $name = preg_replace('/-+/', '-', $name);
        
        return trim($name, '-');
    }

    private function getUploadError(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            default => 'Unknown upload error'
        };
    }
}

