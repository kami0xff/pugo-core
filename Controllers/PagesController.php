<?php
/**
 * PagesController - Standalone pages management
 */

namespace Pugo\Controllers;

class PagesController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/content-types.php';
        require_once dirname(__DIR__) . '/includes/dynamic-fields.php';
    }

    /**
     * List all standalone pages
     */
    public function index(): void
    {
        $this->requireAuth();

        $contentDir = $this->getContentDir();
        $pages = $this->getStandalonePages($contentDir);

        // Get translation stats
        $translationStats = [];
        foreach ($this->config['languages'] as $lang => $langConfig) {
            $langDir = get_content_dir_for_lang($lang);
            $translationStats[$lang] = $this->countPagesInDir($langDir);
        }

        $this->render('pages/index', [
            'pageTitle' => 'Pages',
            'pages' => $pages,
            'translationStats' => $translationStats,
            'contentDir' => $contentDir,
        ]);
    }

    /**
     * Edit a standalone page
     */
    public function edit(): void
    {
        $this->requireAuth();

        $isNew = $this->get('new') !== null;
        $pageSlug = $this->get('page', '');

        // Translation variables
        $translateFrom = $this->get('translate_from', '');
        $sourceLang = $this->get('source_lang', 'en');
        $targetLang = $this->get('target_lang', $this->currentLang);

        $contentDir = $this->getContentDir();

        // Initialize page data
        $pageData = [
            'title' => '',
            'description' => '',
            'layout' => 'default',
            'draft' => false,
            'body' => '',
        ];

        $pageTitle = $isNew ? 'Create New Page' : 'Edit Page';

        // Handle translation prefill
        if ($isNew && $translateFrom) {
            $sourceContentDir = get_content_dir_for_lang($sourceLang);
            $sourcePath = $sourceContentDir . '/' . $translateFrom . '/_index.md';

            if (file_exists($sourcePath)) {
                $sourceContent = file_get_contents($sourcePath);
                $parsed = parse_frontmatter($sourceContent);
                $pageData = array_merge($pageData, $parsed['frontmatter']);
                $pageData['body'] = $parsed['body'];
                $pageSlug = $translateFrom;
                $this->currentLang = $targetLang;
                $contentDir = get_content_dir_for_lang($targetLang);
                $pageTitle = 'Create ' . ($this->config['languages'][$targetLang]['name'] ?? $targetLang) . ' Translation';
            }
        } elseif (!$isNew && $pageSlug) {
            // Load existing page
            $pagePath = $contentDir . '/' . $pageSlug . '/_index.md';
            if (file_exists($pagePath)) {
                $content = file_get_contents($pagePath);
                $parsed = parse_frontmatter($content);
                $pageData = array_merge($pageData, $parsed['frontmatter']);
                $pageData['body'] = $parsed['body'];
                $pageTitle = 'Edit: ' . ($pageData['title'] ?? $pageSlug);
            } else {
                $this->flash('error', "Page not found: {$pageSlug}");
                $this->redirect('pages.php?lang=' . $this->currentLang);
                return;
            }
        }

        // Handle form submission
        if ($this->isPost()) {
            $this->handlePageSave($pageSlug, $isNew, $contentDir);
            return;
        }

        $this->render('pages/edit', [
            'pageTitle' => $pageTitle,
            'pageSlug' => $pageSlug,
            'pageData' => $pageData,
            'isNew' => $isNew,
            'translateFrom' => $translateFrom,
            'sourceLang' => $sourceLang,
            'targetLang' => $targetLang,
            'contentDir' => $contentDir,
        ]);
    }

    /**
     * Handle page save (POST)
     */
    private function handlePageSave(string $pageSlug, bool $isNew, string $contentDir): void
    {
        $this->validateCsrf();

        $slug = $pageSlug ?: generate_slug($this->post('title', 'untitled'));
        $pagePath = $contentDir . '/' . $slug;
        $filePath = $pagePath . '/_index.md';

        // Create directory if needed
        if (!is_dir($pagePath)) {
            mkdir($pagePath, 0755, true);
        }

        $frontmatter = [
            'title' => $this->post('title', ''),
            'description' => $this->post('description', ''),
            'layout' => $this->post('layout', 'default'),
            'date' => $this->post('date', date('Y-m-d')),
            'lastmod' => date('Y-m-d'),
        ];

        if ($this->post('draft')) {
            $frontmatter['draft'] = true;
        }

        $body = $this->post('body', '');

        if (save_article($filePath, $frontmatter, $body)) {
            $buildResult = build_hugo();
            if ($buildResult['success']) {
                $this->flash('success', 'Page saved and site rebuilt successfully!');
            } else {
                $this->flash('success', 'Page saved successfully!');
                $this->flash('warning', 'Hugo rebuild had warnings.');
            }
            $this->redirect('page-edit.php?page=' . urlencode($slug) . '&lang=' . $this->currentLang);
        } else {
            $this->flash('error', 'Failed to save page');
            $this->redirect('page-edit.php?page=' . urlencode($slug) . '&lang=' . $this->currentLang);
        }
    }

    /**
     * Get standalone pages (directories with _index.md but no child articles)
     */
    private function getStandalonePages(string $contentDir): array
    {
        $pages = [];
        if (!is_dir($contentDir)) return $pages;

        foreach (scandir($contentDir) as $item) {
            if ($item[0] === '.' || !is_dir($contentDir . '/' . $item)) continue;

            $indexPath = $contentDir . '/' . $item . '/_index.md';
            if (!file_exists($indexPath)) continue;

            // Check if it has child .md files (making it a section, not a page)
            $hasChildren = false;
            foreach (scandir($contentDir . '/' . $item) as $child) {
                if ($child !== '_index.md' && pathinfo($child, PATHINFO_EXTENSION) === 'md') {
                    $hasChildren = true;
                    break;
                }
            }

            if (!$hasChildren) {
                $content = file_get_contents($indexPath);
                $parsed = parse_frontmatter($content);
                $pages[] = [
                    'slug' => $item,
                    'title' => $parsed['frontmatter']['title'] ?? ucfirst($item),
                    'description' => $parsed['frontmatter']['description'] ?? '',
                    'draft' => $parsed['frontmatter']['draft'] ?? false,
                    'modified' => filemtime($indexPath),
                ];
            }
        }

        // Sort by modified time
        usort($pages, fn($a, $b) => $b['modified'] - $a['modified']);
        return $pages;
    }

    /**
     * Count pages in a directory
     */
    private function countPagesInDir(string $dir): int
    {
        return count($this->getStandalonePages($dir));
    }
}

