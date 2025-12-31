<?php
/**
 * ContentService - Orchestrates complex content operations
 * 
 * Use this service when:
 * - An operation requires multiple Actions
 * - An operation needs business logic beyond a single Action
 * - You need to coordinate side effects (e.g., rebuild Hugo after save)
 * 
 * For simple operations, use Actions directly in Controllers.
 */

namespace Pugo\Services;

use Pugo\Actions\ActionResult;
use Pugo\Actions\Content\GetContentAction;
use Pugo\Actions\Content\ListContentAction;
use Pugo\Actions\Content\UpdateContentAction;
use Pugo\Actions\Content\CreateContentAction;
use Pugo\Actions\Content\DeleteContentAction;
use Pugo\Actions\Build\BuildHugoAction;

class ContentService
{
    private string $contentDir;
    private string $hugoRoot;
    private array $config;

    public function __construct(string $contentDir, string $hugoRoot, array $config)
    {
        $this->contentDir = $contentDir;
        $this->hugoRoot = $hugoRoot;
        $this->config = $config;
    }

    /**
     * Get content with additional metadata (content type, translations, etc.)
     */
    public function getContentWithMetadata(string $relativePath): ActionResult
    {
        $action = new GetContentAction($this->contentDir);
        $result = $action->handle($relativePath);

        if (!$result->success) {
            return $result;
        }

        $data = $result->data;
        
        // Detect content type
        $isIndex = basename($relativePath) === '_index.md';
        $contentType = $this->detectContentType($data['section'], $data['frontmatter'], $isIndex);
        $data['content_type'] = $contentType;
        $data['content_type_info'] = $this->getContentTypeInfo($contentType);
        $data['is_index'] = $isIndex;

        // Get translation status if translationKey exists
        if (!empty($data['frontmatter']['translationKey'])) {
            $data['translations'] = $this->getTranslationStatus($data['frontmatter']['translationKey']);
        }

        return ActionResult::success($result->message, $data);
    }

    /**
     * List content with content type detection
     */
    public function listContentWithTypes(?string $section = null, ?string $contentType = null): ActionResult
    {
        $action = new ListContentAction($this->contentDir);
        $result = $action->handle($section);

        if (!$result->success) {
            return $result;
        }

        $items = $result->data['items'];

        // Add content type info to each item
        foreach ($items as &$item) {
            $detected = $this->detectContentType($item['section'], $item['frontmatter']);
            $item['content_type'] = $detected;
            $item['type_info'] = $this->getContentTypeInfo($detected);
        }
        unset($item);

        // Filter by content type if specified
        if ($contentType) {
            $items = array_filter($items, fn($i) => $i['content_type'] === $contentType);
            $items = array_values($items); // Re-index
        }

        return ActionResult::success(
            'Found ' . count($items) . ' items',
            ['items' => $items, 'count' => count($items)]
        );
    }

    /**
     * Update content and rebuild Hugo site
     */
    public function updateAndRebuild(string $relativePath, array $frontmatter, ?string $body = null): ActionResult
    {
        // First update the content
        $updateAction = new UpdateContentAction($this->contentDir);
        $updateResult = $updateAction->handle($relativePath, $frontmatter, $body);

        if (!$updateResult->success) {
            return $updateResult;
        }

        // Then rebuild Hugo
        $buildAction = new BuildHugoAction($this->hugoRoot);
        $buildResult = $buildAction->handle();

        // Return combined result
        $data = $updateResult->data;
        $data['build'] = [
            'success' => $buildResult->success,
            'message' => $buildResult->message
        ];

        $message = $buildResult->success 
            ? 'Content saved and site rebuilt successfully'
            : 'Content saved but site rebuild had issues';

        return ActionResult::success($message, $data);
    }

    /**
     * Create content and rebuild Hugo site
     */
    public function createAndRebuild(string $section, string $slug, array $frontmatter, string $body): ActionResult
    {
        $createAction = new CreateContentAction($this->contentDir);
        $createResult = $createAction->handle($section, $slug, $frontmatter, $body);

        if (!$createResult->success) {
            return $createResult;
        }

        // Rebuild Hugo
        $buildAction = new BuildHugoAction($this->hugoRoot);
        $buildResult = $buildAction->handle();

        $data = $createResult->data;
        $data['build'] = [
            'success' => $buildResult->success,
            'message' => $buildResult->message
        ];

        return ActionResult::success(
            $buildResult->success ? 'Content created and site rebuilt' : 'Content created but rebuild had issues',
            $data
        );
    }

    /**
     * Delete content and rebuild Hugo site
     */
    public function deleteAndRebuild(string $relativePath): ActionResult
    {
        $deleteAction = new DeleteContentAction($this->contentDir);
        $deleteResult = $deleteAction->handle($relativePath);

        if (!$deleteResult->success) {
            return $deleteResult;
        }

        // Rebuild Hugo
        $buildAction = new BuildHugoAction($this->hugoRoot);
        $buildResult = $buildAction->handle();

        return ActionResult::success(
            $buildResult->success ? 'Content deleted and site rebuilt' : 'Content deleted but rebuild had issues',
            ['build_success' => $buildResult->success]
        );
    }

    /**
     * Get active content types with counts
     */
    public function getActiveContentTypes(): array
    {
        $action = new ListContentAction($this->contentDir);
        $result = $action->handle();

        if (!$result->success) {
            return [];
        }

        $types = [];
        foreach ($result->data['items'] as $item) {
            $type = $this->detectContentType($item['section'], $item['frontmatter']);
            if (!isset($types[$type])) {
                $types[$type] = [
                    'info' => $this->getContentTypeInfo($type),
                    'count' => 0
                ];
            }
            $types[$type]['count']++;
        }

        return $types;
    }

    /**
     * Detect content type from section and frontmatter
     */
    private function detectContentType(string $section, array $frontmatter, bool $isIndex = false): string
    {
        // Use detect_content_type function if available (from content-types.php)
        if (function_exists('detect_content_type')) {
            return detect_content_type($section, $frontmatter, $isIndex);
        }

        // Fallback: check frontmatter type field
        if (!empty($frontmatter['type'])) {
            return $frontmatter['type'];
        }

        // Default based on index status
        return $isIndex ? 'page' : 'article';
    }

    /**
     * Get content type info
     */
    private function getContentTypeInfo(string $type): array
    {
        if (function_exists('get_content_type')) {
            return get_content_type($type);
        }

        // Fallback defaults
        return [
            'name' => ucfirst($type),
            'plural' => ucfirst($type) . 's',
            'icon' => 'file-text',
            'color' => '#6b7280'
        ];
    }

    /**
     * Get translation status for a translation key
     */
    private function getTranslationStatus(string $translationKey): array
    {
        if (function_exists('get_translation_status')) {
            return get_translation_status($translationKey);
        }
        return [];
    }
}

