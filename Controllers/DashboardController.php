<?php
/**
 * DashboardController - Main dashboard/overview page
 */

namespace Pugo\Controllers;

class DashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        
        // Load required includes
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/content-types.php';
    }

    /**
     * Dashboard index - show overview stats
     */
    public function index(): void
    {
        $this->requireAuth();

        // Get sections with content types
        $sections = get_sections_with_types($this->currentLang);
        $totalArticles = array_sum(array_column($sections, 'count'));

        // Get recent articles
        $recentArticles = get_articles($this->currentLang);
        
        // Add content type to recent articles
        foreach ($recentArticles as &$article) {
            $article['content_type'] = detect_content_type($article['section'], $article['frontmatter']);
            $article['type_info'] = get_content_type($article['content_type']);
        }
        unset($article);
        
        $recentArticles = array_slice($recentArticles, 0, 10);

        // Get content stats by type
        $contentByType = get_content_stats_by_type($this->currentLang);

        // Count translations per language
        $translationStats = [];
        foreach ($this->config['languages'] as $lang => $langConfig) {
            $contentDir = get_content_dir_for_lang($lang);
            $translationStats[$lang] = is_dir($contentDir) ? count(get_articles($lang)) : 0;
        }

        // Get standalone pages count
        $pagesCount = $this->countStandalonePages();

        $this->render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'sections' => $sections,
            'totalArticles' => $totalArticles,
            'pagesCount' => $pagesCount,
            'contentByType' => $contentByType,
            'translationStats' => $translationStats,
            'recentArticles' => $recentArticles,
        ]);
    }

    /**
     * Count standalone pages (pages with _index.md but no child articles)
     */
    private function countStandalonePages(): int
    {
        $pagesCount = 0;
        $contentDir = $this->getContentDir();

        if (!is_dir($contentDir)) {
            return 0;
        }

        foreach (scandir($contentDir) as $item) {
            if ($item[0] === '.' || !is_dir($contentDir . '/' . $item)) {
                continue;
            }

            if (file_exists($contentDir . '/' . $item . '/_index.md')) {
                // Check if it's not a section (no child .md files other than _index.md)
                $hasChildren = false;
                foreach (scandir($contentDir . '/' . $item) as $child) {
                    if ($child !== '_index.md' && pathinfo($child, PATHINFO_EXTENSION) === 'md') {
                        $hasChildren = true;
                        break;
                    }
                }
                if (!$hasChildren) {
                    $pagesCount++;
                }
            }
        }

        return $pagesCount;
    }
}
