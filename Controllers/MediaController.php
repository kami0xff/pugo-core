<?php
/**
 * MediaController - Media library management
 * 
 * Uses Media Actions directly for file operations.
 */

namespace Pugo\Controllers;

use Pugo\Actions\Media\ListMediaAction;
use Pugo\Actions\Media\UploadMediaAction;
use Pugo\Actions\Media\DeleteMediaAction;

class MediaController extends BaseController
{
    private string $mediaDir;

    public function __construct()
    {
        parent::__construct();
        $this->mediaDir = HUGO_ROOT . '/static';
        require_once dirname(__DIR__) . '/includes/functions.php';
    }

    /**
     * List media files
     */
    public function index(): void
    {
        $this->requireAuth();

        $path = $this->get('path', '');
        $fullPath = $this->mediaDir . '/' . ltrim($path, '/');

        // Use Action directly (simple operation)
        $action = new ListMediaAction($this->mediaDir);
        $result = $action->handle($path);

        $items = $result->success ? $result->data['items'] : [];
        $folders = $result->success ? ($result->data['folders'] ?? []) : [];

        // Build breadcrumb
        $breadcrumb = $this->buildBreadcrumb($path);

        $this->render('media/index', [
            'pageTitle' => 'Media Library',
            'items' => $items,
            'folders' => $folders,
            'currentPath' => $path,
            'breadcrumb' => $breadcrumb,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    /**
     * Handle file upload (POST)
     */
    public function upload(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $path = $this->post('path', '');

        if (empty($_FILES['file'])) {
            $this->json(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        $action = new UploadMediaAction($this->mediaDir);
        $result = $action->handle($_FILES['file'], $path);

        $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    /**
     * Delete media file (POST)
     */
    public function delete(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $file = $this->post('file', '');

        if (!$file) {
            $this->json(['success' => false, 'error' => 'No file specified'], 400);
        }

        $action = new DeleteMediaAction($this->mediaDir);
        $result = $action->handle($file);

        $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    /**
     * Create folder (POST)
     */
    public function createFolder(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $path = $this->post('path', '');
        $name = $this->post('name', '');

        if (!$name) {
            $this->json(['success' => false, 'error' => 'Folder name required'], 400);
        }

        // Sanitize folder name
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        $fullPath = $this->mediaDir . '/' . ltrim($path, '/') . '/' . $name;

        if (file_exists($fullPath)) {
            $this->json(['success' => false, 'error' => 'Folder already exists'], 400);
        }

        if (mkdir($fullPath, 0755, true)) {
            $this->json(['success' => true, 'message' => 'Folder created']);
        } else {
            $this->json(['success' => false, 'error' => 'Failed to create folder'], 500);
        }
    }

    /**
     * Build breadcrumb navigation
     */
    private function buildBreadcrumb(string $path): array
    {
        $parts = array_filter(explode('/', $path));
        $breadcrumb = [['name' => 'Media', 'path' => '']];
        
        $currentPath = '';
        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            $breadcrumb[] = ['name' => $part, 'path' => ltrim($currentPath, '/')];
        }

        return $breadcrumb;
    }
}

