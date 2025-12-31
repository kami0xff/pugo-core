<?php
/**
 * ApiController - JSON API endpoints for AJAX operations
 * 
 * Handles all AJAX requests from the admin interface.
 * Uses Actions directly - keeps controller thin.
 */

namespace Pugo\Controllers;

use Pugo\Actions\Content\ListContentAction;
use Pugo\Actions\Content\GetContentAction;
use Pugo\Actions\Content\DeleteContentAction;
use Pugo\Actions\Media\ListMediaAction;
use Pugo\Actions\Media\UploadMediaAction;
use Pugo\Actions\Build\BuildHugoAction;
use Pugo\Actions\Build\PublishAction;
use Pugo\Services\ContentService;

class ApiController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        require_once dirname(__DIR__) . '/includes/functions.php';
    }

    /**
     * Route API requests based on action parameter
     */
    public function handle(): void
    {
        $action = $this->get('action', '');

        // Map actions to methods
        $actions = [
            'list_content' => 'listContent',
            'get_content' => 'getContent',
            'delete_content' => 'deleteContent',
            'list_media' => 'listMedia',
            'upload_media' => 'uploadMedia',
            'build' => 'buildSite',
            'deploy' => 'deploySite',
            'deploy_status' => 'deployStatus',
            'search' => 'search',
            'autocomplete_tags' => 'autocompleteTags',
        ];

        if (!isset($actions[$action])) {
            $this->json(['success' => false, 'error' => 'Unknown action'], 400);
        }

        // Check auth for all actions except deploy_status
        if ($action !== 'deploy_status') {
            $this->requireApiAuth();
        }

        $method = $actions[$action];
        $this->$method();
    }

    /**
     * List content items
     */
    private function listContent(): void
    {
        $section = $this->get('section');
        $contentDir = $this->getContentDir();

        $action = new ListContentAction($contentDir);
        $result = $action->handle($section);

        $this->json($result->toArray());
    }

    /**
     * Get single content item
     */
    private function getContent(): void
    {
        $file = $this->get('file', '');

        if (!$file) {
            $this->json(['success' => false, 'error' => 'File path required'], 400);
        }

        $contentDir = $this->getContentDir();
        $action = new GetContentAction($contentDir);
        $result = $action->handle($file);

        $this->json($result->toArray());
    }

    /**
     * Delete content
     */
    private function deleteContent(): void
    {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $file = $this->post('file', '');

        if (!$file) {
            $this->json(['success' => false, 'error' => 'File path required'], 400);
        }

        $contentDir = $this->getContentDir();
        $action = new DeleteContentAction($contentDir);
        $result = $action->handle($file);

        $this->json($result->toArray());
    }

    /**
     * List media files
     */
    private function listMedia(): void
    {
        $path = $this->get('path', '');
        $mediaDir = HUGO_ROOT . '/static';

        $action = new ListMediaAction($mediaDir);
        $result = $action->handle($path);

        $this->json($result->toArray());
    }

    /**
     * Upload media file
     */
    private function uploadMedia(): void
    {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        if (empty($_FILES['file'])) {
            $this->json(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        $path = $this->post('path', '');
        $mediaDir = HUGO_ROOT . '/static';

        $action = new UploadMediaAction($mediaDir);
        $result = $action->handle($_FILES['file'], $path);

        $this->json($result->toArray());
    }

    /**
     * Build Hugo site
     */
    private function buildSite(): void
    {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $action = new BuildHugoAction(HUGO_ROOT);
        $result = $action->handle();

        $this->json($result->toArray());
    }

    /**
     * Deploy site (build + git push)
     */
    private function deploySite(): void
    {
        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $message = $this->post('message', 'Deploy: ' . date('Y-m-d H:i'));

        $action = new PublishAction(HUGO_ROOT);
        $result = $action->handle($message);

        $this->json($result->toArray());
    }

    /**
     * Get deployment status
     */
    private function deployStatus(): void
    {
        // Simple deployment status check
        $configured = false;
        $remoteUrl = null;
        $lastCommit = null;

        // Check if git is configured
        $gitDir = HUGO_ROOT . '/.git';
        if (is_dir($gitDir)) {
            $configured = true;

            // Get remote URL
            $remote = trim(shell_exec('cd ' . escapeshellarg(HUGO_ROOT) . ' && git remote get-url origin 2>/dev/null') ?? '');
            if ($remote) {
                $remoteUrl = $remote;
            }

            // Get last commit
            $lastCommitInfo = shell_exec('cd ' . escapeshellarg(HUGO_ROOT) . ' && git log -1 --format="%s|%ar" 2>/dev/null');
            if ($lastCommitInfo) {
                $parts = explode('|', trim($lastCommitInfo));
                $lastCommit = [
                    'message' => $parts[0] ?? '',
                    'date' => $parts[1] ?? ''
                ];
            }
        }

        $this->json([
            'success' => true,
            'configured' => $configured,
            'remote_url' => $remoteUrl,
            'last_commit' => $lastCommit
        ]);
    }

    /**
     * Search content
     */
    private function search(): void
    {
        $query = $this->get('q', '');

        if (strlen($query) < 2) {
            $this->json(['success' => true, 'data' => ['results' => []]]);
        }

        $contentDir = $this->getContentDir();
        $action = new ListContentAction($contentDir);
        $result = $action->handle();

        if (!$result->success) {
            $this->json($result->toArray());
            return;
        }

        $matches = array_filter($result->data['items'], function($item) use ($query) {
            $title = $item['title'] ?? '';
            $desc = $item['description'] ?? '';
            return stripos($title, $query) !== false || stripos($desc, $query) !== false;
        });

        $this->json([
            'success' => true,
            'data' => ['results' => array_values(array_slice($matches, 0, 10))]
        ]);
    }

    /**
     * Autocomplete tags
     */
    private function autocompleteTags(): void
    {
        $query = $this->get('q', '');
        $tags = get_tag_list($this->currentLang);

        if ($query) {
            $tags = array_filter($tags, fn($tag) => stripos($tag, $query) !== false);
        }

        $this->json([
            'success' => true,
            'data' => ['tags' => array_values(array_slice($tags, 0, 20))]
        ]);
    }

    /**
     * Require API authentication
     */
    private function requireApiAuth(): void
    {
        require_once dirname(__DIR__) . '/includes/auth.php';

        if (!is_authenticated()) {
            $this->json(['success' => false, 'error' => 'Authentication required'], 401);
        }
    }
}

