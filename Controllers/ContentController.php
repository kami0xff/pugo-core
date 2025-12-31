<?php
/**
 * ContentController - Handles all content CRUD operations
 * 
 * Architecture:
 * - Uses Actions directly for simple operations
 * - Uses ContentService for complex orchestrations (multiple actions + side effects)
 * 
 * Pugo supports multiple content types (articles, reviews, tutorials, etc.)
 * This controller handles them all generically.
 */

namespace Pugo\Controllers;

use Pugo\Actions\ActionResult;
use Pugo\Actions\Content\GetContentAction;
use Pugo\Actions\Content\ListContentAction;
use Pugo\Actions\Content\UpdateContentAction;
use Pugo\Actions\Content\CreateContentAction;
use Pugo\Actions\Content\DeleteContentAction;
use Pugo\Services\ContentService;

class ContentController extends BaseController
{
    private ContentService $contentService;

    public function __construct()
    {
        parent::__construct();
        
        // Load required includes for helper functions
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/content-types.php';
        require_once dirname(__DIR__) . '/includes/dynamic-fields.php';
        
        // Initialize service for complex operations
        $this->contentService = new ContentService(
            $this->getContentDir(),
            HUGO_ROOT,
            $this->config
        );
    }

    /**
     * List all content
     * 
     * Uses: ListContentAction directly + helper functions for metadata
     */
    public function index(): void
    {
        $this->requireAuth();

        $currentSection = $this->get('section');
        $currentType = $this->get('type');
        $search = $this->get('search', '');

        // Use service to get content with type detection
        $result = $this->contentService->listContentWithTypes($currentSection, $currentType);
        
        $articles = $result->success ? $result->data['items'] : [];

        // Apply search filter (simple logic stays in controller)
        if ($search) {
            $articles = array_filter($articles, function($article) use ($search) {
                $title = $article['frontmatter']['title'] ?? '';
                $desc = $article['frontmatter']['description'] ?? '';
                return stripos($title, $search) !== false || stripos($desc, $search) !== false;
            });
        }

        // Get sections for sidebar/filter
        $sections = get_sections_with_types($this->currentLang);
        
        // Get active content types for summary cards
        $activeTypes = $this->contentService->getActiveContentTypes();
        
        // Get all articles for total count
        $allArticles = get_articles($this->currentLang);

        // Build page title
        if ($currentType && isset($activeTypes[$currentType])) {
            $pageTitle = $activeTypes[$currentType]['info']['plural'];
        } elseif ($currentSection && isset($sections[$currentSection])) {
            $pageTitle = $sections[$currentSection]['name'];
        } else {
            $pageTitle = 'All Content';
        }

        $this->render('content/index', [
            'pageTitle' => $pageTitle,
            'articles' => $articles,
            'allArticles' => $allArticles,
            'sections' => $sections,
            'activeTypes' => $activeTypes,
            'currentSection' => $currentSection,
            'currentType' => $currentType,
            'search' => $search,
        ]);
    }

    /**
     * Edit existing content
     * 
     * GET: Uses GetContentAction to load content
     * POST: Uses ContentService.updateAndRebuild() for save + build
     */
    public function edit(): void
    {
        $this->requireAuth();

        $file = $this->get('file', '');
        if (!$file) {
            $this->redirect('articles.php');
        }

        // Handle form submission first
        if ($this->isPost()) {
            $this->handleUpdate($file);
            return;
        }

        // Construct full path
        $contentDir = $this->getContentDir();
        $filePath = $contentDir . '/' . $file;

        // Check if file exists
        if (!file_exists($filePath)) {
            $this->flash('error', 'Article not found');
            $this->redirect('articles.php?lang=' . $this->currentLang);
        }

        // Parse the file
        $content = file_get_contents($filePath);
        $parsed = parse_frontmatter($content);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        // Parse path for section info
        $pathParts = explode('/', $file);
        $section = $pathParts[0] ?? '';
        $category = count($pathParts) > 2 ? $pathParts[1] : null;

        // Detect content type
        $isIndex = (basename($file) === '_index.md');
        $contentType = detect_content_type($section, $frontmatter, $isIndex);
        $contentTypeInfo = get_content_type($contentType);

        // Get section info
        $sectionsList = get_sections_with_counts($this->currentLang);
        $sectionColor = $sectionsList[$section]['color'] ?? '#666';
        $sectionName = $sectionsList[$section]['name'] ?? ucfirst($section);

        // Get translation status
        $translationKey = $frontmatter['translationKey'] ?? null;
        $translations = $translationKey ? get_translation_status($translationKey) : [];

        // Get template info
        $templateInfo = detect_hugo_template($section, $frontmatter, $isIndex);
        
        // Get all articles for related selection
        $allContent = get_all_articles_for_selection($this->currentLang);

        $this->render('content/edit', [
            'pageTitle' => 'Edit: ' . ($frontmatter['title'] ?? basename($file, '.md')),
            'file' => $file,
            'filePath' => $filePath,
            'frontmatter' => $frontmatter,
            'body' => $body,
            'section' => $section,
            'category' => $category,
            'isIndex' => $isIndex,
            'contentType' => $contentType,
            'contentTypeInfo' => $contentTypeInfo,
            'sectionColor' => $sectionColor,
            'sectionName' => $sectionName,
            'sectionsList' => $sectionsList,
            'translations' => $translations,
            'translationKey' => $translationKey,
            'templateInfo' => $templateInfo,
            'allContent' => $allContent,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
            'warning' => $this->getFlash('warning'),
        ]);
    }

