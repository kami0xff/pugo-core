<?php
/**
 * Pugo Core - Bootstrap
 * 
 * Initializes the Pugo admin system.
 * This file is part of the updatable core.
 */

// Version info
define('PUGO_VERSION', '1.0.0');

// Prevent direct access
if (!defined('PUGO_ROOT')) {
    die('Direct access not allowed');
}

// Define core paths
define('PUGO_CORE', PUGO_ROOT . '/core');
define('PUGO_VIEWS', PUGO_CORE . '/views');
define('PUGO_CUSTOM', PUGO_ROOT . '/custom');

// Hugo paths - these can be overridden in config
if (!defined('HUGO_ROOT')) {
    define('HUGO_ROOT', dirname(PUGO_ROOT));
}
if (!defined('CONTENT_DIR')) {
    define('CONTENT_DIR', HUGO_ROOT . '/content');
}
if (!defined('STATIC_DIR')) {
    define('STATIC_DIR', HUGO_ROOT . '/static');
}
if (!defined('DATA_DIR')) {
    define('DATA_DIR', HUGO_ROOT . '/data');
}
if (!defined('IMAGES_DIR')) {
    define('IMAGES_DIR', STATIC_DIR . '/images');
}

// Legacy constant for backward compatibility
if (!defined('HUGO_ADMIN')) {
    define('HUGO_ADMIN', true);
}
if (!defined('ADMIN_ROOT')) {
    define('ADMIN_ROOT', PUGO_ROOT);
}

// Load core includes
require_once PUGO_CORE . '/includes/functions.php';
require_once PUGO_CORE . '/includes/ContentType.php';
require_once PUGO_CORE . '/includes/auth.php';

// Load Actions
require_once PUGO_CORE . '/Actions/bootstrap.php';

/**
 * Find a view file, checking custom folder first
 * 
 * @param string $name View name (without .view.php extension)
 * @return string|null Full path to view file, or null if not found
 */
function pugo_find_view(string $name): ?string {
    // Check custom views first (allows overriding)
    $custom = PUGO_CUSTOM . "/views/{$name}.view.php";
    if (file_exists($custom)) {
        return $custom;
    }
    
    // Fall back to core views
    $core = PUGO_VIEWS . "/{$name}.view.php";
    if (file_exists($core)) {
        return $core;
    }
    
    return null;
}

/**
 * Render a view with data
 * 
 * @param string $name View name
 * @param array $data Variables to extract into view scope
 */
function pugo_view(string $name, array $data = []): void {
    $view = pugo_find_view($name);
    
    if (!$view) {
        http_response_code(404);
        echo "View not found: {$name}";
        return;
    }
    
    // Make data available as variables
    extract($data);
    
    // Also make config globally available
    global $config;
    
    require $view;
}

/**
 * Get Pugo configuration merged with defaults
 */
function pugo_config(): array {
    static $config = null;
    
    if ($config === null) {
        $config_file = PUGO_ROOT . '/config.php';
        
        if (file_exists($config_file)) {
            $config = require $config_file;
        } else {
            $config = [];
        }
        
        // Merge with defaults
        $config = array_merge([
            'site_name' => 'My Site',
            'default_language' => 'en',
            'languages' => [
                'en' => ['name' => 'English', 'flag' => '🇬🇧', 'content_dir' => 'content'],
            ],
            'auth' => [
                'enabled' => true,
                'username' => 'admin',
                'password_hash' => password_hash('admin', PASSWORD_DEFAULT),
                'session_lifetime' => 86400,
            ],
        ], $config);
    }
    
    return $config;
}

/**
 * Simple router for Pugo admin
 */
function pugo_route(): string {
    $page = $_GET['page'] ?? 'dashboard';
    
    // Sanitize page name
    $page = preg_replace('/[^a-z0-9_-]/', '', strtolower($page));
    
    // Map routes to views
    $routes = [
        'dashboard' => 'dashboard',
        'articles' => 'articles',
        'edit' => 'edit',
        'new' => 'new',
        'media' => 'media',
        'scanner' => 'scanner',
        'taxonomy' => 'taxonomy',
        'settings' => 'settings',
        'help' => 'help',
        'data' => 'data',
        'login' => 'login',
        'logout' => 'logout',
    ];
    
    return $routes[$page] ?? 'dashboard';
}

/**
 * Check if current request is authenticated
 */
function pugo_check_auth(): bool {
    $config = pugo_config();
    
    if (!($config['auth']['enabled'] ?? true)) {
        return true;
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return !empty($_SESSION['pugo_authenticated']);
}

/**
 * Require authentication, redirect to login if not authenticated
 */
function pugo_require_auth(): void {
    if (!pugo_check_auth()) {
        header('Location: ?page=login');
        exit;
    }
}

