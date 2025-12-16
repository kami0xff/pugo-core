<?php
/**
 * Pugo - Delete Content Action
 * 
 * Deletes a content file.
 * Works with any content type (articles, reviews, tutorials, etc.)
 */

namespace Pugo\Actions\Content;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class DeleteContentAction
{
    public function __construct(
        private string $contentDir
    ) {}

    /**
     * Delete a content item
     * 
     * @param string $relativePath Path relative to content directory
     */
    public function handle(string $relativePath): ActionResult
    {
        if (empty($relativePath)) {
            return ActionResult::failure('Content path is required');
        }

        $fullPath = $this->contentDir . '/' . ltrim($relativePath, '/');

        if (!file_exists($fullPath)) {
            return ActionResult::failure("Content not found: {$relativePath}");
        }

        // Security check - ensure path is within content directory
        $realPath = realpath($fullPath);
        $realContentDir = realpath($this->contentDir);
        
        if ($realPath === false || !str_starts_with($realPath, $realContentDir)) {
            return ActionResult::failure('Invalid content path');
        }

        try {
            // Get content info before deletion
            $content = file_get_contents($fullPath);
            $frontmatter = $this->parseFrontmatter($content);
            $title = $frontmatter['title'] ?? basename($fullPath, '.md');

            // Delete the file
            if (!unlink($fullPath)) {
                return ActionResult::failure('Could not delete content file');
            }

            return ActionResult::success(
                message: "Content \"{$title}\" deleted successfully",
                data: [
                    'path' => $fullPath,
                    'relative_path' => $relativePath,
                    'title' => $title
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error deleting content: ' . $e->getMessage());
        }
    }

    private function parseFrontmatter(string $content): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            return [];
        }

        $yaml = $matches[1];
        $result = [];
        $currentKey = null;

        foreach (explode("\n", $yaml) as $line) {
            if (trim($line) === '') continue;

            if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/', $line, $m)) {
                $currentKey = $m[1];
                $value = trim($m[2], '"\'');
                if ($value !== '') {
                    $result[$currentKey] = $value;
                }
            }
        }

        return $result;
    }
}

