<?php
/**
 * Pugo - List Media Action
 * 
 * Lists media files from the static images directory.
 */

namespace Pugo\Actions\Media;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class ListMediaAction
{
    public function __construct(
        private string $imagesDir
    ) {}

    /**
     * List media files in a directory
     * 
     * @param string|null $subdirectory Subdirectory to browse
     */
    public function handle(?string $subdirectory = null): ActionResult
    {
        $targetDir = $this->imagesDir;
        
        if ($subdirectory) {
            $subdirectory = trim($subdirectory, '/');
            $targetDir .= '/' . $subdirectory;
        }

        if (!is_dir($targetDir)) {
            return ActionResult::failure("Directory not found: {$subdirectory}");
        }

        // Security check
        $realPath = realpath($targetDir);
        $realImagesDir = realpath($this->imagesDir);
        
        if ($realPath === false || !str_starts_with($realPath, $realImagesDir)) {
            return ActionResult::failure('Invalid directory path');
        }

        try {
            $files = [];
            $directories = [];

            $items = scandir($targetDir);
            foreach ($items as $item) {
                if ($item[0] === '.') continue;

                $fullPath = $targetDir . '/' . $item;

                if (is_dir($fullPath)) {
                    $directories[] = [
                        'name' => $item,
                        'path' => ($subdirectory ? $subdirectory . '/' : '') . $item
                    ];
                } else {
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'mp4', 'webm'])) {
                        $files[] = [
                            'name' => $item,
                            'path' => '/images/' . ($subdirectory ? $subdirectory . '/' : '') . $item,
                            'full_path' => $fullPath,
                            'type' => in_array($ext, ['mp4', 'webm']) ? 'video' : 'image',
                            'extension' => $ext,
                            'size' => filesize($fullPath),
                            'size_formatted' => $this->formatSize(filesize($fullPath)),
                            'modified' => filemtime($fullPath)
                        ];
                    }
                }
            }

            // Sort files by modified date, newest first
            usort($files, fn($a, $b) => $b['modified'] - $a['modified']);

            // Sort directories alphabetically
            usort($directories, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            return ActionResult::success(
                message: 'Found ' . count($files) . ' files and ' . count($directories) . ' directories',
                data: [
                    'files' => $files,
                    'directories' => $directories,
                    'current_path' => $subdirectory ?? '',
                    'parent_path' => $subdirectory ? dirname($subdirectory) : null
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error listing media: ' . $e->getMessage());
        }
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

