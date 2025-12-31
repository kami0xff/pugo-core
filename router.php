<?php
/**
 * Pugo Router
 * 
 * Routes requests to either:
 * 1. New Controller classes (Controller-View architecture)
 * 2. Legacy page files in core/pages/ (backward compatible)
 * 
 * Usage in your admin/index.php:
 *   <?php require __DIR__ . '/core/router.php';
 */

// Define admin root (where config.php and content_types/ live)
if (!defined('PUGO_ADMIN_ROOT')) {
    define('PUGO_ADMIN_ROOT', dirname(__DIR__));
}

// Define core root
if (!defined('PUGO_CORE_ROOT')) {
    define('PUGO_CORE_ROOT', __DIR__);
}

// Define HUGO_ADMIN for includes
if (!defined('HUGO_ADMIN')) {
    define('HUGO_ADMIN', true);
}

/**
 * Controller routes (new architecture)
 * 
 * Format: 'route' => ['ControllerClass', 'method']
 * 
 * Enable these routes gradually as you migrate pages to controllers.
 * Uncomment a route to use the controller instead of legacy page file.
 * 
 * Architecture:
 * - Controllers: Handle HTTP, delegate to Actions/Services
 * - Actions: Single-purpose business operations (already exist in Actions/)
 * - Services: Orchestrate multiple Actions for complex workflows (Services/)
 */
$controller_routes = [
    // === Dashboard ===
    // 'dashboard'     => ['DashboardController', 'index'],
    
    // === Content Management ===
    // 'content'       => ['ContentController', 'index'],
    // 'content/edit'  => ['ContentController', 'edit'],
    // 'content/new'   => ['ContentController', 'create'],
    // 'content/delete'=> ['ContentController', 'delete'],
    
    // === Media Library ===
    // 'media'         => ['MediaController', 'index'],
    // 'media/upload'  => ['MediaController', 'upload'],
    // 'media/delete'  => ['MediaController', 'delete'],
    // 'media/folder'  => ['MediaController', 'createFolder'],
    
    // === Taxonomy (Tags/Categories) ===
    // 'taxonomy'      => ['TaxonomyController', 'index'],
    // 'taxonomy/rename' => ['TaxonomyController', 'rename'],
    // 'taxonomy/merge'  => ['TaxonomyController', 'merge'],
    // 'taxonomy/delete' => ['TaxonomyController', 'delete'],
    
    // === Data Files ===
    // 'data'          => ['DataController', 'index'],
    // 'data/edit'     => ['DataController', 'edit'],
    // 'data/create'   => ['DataController', 'create'],
    // 'data/delete'   => ['DataController', 'delete'],
    
    // === Authentication ===
    // 'login'         => ['AuthController', 'login'],
    // 'logout'        => ['AuthController', 'logout'],
    
    // === API ===
    // 'api'           => ['ApiController', 'handle'],
];

/**
 * Legacy page map (backward compatible)
 * These pages work as before - single PHP files with mixed logic/view
 */
$page_map = [
    ''           => 'index.php',
    'index'      => 'index.php',
    'dashboard'  => 'index.php',
    'articles'   => 'articles.php',
    'edit'       => 'edit.php',
    'new'        => 'new.php',
    'media'      => 'media.php',
    'scanner'    => 'scanner.php',
    'taxonomy'   => 'taxonomy.php',
    'settings'   => 'settings.php',
    'help'       => 'help.php',
    'data'       => 'data.php',
    'login'      => 'login.php',
    'logout'     => 'logout.php',
    'api'        => 'api.php',
    'pages'      => 'pages.php',
    'page-edit'  => 'page-edit.php',
    'templates'  => 'templates.php',
    'template-edit' => 'template-edit.php',
    'components' => 'components.php',
];

// Determine which page to load
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($request_uri, PHP_URL_PATH);
$path = trim($path, '/');

// Remove admin/ prefix if present
$path = preg_replace('#^admin/?#', '', $path);

// Remove .php extension if present
$path = preg_replace('#\.php$#', '', $path);

// Get the page name
$page = $path ?: 'index';

/**
 * Try controller route first (new architecture)
 */
if (isset($controller_routes[$page])) {
    [$controllerName, $method] = $controller_routes[$page];
    $controllerClass = "\\Pugo\\Controllers\\{$controllerName}";
    
    // Autoload controller
    require_once PUGO_CORE_ROOT . '/Controllers/BaseController.php';
    require_once PUGO_CORE_ROOT . "/Controllers/{$controllerName}.php";
    
    // Load config
    $config = require PUGO_ADMIN_ROOT . '/config.php';
    
    // Set current page for sidebar
    $GLOBALS['pugo_current_page'] = explode('/', $page)[0];
    
    // Instantiate and call method
    $controller = new $controllerClass();
    $controller->$method();
    exit;
}

/**
 * Fall back to legacy page files
 */

// Check if page exists in map
if (!isset($page_map[$page])) {
    // Try direct file access for backward compatibility
    $direct_file = PUGO_CORE_ROOT . '/pages/' . $page . '.php';
    if (file_exists($direct_file)) {
        // Set the current page name for sidebar highlighting
        $GLOBALS['pugo_current_page'] = $page;
        require $direct_file;
        exit;
    }
    
    http_response_code(404);
    echo "Page not found: " . htmlspecialchars($page);
    exit;
}

// Load the page
$page_file = PUGO_CORE_ROOT . '/pages/' . $page_map[$page];

if (!file_exists($page_file)) {
    http_response_code(500);
    echo "Page file missing: " . htmlspecialchars($page_map[$page]);
    exit;
}

// Set the current page name for sidebar highlighting (used in header.php)
$GLOBALS['pugo_current_page'] = basename($page_map[$page], '.php');

require $page_file;