    /**
     * Handle content update (POST)
     */
    private function handleUpdate(string $file): void
    {
        $this->validateCsrf();

        // Detect content type early (needed for dynamic fields)
        $pathParts = explode('/', $file);
        $section = $pathParts[0] ?? '';
        $isIndex = (basename($file) === '_index.md');
        
        // Read current frontmatter to detect type
        $filePath = $this->getContentDir() . '/' . $file;
        if (file_exists($filePath)) {
            $parsed = parse_frontmatter(file_get_contents($filePath));
            $contentType = detect_content_type($section, $parsed['frontmatter'], $isIndex);
        } else {
            $contentType = 'article';
        }

        // Build new frontmatter
        $frontmatter = [
            'title' => $this->post('title', ''),
            'description' => $this->post('description', ''),
            'author' => $this->post('author', 'XloveCam Team'),
            'date' => $this->post('date', date('Y-m-d')),
            'lastmod' => date('Y-m-d'),
        ];

        // Optional fields
        if ($image = $this->post('image')) {
            $frontmatter['image'] = $image;
        }
        if ($keywords = $this->post('keywords')) {
            $decoded = json_decode($keywords, true);
            if ($decoded) $frontmatter['keywords'] = $decoded;
        }
        if ($tags = $this->post('tags')) {
            $decoded = json_decode($tags, true);
            if ($decoded) $frontmatter['tags'] = $decoded;
        }
        if ($translationKey = $this->post('translationKey')) {
            $frontmatter['translationKey'] = $translationKey;
        }
        if ($related = $this->post('related')) {
            $decoded = json_decode($related, true);
            if ($decoded && is_array($decoded) && count($decoded) > 0) {
                $frontmatter['related'] = $decoded;
            }
        }
        if ($weight = $this->post('weight')) {
            if (is_numeric($weight)) {
                $frontmatter['weight'] = (int)$weight;
            }
        }
        if ($this->post('draft')) {
            $frontmatter['draft'] = true;
        }

        // Parse dynamic content type fields
        $dynamicFields = parse_content_type_form_data($contentType, $_POST);
        $frontmatter = array_merge($frontmatter, $dynamicFields);

        // Preserve content type in frontmatter if it has custom fields
        $typeInfo = get_content_type($contentType);
        if (!empty($typeInfo['fields']) && $contentType !== 'article' && $contentType !== 'page') {
            $frontmatter['type'] = $contentType;
        }

        $body = $this->post('body', '');

        // Save the article
        if (save_article($filePath, $frontmatter, $body)) {
            // Auto-rebuild Hugo
            $buildResult = build_hugo();
            if ($buildResult['success']) {
                $this->flash('success', 'Article saved and site rebuilt successfully!');
            } else {
                $this->flash('success', 'Article saved successfully!');
                $this->flash('warning', 'Hugo rebuild had warnings.');
            }
        } else {
            $this->flash('error', 'Failed to save article');
        }

        $this->redirect('edit.php?file=' . urlencode($file) . '&lang=' . $this->currentLang);
    }

    /**
     * Build frontmatter array from POST data
     * Simple data mapping - stays in controller
     */
    private function buildFrontmatterFromPost(): array
    {
        $frontmatter = [
            'title' => $this->post('title', ''),
            'description' => $this->post('description', ''),
            'author' => $this->post('author', 'XloveCam Team'),
            'date' => $this->post('date', date('Y-m-d')),
        ];

        // Optional fields
        if ($image = $this->post('image')) {
            $frontmatter['image'] = $image;
        }

        if ($keywords = $this->post('keywords')) {
            $decoded = json_decode($keywords, true);
            if ($decoded) $frontmatter['keywords'] = $decoded;
        }

        if ($tags = $this->post('tags')) {
            $decoded = json_decode($tags, true);
            if ($decoded) $frontmatter['tags'] = $decoded;
        }

        if ($translationKey = $this->post('translationKey')) {
            $frontmatter['translationKey'] = $translationKey;
        }

        if ($related = $this->post('related')) {
            $decoded = json_decode($related, true);
            if ($decoded && is_array($decoded) && count($decoded) > 0) {
                $frontmatter['related'] = $decoded;
            }
        }

        if ($weight = $this->post('weight')) {
            if (is_numeric($weight)) {
                $frontmatter['weight'] = (int)$weight;
            }
        }

        if ($this->post('draft')) {
            $frontmatter['draft'] = true;
        }

        return $frontmatter;
    }

