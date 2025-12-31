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

        $currentPath = $this->get('path', '');
        $uploadMessage = null;

        // Handle POST actions
        if ($this->isPost()) {
            $uploadMessage = $this->handlePostAction($currentPath);
        }

        // Get media files using helper function
        $media = get_media_files($currentPath);

        $this->render('media/index', [
            'pageTitle' => 'Media Library',
            'media' => $media,
            'currentPath' => $currentPath,
            'uploadMessage' => $uploadMessage,
        ]);
    }

    /**
     * Handle POST actions (upload, delete, create folder)
     */
    private function handlePostAction(string &$currentPath): ?array
    {
        $this->validateCsrf();

        // Handle file upload
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $targetDir = $this->post('directory', 'articles');
            $targetPath = IMAGES_DIR . '/' . $targetDir;

            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = array_merge($this->config['allowed_images'], $this->config['allowed_videos']);

            if (!in_array($ext, $allowed)) {
                return ['type' => 'error', 'text' => 'File type not allowed'];
            }
            
            if ($file['size'] > $this->config['max_upload_size']) {
                return ['type' => 'error', 'text' => 'File too large (max ' . format_size($this->config['max_upload_size']) . ')'];
            }

            $filename = generate_slug(pathinfo($file['name'], PATHINFO_FILENAME));
            $filename = $filename . '-' . time() . '.' . $ext;
            $destination = $targetPath . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $currentPath = $targetDir;
                return ['type' => 'success', 'text' => 'File uploaded successfully'];
            }
            return ['type' => 'error', 'text' => 'Failed to upload file'];
        }

        // Handle file deletion
        if ($deletePath = $this->post('delete')) {
            $fullPath = STATIC_DIR . $deletePath;
            $realPath = realpath($fullPath);

            if ($realPath && strpos($realPath, realpath(IMAGES_DIR)) === 0 && file_exists($realPath)) {
                if (unlink($realPath)) {
                    return ['type' => 'success', 'text' => 'File deleted'];
                }
            }
            return ['type' => 'error', 'text' => 'Failed to delete file'];
        }

        // Handle folder creation
        if ($newFolder = $this->post('new_folder')) {
            $folderName = generate_slug($newFolder);
            $parent = $this->post('parent', '');
            $newFolderPath = IMAGES_DIR . ($parent ? '/' . $parent : '') . '/' . $folderName;

            if (!is_dir($newFolderPath)) {
                if (mkdir($newFolderPath, 0755, true)) {
                    $currentPath = ($parent ? $parent . '/' : '') . $folderName;
                    return ['type' => 'success', 'text' => 'Folder created'];
                }
                return ['type' => 'error', 'text' => 'Failed to create folder'];
            }
            return ['type' => 'error', 'text' => 'Folder already exists'];
        }

        return null;
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

