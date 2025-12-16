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
