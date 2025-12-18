<?php
/**
 * Pugo - List Tags Action
 * 
 * Retrieves all tags from content files with article counts.
 */

namespace Pugo\Actions\Tags;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class ListTagsAction
{
    public function __construct(
        private string $contentDir
    ) {}

    /**
     * Get all tags with their article counts
     * 
     * @return ActionResult Contains ['tags' => [...]] on success
     */
    public function handle(): ActionResult
    {
        if (!is_dir($this->contentDir)) {
            return ActionResult::failure('Content directory not found');
        }

        $tags = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->contentDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'md') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                $frontmatter = $this->parseFrontmatter($content);

                if (!empty($frontmatter['tags']) && is_array($frontmatter['tags'])) {
                    foreach ($frontmatter['tags'] as $tag) {
                        $tag = trim($tag);
                        if (!isset($tags[$tag])) {
                            $tags[$tag] = ['count' => 0, 'articles' => []];
                        }
                        $tags[$tag]['count']++;
                        $tags[$tag]['articles'][] = [
                            'title' => $frontmatter['title'] ?? basename($file->getPathname(), '.md'),
                            'path' => $file->getPathname()
                        ];
                    }
                }
            }

            // Sort by count descending
            uasort($tags, fn($a, $b) => $b['count'] - $a['count']);

            return ActionResult::success(
                message: 'Found ' . count($tags) . ' tags',
                data: ['tags' => $tags]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error scanning content: ' . $e->getMessage());
        }
    }

    /**
     * Get just tag names as a simple array (for autocomplete)
     */
    public function handleSimple(): ActionResult
    {
        $result = $this->handle();
        
        if (!$result->success) {
            return $result;
        }

        $tagNames = array_keys($result->data['tags']);

        return ActionResult::success(
            message: 'Found ' . count($tagNames) . ' tags',
            data: $tagNames
        );
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

            // Array item
            if (preg_match('/^\s*-\s*(.*)$/', $line, $m)) {
                if ($currentKey) {
                    if (!isset($result[$currentKey]) || !is_array($result[$currentKey])) {
                        $result[$currentKey] = [];
                    }
                    $result[$currentKey][] = trim($m[1], '"\'');
                }
                continue;
            }

            // Key: value
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

