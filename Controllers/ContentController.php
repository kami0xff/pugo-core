<?php
/**
 * ContentController - Handles all content CRUD operations
 * 
 * Pugo supports multiple content types (articles, reviews, tutorials, etc.)
 * This controller handles them all generically.
 * 
 * Responsible for:
 * - Listing content
 * - Editing content
 * - Creating new content
 * - Deleting content
 */

namespace Pugo\Controllers;

class ContentController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        
        // Load required includes
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/content-types.php';
        require_once dirname(__DIR__) . '/includes/dynamic-fields.php';
    }
    
    /**
     * List all content
     */
    public function index(): void
    {
        $this->requireAuth();
        
        $currentSection = $this->get('section');
        $currentType = $this->get('type');
        $search = $this->get('search', '');
        
        // Get sections with content type info
        $sections = get_sections_with_types($this->currentLang);
        
        // Get articles
        $articles = get_articles($this->currentLang, $currentSection);
        
        // Add content type info to each article
        foreach ($articles as &$article) {
            $article['content_type'] = detect_content_type($article['section'], $article['frontmatter']);
            $article['type_info'] = get_content_type($article['content_type']);
        }
        unset($article);
        
        // Filter by content type if specified
        if ($currentType) {
            $articles = array_filter($articles, fn($a) => $a['content_type'] === $currentType);
        }
        
        // Filter by search
        if ($search) {
            $articles = array_filter($articles, function($article) use ($search) {
                $title = $article['frontmatter']['title'] ?? '';
                $desc = $article['frontmatter']['description'] ?? '';
                return stripos($title, $search) !== false || stripos($desc, $search) !== false;
            });
        }
        
        // Get content types that have items
        $activeTypes = [];
        $allArticles = get_articles($this->currentLang);
        foreach ($allArticles as $art) {
            $type = detect_content_type($art['section'], $art['frontmatter']);
            if (!isset($activeTypes[$type])) {
                $activeTypes[$type] = ['info' => get_content_type($type), 'count' => 0];
            }
            $activeTypes[$type]['count']++;
        }
        
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
            'sections' => $sections,
            'activeTypes' => $activeTypes,
            'currentSection' => $currentSection,
            'currentType' => $currentType,
            'search' => $search,
        ]);
    }
    
    /**
     * Edit existing content
     */
    public function edit(): void
    {
        $this->requireAuth();
        
        $file = $this->get('file', '');
        
        if (!$file) {
            $this->redirect('articles.php');
        }
        
        // Build file path
        $contentDir = $this->getContentDir();
        $filePath = $contentDir . '/' . $file;
        
        if (!file_exists($filePath)) {
            $this->flash('error', 'Content not found');
            $this->redirect('articles.php?lang=' . $this->currentLang);
        }
        
        // Parse the file
        $fileContent = file_get_contents($filePath);
        $parsed = parse_frontmatter($fileContent);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];
        
        // Detect content type
        $pathParts = explode('/', $file);
        $section = $pathParts[0] ?? '';
        $category = count($pathParts) > 2 ? $pathParts[1] : null;
        $isIndex = (basename($file) === '_index.md');
        $contentType = detect_content_type($section, $frontmatter, $isIndex);
        $contentTypeInfo = get_content_type($contentType);
        
        // Handle form submission
        if ($this->isPost()) {
            $this->handleEditSubmission($filePath, $contentType);
        }
        
        // Get additional data for the view
        $sections = get_sections_with_counts($this->currentLang);
        $sectionColor = $sections[$section]['color'] ?? '#666';
        $sectionName = $sections[$section]['name'] ?? ucfirst($section);
        
        $translationKey = $frontmatter['translationKey'] ?? null;
        $translations = $translationKey ? get_translation_status($translationKey) : [];
        
        $templateInfo = detect_hugo_template($section, $frontmatter, $isIndex);
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
            'translations' => $translations,
            'templateInfo' => $templateInfo,
            'allContent' => $allContent,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
            'warning' => $this->getFlash('warning'),
        ]);
    }
    
    /**
     * Handle edit form submission
     */
    private function handleEditSubmission(string $filePath, string $contentType): void
    {
        $this->validateCsrf();
        
        // Build new frontmatter
        $newFrontmatter = [
            'title' => $this->post('title', ''),
            'description' => $this->post('description', ''),
            'author' => $this->post('author', 'XloveCam Team'),
            'date' => $this->post('date', date('Y-m-d')),
            'lastmod' => date('Y-m-d'),
        ];
        
        // Optional fields
        if ($image = $this->post('image')) {
            $newFrontmatter['image'] = $image;
        }
        
        if ($keywords = $this->post('keywords')) {
            $decoded = json_decode($keywords, true);
            if ($decoded) $newFrontmatter['keywords'] = $decoded;
        }
        
        if ($tags = $this->post('tags')) {
            $decoded = json_decode($tags, true);
            if ($decoded) $newFrontmatter['tags'] = $decoded;
        }
        
        if ($translationKey = $this->post('translationKey')) {
            $newFrontmatter['translationKey'] = $translationKey;
        }
        
        if ($related = $this->post('related')) {
            $decoded = json_decode($related, true);
            if ($decoded && is_array($decoded) && count($decoded) > 0) {
                $newFrontmatter['related'] = $decoded;
            }
        }
        
        if ($weight = $this->post('weight')) {
            if (is_numeric($weight)) {
                $newFrontmatter['weight'] = (int)$weight;
            }
        }
        
        if ($this->post('draft')) {
            $newFrontmatter['draft'] = true;
        }
        
        // Parse dynamic content type fields
        $dynamicFields = parse_content_type_form_data($contentType, $_POST);
        $newFrontmatter = array_merge($newFrontmatter, $dynamicFields);
        
        // Preserve content type if it has custom fields
        $typeInfo = get_content_type($contentType);
        if (!empty($typeInfo['fields']) && $contentType !== 'article' && $contentType !== 'page') {
            $newFrontmatter['type'] = $contentType;
        }
        
        $newBody = $this->post('body', '');
        
        if (save_article($filePath, $newFrontmatter, $newBody)) {
            // Auto-rebuild Hugo
            $buildResult = build_hugo();
            if ($buildResult['success']) {
                $this->flash('success', 'Content saved and site rebuilt successfully!');
            } else {
                $this->flash('success', 'Content saved successfully!');
                $this->flash('warning', 'Hugo rebuild had warnings.');
            }
        } else {
            $this->flash('error', 'Failed to save content');
        }
        
        $this->redirect('edit.php?file=' . urlencode($this->get('file')) . '&lang=' . $this->currentLang);
    }
    
    /**
     * Create new content
     */
    public function create(): void
    {
        $this->requireAuth();
        
        // Get parameters for translation or new content
        $translateFrom = $this->get('translate_from');
        $sourceLang = $this->get('source_lang');
        $targetLang = $this->get('target_lang');
        $section = $this->get('section');
        
        $prefillData = [];
        
        if ($translateFrom && $sourceLang) {
            // Loading from existing content for translation
            $sourceDir = pugo_get_content_dir_for_lang($sourceLang);
            $sourcePath = $sourceDir . '/' . $translateFrom;
            
            if (file_exists($sourcePath)) {
                $fileContent = file_get_contents($sourcePath);
                $parsed = parse_frontmatter($fileContent);
                $prefillData = [
                    'frontmatter' => $parsed['frontmatter'],
                    'body' => $parsed['body'],
                    'sourceFile' => $translateFrom,
                ];
            }
        }
        
        $sections = get_sections_with_types($this->currentLang);
        
        $this->render('content/create', [
            'pageTitle' => 'New Content',
            'sections' => $sections,
            'currentSection' => $section,
            'prefillData' => $prefillData,
            'targetLang' => $targetLang,
        ]);
    }
    
    /**
     * Delete content
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
        
        $contentDir = $this->getContentDir();
        $filePath = $contentDir . '/' . $file;
        
        if (file_exists($filePath) && unlink($filePath)) {
            $this->flash('success', 'Content deleted successfully');
            
            // Rebuild site
            build_hugo();
        } else {
            $this->flash('error', 'Failed to delete content');
        }
        
        $this->redirect('articles.php?lang=' . $this->currentLang);
    }
}
