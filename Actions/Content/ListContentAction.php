<?php
/**
 * Pugo - List Content Action
 * 
 * Retrieves all content items from a content directory.
 * Works with any content type (articles, reviews, tutorials, etc.)
 */

namespace Pugo\Actions\Content;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class ListContentAction
{
    public function __construct(
        private string $contentDir
    ) {}

    /**
     * Get all content items with metadata
     * 
     * @param string|null $section Filter by section (e.g., 'users', 'models')
     * @param bool $draftsOnly Show only drafts
     */
    public function handle(?string $section = null, bool $draftsOnly = false): ActionResult
    {
        if (!is_dir($this->contentDir)) {
            return ActionResult::failure('Content directory not found');
        }

        $items = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->contentDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'md') {
                    continue;
                }

                // Skip _index.md files
                if (basename($file->getPathname()) === '_index.md') {
                    continue;
                }

                $path = $file->getPathname();
                $relativePath = str_replace($this->contentDir . '/', '', $path);
                $parts = explode('/', $relativePath);
                $itemSection = $parts[0] ?? '';

                // Filter by section if specified
                if ($section !== null && $itemSection !== $section) {
                    continue;
                }

                $content = file_get_contents($path);
                $frontmatter = $this->parseFrontmatter($content);

                $isDraft = ($frontmatter['draft'] ?? false) === true || ($frontmatter['draft'] ?? '') === 'true';

                // Filter drafts if specified
                if ($draftsOnly && !$isDraft) {
                    continue;
                }

                $items[] = [
                    'path' => $path,
                    'relative_path' => $relativePath,
                    'section' => $itemSection,
                    'category' => $parts[1] ?? null,
                    'slug' => '/' . str_replace('.md', '', $relativePath),
                    'title' => $frontmatter['title'] ?? basename($path, '.md'),
                    'description' => $frontmatter['description'] ?? '',
                    'author' => $frontmatter['author'] ?? 'Unknown',
                    'date' => $frontmatter['date'] ?? null,
                    'lastmod' => $frontmatter['lastmod'] ?? null,
                    'draft' => $isDraft,
                    'tags' => $frontmatter['tags'] ?? [],
                    'keywords' => $frontmatter['keywords'] ?? [],
                    'modified' => filemtime($path),
                    'frontmatter' => $frontmatter
                ];
            }

            // Sort by last modified date, newest first
            usort($items, fn($a, $b) => ($b['modified'] ?? 0) - ($a['modified'] ?? 0));

            return ActionResult::success(
                message: 'Found ' . count($items) . ' content items',
                data: ['items' => $items, 'count' => count($items)]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error listing content: ' . $e->getMessage());
        }
    }

    /**
     * Get content grouped by section
     */
    public function handleGrouped(): ActionResult
    {
        $result = $this->handle();
        
        if (!$result->success) {
            return $result;
        }

        $grouped = [];
        foreach ($result->data['items'] as $item) {
            $section = $item['section'];
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][] = $item;
        }

        return ActionResult::success(
            message: 'Found ' . count($result->data['items']) . ' items in ' . count($grouped) . ' sections',
            data: ['sections' => $grouped, 'count' => count($result->data['items'])]
        );
    }

    /**
     * Get available sections (top-level directories)
     */
    public function handleSections(): ActionResult
    {
        if (!is_dir($this->contentDir)) {
            return ActionResult::failure('Content directory not found');
        }

        $sections = [];
        
        try {
            $dirs = new \DirectoryIterator($this->contentDir);
            
            foreach ($dirs as $dir) {
                if ($dir->isDot() || !$dir->isDir()) {
                    continue;
                }
                
                $name = $dir->getFilename();
                
                // Count items in section
                $count = 0;
                $sectionPath = $dir->getPathname();
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sectionPath, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'md' && basename($file->getPathname()) !== '_index.md') {
                        $count++;
                    }
                }
                
                $sections[] = [
                    'name' => $name,
                    'path' => $sectionPath,
                    'count' => $count
                ];
            }
            
            // Sort alphabetically
            usort($sections, fn($a, $b) => strcasecmp($a['name'], $b['name']));
            
            return ActionResult::success(
                message: 'Found ' . count($sections) . ' sections',
                data: ['sections' => $sections]
            );
            
        } catch (\Exception $e) {
            return ActionResult::failure('Error listing sections: ' . $e->getMessage());
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

            if (preg_match('/^\s*-\s*(.*)$/', $line, $m)) {
                if ($currentKey) {
                    if (!isset($result[$currentKey]) || !is_array($result[$currentKey])) {
                        $result[$currentKey] = [];
                    }
                    $result[$currentKey][] = trim($m[1], '"\'');
                }
                continue;
            }

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

