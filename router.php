<?php
/**
 * Pugo Router
 * 
 * This file routes requests to the appropriate page in core/pages/.
 * Place this as index.php in your admin folder or include it from there.
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

// Map URL to page files
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

// Check if page exists
if (!isset($page_map[$page])) {
    // Try direct file access for backward compatibility
    $direct_file = PUGO_CORE_ROOT . '/pages/' . $page . '.php';
    if (file_exists($direct_file)) {
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

require $page_file;

