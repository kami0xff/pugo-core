<?php
/**
 * DashboardController - Admin dashboard/homepage
 * 
 * Shows overview statistics and recent activity.
 * Uses Actions directly for simple data fetching.
 */

namespace Pugo\Controllers;

use Pugo\Actions\Content\ListContentAction;

class DashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/content-types.php';
    }

    /**
     * Dashboard index - show overview
     */
    public function index(): void
    {
        $this->requireAuth();

        // Get sections with content counts
        $sections = get_sections_with_counts($this->currentLang);
        
        // Get recent content using Action directly (simple operation)
        $contentDir = $this->getContentDir();
        $listAction = new ListContentAction($contentDir);
        $result = $listAction->handle();
        
        $allContent = $result->success ? $result->data['items'] : [];
        $totalContent = count($allContent);
        
        // Get recent items (last 5)
        $recentContent = array_slice($allContent, 0, 5);
        
        // Count drafts
        $draftCount = count(array_filter($allContent, fn($item) => $item['draft'] ?? false));
        
        // Get content type distribution
        $contentTypes = [];
        foreach ($allContent as $item) {
            $type = detect_content_type($item['section'], $item['frontmatter']);
            if (!isset($contentTypes[$type])) {
                $contentTypes[$type] = ['info' => get_content_type($type), 'count' => 0];
            }
            $contentTypes[$type]['count']++;
        }

        // Calculate stats per language
        $languageStats = [];
        foreach ($this->config['languages'] as $lang => $langConfig) {
            $langDir = pugo_get_content_dir_for_lang($lang);
            $langAction = new ListContentAction($langDir);
            $langResult = $langAction->handle();
            $languageStats[$lang] = [
                'name' => $langConfig['name'],
                'flag' => $langConfig['flag'],
                'count' => $langResult->success ? $langResult->data['count'] : 0
            ];
        }

        $this->render('dashboard/index', [
            'pageTitle' => 'Dashboard',
            'sections' => $sections,
            'totalContent' => $totalContent,
            'draftCount' => $draftCount,
            'recentContent' => $recentContent,
            'contentTypes' => $contentTypes,
            'languageStats' => $languageStats,
            'sectionCount' => count($sections),
        ]);
    }
}

