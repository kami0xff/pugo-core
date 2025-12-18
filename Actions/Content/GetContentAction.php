<?php
/**
 * Pugo - Get Content Action
 * 
 * Retrieves a single content item by path.
 * Works with any content type (articles, reviews, tutorials, etc.)
 */

namespace Pugo\Actions\Content;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class GetContentAction
{
    public function __construct(
        private string $contentDir
    ) {}

    /**
     * Get a single content item
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

        try {
            $content = file_get_contents($fullPath);
            $parsed = $this->parseFrontmatter($content);

            $parts = explode('/', $relativePath);

            return ActionResult::success(
                message: 'Content loaded',
                data: [
                    'path' => $fullPath,
                    'relative_path' => $relativePath,
                    'section' => $parts[0] ?? '',
                    'category' => count($parts) > 2 ? $parts[1] : null,
                    'slug' => basename($relativePath, '.md'),
                    'frontmatter' => $parsed['frontmatter'],
                    'body' => $parsed['body'],
                    'modified' => filemtime($fullPath)
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error loading content: ' . $e->getMessage());
        }
    }

    private function parseFrontmatter(string $content): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            return ['frontmatter' => [], 'body' => $content];
        }

        $yaml = $matches[1];
        $body = $matches[2];
        $frontmatter = [];
        $currentKey = null;

        foreach (explode("\n", $yaml) as $line) {
            if (trim($line) === '') continue;

            if (preg_match('/^\s*-\s*(.*)$/', $line, $m)) {
                if ($currentKey) {
                    if (!isset($frontmatter[$currentKey]) || !is_array($frontmatter[$currentKey])) {
                        $frontmatter[$currentKey] = [];
                    }
                    $frontmatter[$currentKey][] = trim($m[1], '"\'');
                }
                continue;
            }

            if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/', $line, $m)) {
                $currentKey = $m[1];
                $value = trim($m[2], '"\'');
                if ($value !== '') {
                    $frontmatter[$currentKey] = $value;
                }
            }
        }

        return ['frontmatter' => $frontmatter, 'body' => $body];
    }
}