    /**
     * Create new content
     * 
     * GET: Show form
     * POST: Save new content
     */
    public function create(): void
    {
        $this->requireAuth();

        $pageTitle = 'New Article';
        $currentSection = $this->get('section', '');

        // Translation mode
        $translateFrom = $this->get('translate_from', '');
        $sourceLang = $this->get('source_lang', 'en');
        $targetLang = $this->get('target_lang', $this->currentLang);

        $prefillData = null;
        if ($translateFrom) {
            $sourceContentDir = get_content_dir_for_lang($sourceLang);
            $sourcePath = $sourceContentDir . '/' . $translateFrom;

            if (file_exists($sourcePath)) {
                $content = file_get_contents($sourcePath);
                $parsed = parse_frontmatter($content);
                $prefillData = [
                    'frontmatter' => $parsed['frontmatter'],
                    'body' => $parsed['body'],
                    'path' => $translateFrom
                ];
                $this->currentLang = $targetLang;
                $pageTitle = 'Translate Article';
            }
        }

        // Handle form submission
        if ($this->isPost()) {
            $this->handleCreate();
            return;
        }

        $sections = get_sections_with_counts($this->currentLang);

        $this->render('content/create', [
            'pageTitle' => $pageTitle,
            'sections' => $sections,
            'currentSection' => $currentSection,
            'prefillData' => $prefillData,
            'translateFrom' => $translateFrom,
            'sourceLang' => $sourceLang,
            'targetLang' => $targetLang,
        ]);
    }

    /**
     * Handle content creation (POST)
     */
    private function handleCreate(): void
    {
        $this->validateCsrf();

        $section = $this->post('section', '');
        $category = $this->post('category', '');
        $slug = generate_slug($this->post('title', 'untitled'));

        // Build path
        $contentDir = $this->getContentDir();
        if ($category) {
            $filePath = $contentDir . '/' . $section . '/' . $category . '/' . $slug . '.md';
        } else {
            $filePath = $contentDir . '/' . $section . '/' . $slug . '.md';
        }

        // Check if file already exists
        if (file_exists($filePath)) {
            $this->flash('error', 'An article with this slug already exists. Please choose a different title.');
            $this->redirect('new.php?section=' . urlencode($section) . '&lang=' . $this->currentLang);
            return;
        }

        // Build frontmatter
        $frontmatter = [
            'title' => $this->post('title', ''),
            'description' => $this->post('description', ''),
            'author' => $this->post('author', 'XloveCam Team'),
            'date' => date('Y-m-d'),
            'lastmod' => date('Y-m-d'),
        ];

        if ($translationKey = $this->post('translationKey')) {
            $frontmatter['translationKey'] = $translationKey;
        }
        if ($this->post('draft')) {
            $frontmatter['draft'] = true;
        }

        $body = $this->post('body', '');

        // Create directory if needed
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Save the file
        if (save_article($filePath, $frontmatter, $body)) {
            // Build Hugo
            $buildResult = build_hugo();
            
            // Determine relative path for edit link
            $relativePath = str_replace($contentDir . '/', '', $filePath);
            
            if ($buildResult['success']) {
                $this->flash('success', 'Article created and site rebuilt successfully!');
            } else {
                $this->flash('success', 'Article created successfully!');
                $this->flash('warning', 'Hugo rebuild had warnings.');
            }
            
            $this->redirect('edit.php?file=' . urlencode($relativePath) . '&lang=' . $this->currentLang);
        } else {
            $this->flash('error', 'Failed to create article');
            $this->redirect('new.php?section=' . urlencode($section) . '&lang=' . $this->currentLang);
        }
    }

    /**
     * Delete content
     * 
     * Uses: ContentService.deleteAndRebuild()
     */
    public function delete(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->redirect('articles.php');
        }

        $this->validateCsrf();

        $file = $this->post('file', '');
        if (!$file) {
            $this->flash('error', 'No file specified');
            $this->redirect('articles.php?lang=' . $this->currentLang);
        }

        $result = $this->contentService->deleteAndRebuild($file);

        if ($result->success) {
            $this->flash('success', 'Content deleted successfully');
        } else {
            $this->flash('error', $result->error);
        }

        $this->redirect('articles.php?lang=' . $this->currentLang);
    }

    /**
     * Simple slugify helper - stays in controller as it's presentation-related
     */
    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }
}

