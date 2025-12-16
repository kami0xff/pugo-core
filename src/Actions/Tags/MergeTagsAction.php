<?php
/**
 * Pugo - Merge Tags Action
 * 
 * Merges one tag into another across all content files.
 * The source tag is replaced with the target tag.
 */

namespace Pugo\Actions\Tags;

require_once dirname(__DIR__) . '/ActionResult.php';

use Pugo\Actions\ActionResult;

final readonly class MergeTagsAction
{
    public function __construct(
        private string $contentDir
    ) {}

    /**
     * Merge source tag into target tag
     */
    public function handle(string $sourceTag, string $targetTag): ActionResult
    {
        $sourceTag = trim($sourceTag);
        $targetTag = trim($targetTag);

        if (empty($sourceTag) || empty($targetTag)) {
            return ActionResult::failure('Both source and target tag names are required');
        }

        if ($sourceTag === $targetTag) {
            return ActionResult::failure('Source and target tags must be different');
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
                $hasSource = false;
                $hasTarget = false;

                foreach ($tags as $tag) {
                    if (strtolower(trim($tag)) === strtolower($sourceTag)) {
                        $hasSource = true;
                    }
                    if (strtolower(trim($tag)) === strtolower($targetTag)) {
                        $hasTarget = true;
                    }
                }

                if ($hasSource) {
                    // Remove source tag
                    $tags = array_filter($tags, fn($t) => strtolower(trim($t)) !== strtolower($sourceTag));

                    // Add target tag if not already present
                    if (!$hasTarget) {
                        $tags[] = $targetTag;
                    }

                    $parsed['frontmatter']['tags'] = array_values($tags);

                    $newContent = $this->rebuildContent($parsed);
                    file_put_contents($path, $newContent);
                    
                    $count++;
                    $updatedFiles[] = str_replace($this->contentDir . '/', '', $path);
                }
            }

            return ActionResult::success(
                message: "Merged \"{$sourceTag}\" into \"{$targetTag}\" ({$count} article(s) updated)",
                data: [
                    'count' => $count,
                    'files' => $updatedFiles,
                    'sourceTag' => $sourceTag,
                    'targetTag' => $targetTag
                ]
            );

        } catch (\Exception $e) {
            return ActionResult::failure('Error merging tags: ' . $e->getMessage());
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

