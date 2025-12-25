<?php
/**
 * Pugo - API Endpoints
 * 
 * All endpoints use the Action pattern for consistent responses.
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../Actions/bootstrap.php';
require __DIR__ . '/../includes/auth.php';

// Must be authenticated
if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// CSRF protection for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../Security/CSRF.php';
    if (!\Pugo\Security\CSRF::validate()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$lang = $_GET['lang'] ?? 'en';

switch ($action) {
    // =========================================================================
    // MEDIA ENDPOINTS
    // =========================================================================
    
    case 'media':
        $path = $_GET['path'] ?? null;
        $result = Actions::listMedia()->handle($path);
        echo $result->toJson();
        break;
        
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        if (!isset($_FILES['file'])) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
            exit;
        }
        
        $result = Actions::uploadMedia()->handle(
            $_FILES['file'],
            $_POST['directory'] ?? 'articles'
        );
        echo $result->toJson();
        break;
        
    case 'delete_media':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $result = Actions::deleteMedia()->handle($_POST['path'] ?? '');
        echo $result->toJson();
        break;

    // =========================================================================
    // BUILD ENDPOINTS
    // =========================================================================
    
    case 'build':
        $result = Actions::buildHugo()->handle(runPagefind: true);
        echo $result->toJson();
        break;
        
    case 'publish':
        $message = $_POST['message'] ?? 'Content update from Pugo';
        $result = Actions::publish()->handle($message);
        echo $result->toJson();
        break;
        
    case 'git_status':
        $result = Actions::publish()->getStatus();
        echo $result->toJson();
        break;

    // =========================================================================
    // DEPLOY ENDPOINTS
    // =========================================================================
    
    case 'deploy_status':
        require_once __DIR__ . '/../includes/deploy.php';
        $status = get_deploy_status();
        echo json_encode($status);
        break;
        
    case 'deploy':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        require_once __DIR__ . '/../includes/deploy.php';
        
        // First build Hugo
        $build_result = build_hugo();
        
        if (!$build_result['success']) {
            echo json_encode([
                'success' => false, 
                'output' => "Build failed:\n" . $build_result['output']
            ]);
            exit;
        }
        
        // Then deploy
        $message = $_POST['message'] ?? 'Deploy: ' . date('Y-m-d H:i');
        $deploy_result = deploy_site($message);
        
        echo json_encode([
            'success' => $deploy_result['success'],
            'output' => "Build:\n" . $build_result['output'] . "\n\nDeploy:\n" . $deploy_result['output']
        ]);
        break;
        
    case 'test_deploy_connection':
        require_once __DIR__ . '/../includes/deploy.php';
        
        $ssh_key = DEPLOY_SSH_KEY;
        if (!file_exists($ssh_key)) {
            echo json_encode([
                'success' => false,
                'output' => 'Deploy key not found at ' . $ssh_key
            ]);
            exit;
        }
        
        // Test SSH connection using the git environment
        $env = get_git_env();
        $cmd = $env . 'ssh -T git@github.com 2>&1';
        exec($cmd, $output, $code);
        
        // GitHub returns code 1 on successful auth (with message "successfully authenticated")
        $success = ($code === 1 && strpos(implode("\n", $output), 'successfully authenticated') !== false);
        
        echo json_encode([
            'success' => $success,
            'output' => implode("\n", $output)
        ]);
        break;

    // =========================================================================
    // TAG ENDPOINTS
    // =========================================================================
    
    case 'tags':
        $result = Actions::listTags($lang)->handle();
        echo $result->toJson();
        break;
        
    case 'tags_simple':
        $result = Actions::listTags($lang)->handleSimple();
        echo $result->toJson();
        break;
        
    case 'rename_tag':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $result = Actions::renameTag($lang)->handle(
            $_POST['old_tag'] ?? '',
            $_POST['new_tag'] ?? ''
        );
        echo $result->toJson();
        break;
        
    case 'merge_tags':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $result = Actions::mergeTags($lang)->handle(
            $_POST['source_tag'] ?? '',
            $_POST['target_tag'] ?? ''
        );
        echo $result->toJson();
        break;
        
    case 'delete_tag':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $result = Actions::deleteTag($lang)->handle($_POST['tag'] ?? '');
        echo $result->toJson();
        break;

    // =========================================================================
    // CONTENT ENDPOINTS (Generic - works for any content type)
    // =========================================================================
    
    case 'content':
    case 'articles': // Alias for backwards compatibility
        $section = $_GET['section'] ?? null;
        $result = Actions::listContent($lang)->handle($section);
        echo $result->toJson();
        break;
        
    case 'content_grouped':
    case 'articles_grouped': // Alias
        $result = Actions::listContent($lang)->handleGrouped();
        echo $result->toJson();
        break;
        
    case 'sections':
        $result = Actions::listContent($lang)->handleSections();
        echo $result->toJson();
        break;
        
    case 'content_item':
    case 'article': // Alias
        $file = $_GET['file'] ?? '';
        $result = Actions::getContent($lang)->handle($file);
        echo $result->toJson();
        break;
        
    case 'update_content':
    case 'update_article': // Alias
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $file = $_POST['file'] ?? '';
        $frontmatter = json_decode($_POST['frontmatter'] ?? '{}', true);
        $body = $_POST['body'] ?? null;
        
        $result = Actions::updateContent($lang)->handle($file, $frontmatter, $body);
        // Auto-rebuild Hugo after update
        if ($result->success) {
            build_hugo();
        }
        echo $result->toJson();
        break;
        
    case 'create_content':
    case 'create_article': // Alias
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $section = $_POST['section'] ?? '';
        $slug = $_POST['slug'] ?? '';
        $category = $_POST['category'] ?? null;
        $frontmatter = json_decode($_POST['frontmatter'] ?? '{}', true);
        $body = $_POST['body'] ?? '';
        
        $result = Actions::createContent($lang)->handle($section, $slug, $frontmatter, $body, $category);
        // Auto-rebuild Hugo after create
        if ($result->success) {
            build_hugo();
        }
        echo $result->toJson();
        break;
        
    case 'delete_content':
    case 'delete_article': // Alias
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            exit;
        }
        
        $result = Actions::deleteContent($lang)->handle($_POST['file'] ?? '');
        // Auto-rebuild Hugo after deletion
        if ($result->success) {
            build_hugo();
        }
        echo $result->toJson();
        break;

    // =========================================================================
    // LEGACY ENDPOINTS (for backward compatibility)
    // =========================================================================
    
    case 'categories':
        // Legacy: Get categories for a section
        $section = $_GET['section'] ?? '';
        $categories = get_categories($section, $lang);
        echo json_encode(['success' => true, 'data' => $categories]);
        break;
        
    case 'trigger_pipeline':
        // Legacy: Trigger GitLab CI/CD pipeline directly
        $result = trigger_pipeline();
        echo json_encode($result);
        break;

    // =========================================================================
    // DEFAULT
    // =========================================================================
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}
