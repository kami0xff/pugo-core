<?php
/**
 * Pugo - Update Content Action
 * 
 * Updates an existing content item's frontmatter and/or body.
 * Works with any content type (articles, reviews, tutorials, etc.)
 */

namespace Pugo\Actions\Content;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class UpdateContentAction
{
    public function __construct(
        private string $contentDir
    ) {}

    /**
     * Update a content item
     * 
     * @param string $relativePath Path relative to content directory
     * @param array $frontmatter New frontmatter values (merged with existing)
     * @param string|null $body New body content (null to keep existing)
     */
    public function handle(string $relativePath, array $frontmatter, ?string $body = null): ActionResult
    {
        if (empty($relativePath)) {
            return ActionResult::failure('Content path is required');
        }

        $fullPath = $this->contentDir . '/' . ltrim($relativePath, '/');

        if (!file_exists($fullPath)) {
            return ActionResult::failure("Content not found: {$relativePath}");
        }

        try {
            // Load existing content
            $content = file_get_contents($fullPath);
            $parsed = $this->parseFrontmatter($content);

            // Merge frontmatter
            $newFrontmatter = array_merge($parsed['frontmatter'], $frontmatter);

            // Clean up empty values
            $newFrontmatter = array_filter($newFrontmatter, fn($v) => $v !== '' && $v !== null && $v !== []);

            // Update lastmod
            $newFrontmatter['lastmod'] = date('Y-m-d');

            // Use new body or keep existing
            $newBody = $body ?? $parsed['body'];

            // Rebuild content
            $newContent = $this->rebuildContent($newFrontmatter, $newBody);

            // Save
            file_put_contents($fullPath, $newContent);

            return ActionResult::success(
                message: 'Content updated successfully',
                data: [
                    'path' => $fullPath,
                    'relative_path' => $relativePath,
                    'frontmatter' => $newFrontmatter
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error updating content: ' . $e->getMessage());
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

    private function rebuildContent(array $frontmatter, string $body): string
    {
        $yaml = '';
        
        // Define preferred order for common frontmatter keys
        $order = ['title', 'description', 'author', 'date', 'lastmod', 'draft', 'image', 'keywords', 'tags', 'related', 'translationKey', 'weight'];
        
        // Output in preferred order first
        foreach ($order as $key) {
            if (isset($frontmatter[$key])) {
                $yaml .= $this->formatYamlValue($key, $frontmatter[$key]);
                unset($frontmatter[$key]);
            }
        }
        
        // Output remaining keys (custom fields for specific content types)
        foreach ($frontmatter as $key => $value) {
            $yaml .= $this->formatYamlValue($key, $value);
        }

        return "---\n{$yaml}---\n{$body}";
    }

    private function formatYamlValue(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $yaml = "{$key}:\n";
            foreach ($value as $item) {
                $yaml .= "  - \"{$item}\"\n";
            }
            return $yaml;
        }
        
        if (is_bool($value)) {
            return "{$key}: " . ($value ? 'true' : 'false') . "\n";
        }
        
        if (is_numeric($value) && !str_contains((string)$value, '-')) {
            return "{$key}: {$value}\n";
        }
        
        return "{$key}: \"{$value}\"\n";
    }
}

