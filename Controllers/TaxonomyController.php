<?php
/**
 * TaxonomyController - Tags and categories management
 * 
 * Uses Tags Actions directly for tag operations.
 */

namespace Pugo\Controllers;

use Pugo\Actions\Tags\ListTagsAction;
use Pugo\Actions\Tags\RenameTagAction;
use Pugo\Actions\Tags\MergeTagsAction;
use Pugo\Actions\Tags\DeleteTagAction;

class TaxonomyController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        require_once dirname(__DIR__) . '/includes/functions.php';
    }

    /**
     * List all tags with counts
     */
    public function index(): void
    {
        $this->requireAuth();

        $contentDir = $this->getContentDir();
        
        // Use Action directly
        $action = new ListTagsAction($contentDir);
        $result = $action->handle();

        $tags = $result->success ? $result->data['tags'] : [];
        
        // Sort by count descending
        usort($tags, fn($a, $b) => $b['count'] - $a['count']);

        // Get categories (section _index.md files)
        $categories = $this->getCategories();

        $this->render('taxonomy/index', [
            'pageTitle' => 'Taxonomy',
            'tags' => $tags,
            'categories' => $categories,
            'totalTags' => count($tags),
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    /**
     * Rename a tag across all content (POST)
     */
    public function rename(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $oldName = $this->post('old_name', '');
        $newName = $this->post('new_name', '');

        if (!$oldName || !$newName) {
            $this->json(['success' => false, 'error' => 'Both old and new names required'], 400);
        }

        $contentDir = $this->getContentDir();
        $action = new RenameTagAction($contentDir);
        $result = $action->handle($oldName, $newName);

        $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    /**
     * Merge multiple tags into one (POST)
     */
    public function merge(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $sourceTags = $this->post('source_tags', '');
        $targetTag = $this->post('target_tag', '');

        if (!$sourceTags || !$targetTag) {
            $this->json(['success' => false, 'error' => 'Source tags and target tag required'], 400);
        }

        // Parse source tags (comma-separated or JSON array)
        $sources = is_string($sourceTags) ? 
            (json_decode($sourceTags, true) ?? explode(',', $sourceTags)) : 
            $sourceTags;

        $contentDir = $this->getContentDir();
        $action = new MergeTagsAction($contentDir);
        $result = $action->handle($sources, $targetTag);

        $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    /**
     * Delete a tag from all content (POST)
     */
    public function delete(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $tagName = $this->post('tag', '');

        if (!$tagName) {
            $this->json(['success' => false, 'error' => 'Tag name required'], 400);
        }

        $contentDir = $this->getContentDir();
        $action = new DeleteTagAction($contentDir);
        $result = $action->handle($tagName);

        $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    /**
     * Get categories (section index pages)
     */
    private function getCategories(): array
    {
        $contentDir = $this->getContentDir();
        $categories = [];

        if (!is_dir($contentDir)) {
            return $categories;
        }

        $dirs = new \DirectoryIterator($contentDir);
        foreach ($dirs as $dir) {
            if ($dir->isDot() || !$dir->isDir()) {
                continue;
            }

            $indexPath = $dir->getPathname() . '/_index.md';
            $name = $dir->getFilename();

            if (file_exists($indexPath)) {
                $content = file_get_contents($indexPath);
                $frontmatter = $this->parseFrontmatter($content);
                $categories[] = [
                    'name' => $name,
                    'title' => $frontmatter['title'] ?? ucfirst($name),
                    'description' => $frontmatter['description'] ?? '',
                    'path' => $indexPath
                ];
            } else {
                $categories[] = [
                    'name' => $name,
                    'title' => ucfirst($name),
                    'description' => '',
                    'path' => null
                ];
            }
        }

        return $categories;
    }

    /**
     * Simple frontmatter parser
     */
    private function parseFrontmatter(string $content): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
            return [];
        }

        $yaml = $matches[1];
        $result = [];

        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/', $line, $m)) {
                $result[$m[1]] = trim($m[2], '"\'');
            }
        }

        return $result;
    }
}

