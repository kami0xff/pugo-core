<?php
/**
 * ApiController - AJAX API endpoints
 */

namespace Pugo\Controllers;

class ApiController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/deploy.php';
    }

    /**
     * Handle API requests
     */
    public function handle(): void
    {
        $this->requireAuth();

        $action = $this->get('action', '');

        switch ($action) {
            case 'media':
                $this->handleMedia();
                break;
            case 'build':
                $this->handleBuild();
                break;
            case 'deploy':
                $this->handleDeploy();
                break;
            case 'deploy_status':
                $this->handleDeployStatus();
                break;
            case 'upload':
                $this->handleUpload();
                break;
            case 'delete':
                $this->handleDelete();
                break;
            default:
                $this->json(['success' => false, 'error' => 'Unknown action'], 400);
        }
    }

    /**
     * List media files
     */
    private function handleMedia(): void
    {
        $path = $this->get('path', '');
        $media = get_media_files($path);
        $this->json($media);
    }

    /**
     * Build Hugo site
     */
    private function handleBuild(): void
    {
        $result = build_hugo();
        $this->json($result);
    }

    /**
     * Deploy site
     */
    private function handleDeploy(): void
    {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
            return;
        }

        $this->validateCsrf();

        // Build first
        $buildResult = build_hugo();
        if (!$buildResult['success']) {
            $this->json([
                'success' => false,
                'error' => 'Build failed',
                'output' => $buildResult['output']
            ], 400);
            return;
        }

        // Then deploy
        $message = $this->post('message', 'Deploy: ' . date('Y-m-d H:i'));
        $deployResult = deploy_site($message);

        $this->json([
            'success' => $deployResult['success'],
            'output' => $deployResult['output'],
            'build_output' => $buildResult['output']
        ]);
    }

    /**
     * Get deployment status
     */
    private function handleDeployStatus(): void
    {
        $status = get_deploy_status();
        $this->json($status);
    }

    /**
     * Handle file upload
     */
    private function handleUpload(): void
    {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
            return;
        }

        $this->validateCsrf();

        if (empty($_FILES['file'])) {
            $this->json(['success' => false, 'error' => 'No file uploaded'], 400);
            return;
        }

        $file = $_FILES['file'];
        $targetDir = $this->post('directory', 'articles');
        $targetPath = IMAGES_DIR . '/' . $targetDir;

        // Create directory if needed
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        // Validate file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = array_merge($this->config['allowed_images'], $this->config['allowed_videos']);

        if (!in_array($ext, $allowed)) {
            $this->json(['success' => false, 'error' => 'File type not allowed'], 400);
            return;
        }

        if ($file['size'] > $this->config['max_upload_size']) {
            $this->json(['success' => false, 'error' => 'File too large'], 400);
            return;
        }

        // Generate unique filename
        $filename = generate_slug(pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = $filename . '-' . time() . '.' . $ext;
        $destination = $targetPath . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $this->json([
                'success' => true,
                'path' => '/images/' . $targetDir . '/' . $filename,
                'filename' => $filename
            ]);
        } else {
            $this->json(['success' => false, 'error' => 'Failed to upload file'], 500);
        }
    }

    /**
     * Handle file deletion
     */
    private function handleDelete(): void
    {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
            return;
        }

        $this->validateCsrf();

        $path = $this->post('path', '');
        if (!$path) {
            $this->json(['success' => false, 'error' => 'Path required'], 400);
            return;
        }

        $fullPath = STATIC_DIR . $path;
        $realPath = realpath($fullPath);

        // Security check
        if (!$realPath || strpos($realPath, realpath(IMAGES_DIR)) !== 0) {
            $this->json(['success' => false, 'error' => 'Invalid path'], 400);
            return;
        }

        if (file_exists($realPath) && unlink($realPath)) {
            $this->json(['success' => true, 'message' => 'File deleted']);
        } else {
            $this->json(['success' => false, 'error' => 'Failed to delete file'], 500);
        }
    }
}
