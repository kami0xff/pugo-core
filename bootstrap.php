<?php
/**
 * Pugo Core - Bootstrap
 * 
 * Initializes the Pugo admin system.
 * This file is part of the updatable core.
 */

// Version info
define('PUGO_VERSION', '3.0.0-lean');

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

// Load PSR-4 autoloader for Pugo namespace
require_once PUGO_CORE . '/autoload.php';

// Load core includes
require_once PUGO_CORE . '/includes/functions.php';
require_once PUGO_CORE . '/includes/ContentType.php';
require_once PUGO_CORE . '/includes/auth.php';

// Load Actions
require_once PUGO_CORE . '/Actions/bootstrap.php';

// Initialize Registry with defaults
use Pugo\Registry\Registry;

$registry = Registry::getInstance();

// Register default content types
$registry->registerContentType('article', [
    'name' => 'Article',
    'icon' => 'file-text',
    'description' => 'Standard articles, documentation, and blog posts',
    'sections' => ['*'],
    'fields' => [
        'title' => ['type' => 'text', 'required' => true, 'label' => 'Title'],
        'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Description'],
        'date' => ['type' => 'date', 'required' => true, 'label' => 'Date'],
        'draft' => ['type' => 'checkbox', 'label' => 'Draft'],
    ],
]);

// Register default data editors
$registry->registerDataEditor('faqs', [
    'name' => 'FAQ Editor',
    'icon' => 'help-circle',
    'description' => 'Manage frequently asked questions',
    'editor_class' => \Pugo\DataEditors\SimpleListEditor::class,
    'data_file' => 'faqs',
    'data_format' => 'grouped',
    'fields' => [
        'question' => ['type' => 'text', 'label' => 'Question', 'required' => true],
        'answer' => ['type' => 'textarea', 'label' => 'Answer', 'required' => true],
    ],
    'preview_type' => 'qa',
    'item_name' => 'question',
    'item_name_plural' => 'questions',
]);

$registry->registerDataEditor('topics', [
    'name' => 'Topics Editor',
    'icon' => 'book',
    'description' => 'Quick access topics for sections',
    'editor_class' => \Pugo\DataEditors\GroupedListEditor::class,
    'data_file' => 'topics',
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
        'desc' => ['type' => 'text', 'label' => 'Description'],
        'url' => ['type' => 'text', 'label' => 'URL', 'placeholder' => '/section/page/'],
    ],
    'item_name' => 'topic',
    'item_name_plural' => 'topics',
]);

$registry->registerDataEditor('tutorials', [
    'name' => 'Tutorials Editor',
    'icon' => 'video',
    'description' => 'Video tutorials with categories',
    'editor_class' => \Pugo\DataEditors\SimpleListEditor::class,
    'data_file' => 'all_tutorials',
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
        'description' => ['type' => 'textarea', 'label' => 'Description'],
        'duration' => ['type' => 'text', 'label' => 'Duration', 'placeholder' => '3:45'],
        'video' => ['type' => 'url', 'label' => 'Video URL'],
        'category' => ['type' => 'select', 'label' => 'Category', 'options' => []],
        'featured' => ['type' => 'checkbox', 'label' => 'Featured'],
    ],
    'item_name' => 'tutorial',
    'item_name_plural' => 'tutorials',
]);

$registry->registerDataEditor('quickaccess', [
    'name' => 'Quick Access Editor',
    'icon' => 'zap',
    'description' => 'Homepage quick access buttons',
    'editor_class' => \Pugo\DataEditors\SimpleListEditor::class,
    'data_file' => 'quickaccess',
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
        'icon' => ['type' => 'select', 'label' => 'Icon', 'options' => []],
        'glow' => ['type' => 'select', 'label' => 'Glow Effect', 'options' => []],
        'url' => ['type' => 'text', 'label' => 'URL', 'placeholder' => '/section/page/'],
    ],
    'item_name' => 'button',
    'item_name_plural' => 'buttons',
]);

// Register default admin pages
$registry->registerPage('dashboard', [
    'name' => 'Dashboard',
    'icon' => 'home',
    'nav_group' => 'main',
    'nav_order' => 10,
]);

$registry->registerPage('articles', [
    'name' => 'Articles',
    'icon' => 'file-text',
    'nav_group' => 'main',
    'nav_order' => 20,
]);

$registry->registerPage('media', [
    'name' => 'Media',
    'icon' => 'image',
    'nav_group' => 'main',
    'nav_order' => 30,
]);

$registry->registerPage('components', [
    'name' => 'Site Components',
    'icon' => 'grid',
    'nav_group' => 'data',
    'nav_order' => 10,
]);

$registry->registerPage('settings', [
    'name' => 'Settings',
    'icon' => 'settings',
    'nav_group' => 'settings',
    'nav_order' => 100,
]);

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
 * 
 * Uses PugoConfig for consistent config loading from pugo.yaml
 */
function pugo_config(): array {
    static $config = null;
    
    if ($config === null) {
        // Try to use pugo_init.php if available (single source of truth)
        $pugo_init = PUGO_CORE . '/pugo_init.php';
        if (file_exists($pugo_init)) {
            require_once $pugo_init;
            $config = pugo_build_legacy_config();
            return $config;
        }
        
        // Fallback: Load from config.php directly
        $config_file = PUGO_ROOT . '/config.php';
        
        if (file_exists($config_file)) {
            $config = require $config_file;
        } else {
            $config = [];
        }
        
        // Try PugoConfig class for languages
        try {
            require_once PUGO_CORE . '/Config/PugoConfig.php';
            $pugoConfig = \Pugo\Config\PugoConfig::getInstance(HUGO_ROOT);
            $languages = $pugoConfig->languages();
            if (!empty($languages)) {
                $config['languages'] = $languages;
            }
        } catch (\Exception $e) {
            // PugoConfig not available
        }
        
        // Merge with defaults (only for missing values)
        $config = array_merge([
            'site_name' => 'My Site',
            'default_language' => 'en',
            'languages' => [
                'en' => ['name' => 'English', 'flag' => '🇬🇧', 'suffix' => '', 'data_suffix' => ''],
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

/**
 * Get the registry instance
 */
function pugo_registry(): Registry {
    return Registry::getInstance();
}

/**
 * Create a data editor from registry configuration
 */
function pugo_create_editor(string $key, array $overrides = []): ?\Pugo\DataEditors\BaseDataEditor {
    $registry = Registry::getInstance();
    $editorConfig = $registry->getDataEditor($key);
    
    if (!$editorConfig) {
        return null;
    }
    
    $config = array_merge($editorConfig, $overrides);
    $class = $config['editor_class'] ?? \Pugo\DataEditors\SimpleListEditor::class;
    
    if (!class_exists($class)) {
        return null;
    }
    
    return new $class($config);
}
