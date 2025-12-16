<?php
/**
 * Pugo - Delete Media Action
 * 
 * Deletes a media file.
 */

namespace Pugo\Actions\Media;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class DeleteMediaAction
{
    public function __construct(
        private string $imagesDir
    ) {}

    /**
     * Delete a media file
     * 
     * @param string $path Path relative to images directory (e.g., "articles/users/image.png")
     */
    public function handle(string $path): ActionResult
    {
        if (empty($path)) {
            return ActionResult::failure('File path is required');
        }

        // Remove /images/ prefix if present
        $path = preg_replace('#^/images/#', '', $path);
        $path = ltrim($path, '/');

        $fullPath = $this->imagesDir . '/' . $path;

        // Security check
        $realPath = realpath($fullPath);
        $realImagesDir = realpath($this->imagesDir);
        
        if ($realPath === false) {
            return ActionResult::failure('File not found');
        }

        if (!str_starts_with($realPath, $realImagesDir)) {
            return ActionResult::failure('Invalid file path');
        }

        if (!file_exists($fullPath)) {
            return ActionResult::failure('File not found');
        }

        if (is_dir($fullPath)) {
            return ActionResult::failure('Cannot delete directories');
        }

        try {
            $filename = basename($fullPath);
            
            if (!unlink($fullPath)) {
                return ActionResult::failure('Could not delete file');
            }

            return ActionResult::success(
                message: "File \"{$filename}\" deleted successfully",
                data: [
                    'path' => $path,
                    'filename' => $filename
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error deleting file: ' . $e->getMessage());
        }
    }
}

