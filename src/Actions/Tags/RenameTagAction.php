<?php
/**
 * Pugo - Rename Tag Action
 * 
 * Renames a tag across all content files.
 */

namespace Pugo\Actions\Tags;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class RenameTagAction
{
    public function __construct(
        private string $contentDir
    ) {}

    /**
     * Rename a tag in all articles
     */
    public function handle(string $oldTag, string $newTag): ActionResult
    {
        $oldTag = trim($oldTag);
        $newTag = trim($newTag);

        if (empty($oldTag) || empty($newTag)) {
            return ActionResult::failure('Both old and new tag names are required');
        }

        if ($oldTag === $newTag) {
            return ActionResult::failure('New tag name must be different from old name');
        }

        if (!is_dir($this->contentDir)) {
            return ActionResult::failure('Content directory not found');
        }

        $count = 0;
        $updatedFiles = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->contentDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'md') {
                    continue;
                }

                $path = $file->getPathname();
                $content = file_get_contents($path);
                $parsed = $this->parseFrontmatter($content);

                if (empty($parsed['frontmatter']['tags']) || !is_array($parsed['frontmatter']['tags'])) {
                    continue;
                }

                $tags = $parsed['frontmatter']['tags'];
                $found = false;

                foreach ($tags as $i => $tag) {
                    if (strtolower(trim($tag)) === strtolower($oldTag)) {
                        $tags[$i] = $newTag;
                        $found = true;
                    }
                }

                if ($found) {
                    // Remove duplicates in case newTag already existed
                    $tags = array_values(array_unique($tags));
                    $parsed['frontmatter']['tags'] = $tags;

                    $newContent = $this->rebuildContent($parsed);
                    file_put_contents($path, $newContent);
                    
                    $count++;
                    $updatedFiles[] = str_replace($this->contentDir . '/', '', $path);
                }
            }

            return ActionResult::success(
                message: "Tag renamed from \"{$oldTag}\" to \"{$newTag}\" in {$count} article(s)",
                data: [
                    'count' => $count,
                    'files' => $updatedFiles,
                    'oldTag' => $oldTag,
                    'newTag' => $newTag
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error renaming tag: ' . $e->getMessage());
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

    private function rebuildContent(array $parsed): string
    {
        $yaml = '';
        foreach ($parsed['frontmatter'] as $key => $value) {
            if (is_array($value)) {
                $yaml .= "{$key}:\n";
                foreach ($value as $item) {
                    $yaml .= "  - \"{$item}\"\n";
                }
            } elseif (is_bool($value)) {
                $yaml .= "{$key}: " . ($value ? 'true' : 'false') . "\n";
            } elseif (is_numeric($value)) {
                $yaml .= "{$key}: {$value}\n";
            } else {
                $yaml .= "{$key}: \"{$value}\"\n";
            }
        }

        return "---\n{$yaml}---\n{$parsed['body']}";
    }
}

