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

        // GET: Load content using service (includes metadata)
        $result = $this->contentService->getContentWithMetadata($file);

        if (!$result->success) {
            $this->flash('error', $result->error);
            $this->redirect('articles.php?lang=' . $this->currentLang);
        }

        $data = $result->data;

        // Get additional view data
        $sections = get_sections_with_counts($this->currentLang);
        $sectionColor = $sections[$data['section']]['color'] ?? '#666';
        $sectionName = $sections[$data['section']]['name'] ?? ucfirst($data['section']);
        $templateInfo = detect_hugo_template($data['section'], $data['frontmatter'], $data['is_index']);
        $allContent = get_all_articles_for_selection($this->currentLang);

        $this->render('content/edit', [
            'pageTitle' => 'Edit: ' . ($data['frontmatter']['title'] ?? basename($file, '.md')),
            'file' => $file,
            'filePath' => $data['path'],
            'frontmatter' => $data['frontmatter'],
            'body' => $data['body'],
            'section' => $data['section'],
            'category' => $data['category'],
            'isIndex' => $data['is_index'],
            'contentType' => $data['content_type'],
            'contentTypeInfo' => $data['content_type_info'],
            'sectionColor' => $sectionColor,
            'sectionName' => $sectionName,
            'translations' => $data['translations'] ?? [],
            'templateInfo' => $templateInfo,
            'allContent' => $allContent,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
            'warning' => $this->getFlash('warning'),
        ]);
    }

    /**
     * Handle content update (POST)
     * 
     * Uses: ContentService.updateAndRebuild() for atomic save + rebuild
     */
    private function handleUpdate(string $file): void
    {
        $this->validateCsrf();

        // Build frontmatter from POST data
        $frontmatter = $this->buildFrontmatterFromPost();

        // Get content type for dynamic fields
        $contentType = $this->post('content_type', 'article');
        $dynamicFields = parse_content_type_form_data($contentType, $_POST);
        $frontmatter = array_merge($frontmatter, $dynamicFields);

        // Preserve content type if it has custom fields
        $typeInfo = get_content_type($contentType);
        if (!empty($typeInfo['fields']) && $contentType !== 'article' && $contentType !== 'page') {
            $frontmatter['type'] = $contentType;
        }

        $body = $this->post('body', '');

        // Use service for update + rebuild
        $result = $this->contentService->updateAndRebuild($file, $frontmatter, $body);

        if ($result->success) {
            $this->flash('success', $result->message);
            if (!$result->data['build']['success']) {
                $this->flash('warning', 'Hugo rebuild had warnings');
            }
        } else {
            $this->flash('error', $result->error);
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
     * POST: Uses ContentService.createAndRebuild()
     */
    public function create(): void
    {
        $this->requireAuth();

        if ($this->isPost()) {
            $this->handleCreate();
            return;
        }

        // Handle prefill from translation source
        $prefillData = [];
        $translateFrom = $this->get('translate_from');
        $sourceLang = $this->get('source_lang');

        if ($translateFrom && $sourceLang) {
            $sourceDir = pugo_get_content_dir_for_lang($sourceLang);
            $action = new GetContentAction($sourceDir);
            $result = $action->handle($translateFrom);

            if ($result->success) {
                $prefillData = [
                    'frontmatter' => $result->data['frontmatter'],
                    'body' => $result->data['body'],
                    'sourceFile' => $translateFrom,
                ];
            }
        }

        $sections = get_sections_with_types($this->currentLang);

        $this->render('content/create', [
            'pageTitle' => 'New Content',
            'sections' => $sections,
            'currentSection' => $this->get('section'),
            'prefillData' => $prefillData,
            'targetLang' => $this->get('target_lang'),
        ]);
    }

    /**
     * Handle content creation (POST)
     */
    private function handleCreate(): void
    {
        $this->validateCsrf();

        $section = $this->post('section', 'blog');
        $slug = $this->post('slug', '');

        if (!$slug) {
            $slug = $this->slugify($this->post('title', 'untitled'));
        }

        $frontmatter = $this->buildFrontmatterFromPost();
        $body = $this->post('body', '');

        $result = $this->contentService->createAndRebuild($section, $slug, $frontmatter, $body);

        if ($result->success) {
            $this->flash('success', $result->message);
            $this->redirect('edit.php?file=' . urlencode($result->data['relative_path']) . '&lang=' . $this->currentLang);
        } else {
            $this->flash('error', $result->error);
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
