<?php
/**
 * Pugo Core - Unified Initialization
 * 
 * This file is the SINGLE entry point for Pugo configuration.
 * It uses PugoConfig to read pugo.yaml (with config.php as fallback).
 * 
 * Include this file at the top of any Pugo page:
 *   require __DIR__ . '/../core/pugo_init.php';
 * 
 * After including, you have access to:
 *   - $config (array) - Backward compatible config array
 *   - pugo() - Get the PugoConfig instance
 *   - pugo_get($key, $default) - Get config value by dot notation
 */

// Prevent multiple initialization
if (defined('PUGO_INITIALIZED')) {
    return;
}
define('PUGO_INITIALIZED', true);

// Prevent direct access
if (!defined('HUGO_ADMIN')) {
    define('HUGO_ADMIN', true);
}

// ============================================================================
// PATH SETUP
// ============================================================================

// Determine project root (where pugo.yaml lives)
if (!defined('HUGO_ROOT')) {
    // Default: two levels up from core (admin/core -> project root)
    $guessRoot = getenv('HUGO_ROOT') ?: dirname(__DIR__, 2);
    define('HUGO_ROOT', $guessRoot);
}

if (!defined('ADMIN_ROOT')) {
    define('ADMIN_ROOT', dirname(__DIR__));
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

// ============================================================================
// LOAD PUGO CONFIG (SINGLE SOURCE OF TRUTH)
// ============================================================================

require_once __DIR__ . '/Config/PugoConfig.php';

use Pugo\Config\PugoConfig;

/**
 * Get the PugoConfig singleton instance
 */
function pugo(): PugoConfig
{
    return PugoConfig::getInstance(HUGO_ROOT);
}

/**
 * Get a config value by dot notation
 * 
 * @example pugo_get('languages.en.name') => 'English'
 * @example pugo_get('site.name') => 'My Site'
 */
function pugo_get(string $key, mixed $default = null): mixed
{
    return pugo()->get($key, $default);
}

/**
 * Get all languages from config
 */
function pugo_languages(): array
{
    return pugo()->languages();
}

/**
 * Get all sections from config
 */
function pugo_sections(): array
{
    return pugo()->sections();
}

/**
 * Get the content directory for a language
 */
function pugo_content_dir(string $lang = 'en'): string
{
    if ($lang === 'en' || $lang === pugo_get('site.default_language', 'en')) {
        return CONTENT_DIR;
    }
    
    $languages = pugo_languages();
    $contentDir = $languages[$lang]['content_dir'] ?? "content.{$lang}";
    
    // If it's a relative path, prepend HUGO_ROOT
    if (!str_starts_with($contentDir, '/')) {
        return HUGO_ROOT . '/' . $contentDir;
    }
    
    return $contentDir;
}

// ============================================================================
// BACKWARD COMPATIBILITY: Build $config array from PugoConfig
// ============================================================================

/**
 * Build backward-compatible $config array from PugoConfig
 * 
 * This allows old code using $config['languages'] etc. to keep working
 * while we migrate to using pugo() directly.
 */
function pugo_build_legacy_config(): array
{
    $pugo = pugo();
    
    // Get languages (already normalized by PugoConfig::languages())
    $languages = $pugo->languages();
    
    return [
        // Site info
        'site_name' => $pugo->get('site.name', 'My Site'),
        'site_url' => $pugo->get('site.url', 'http://localhost'),
        
        // Languages (single source of truth!)
        'languages' => $languages,
        'default_language' => $pugo->get('site.default_language', 'en'),
        
        // Sections
        'sections' => $pugo->sections(),
        
        // Content types
        'content_types' => $pugo->contentTypes(),
        
        // Data types
        'data_types' => $pugo->dataTypes(),
        
        // Deployment
        'deployment' => $pugo->deployment(),
        
        // Auth
        'auth' => $pugo->get('auth', [
            'enabled' => true,
            'session_lifetime' => 86400,
        ]),
        
        // Git
        'git' => $pugo->get('deployment.git', [
            'enabled' => true,
            'branch' => 'main',
        ]),
        
        // GitLab
        'gitlab' => $pugo->get('deployment.git.gitlab', []),
        
        // Frontmatter fields (keep same structure)
        'frontmatter_fields' => $pugo->get('content_types.article.fields', [
            'title' => ['type' => 'text', 'required' => true, 'label' => 'Title'],
            'description' => ['type' => 'textarea', 'required' => true, 'label' => 'Description'],
            'date' => ['type' => 'date', 'required' => true, 'label' => 'Date'],
            'draft' => ['type' => 'checkbox', 'label' => 'Draft'],
        ]),
        
        // Features
        'features' => $pugo->get('features', []),
        
        // Allowed uploads
        'allowed_images' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
        'allowed_videos' => ['mp4', 'webm'],
        'max_upload_size' => 100 * 1024 * 1024,
        
        // Hugo command
        'hugo_command' => 'cd ' . HUGO_ROOT . ' && hugo --minify',
    ];
}

// Build and expose $config globally for backward compatibility
$config = pugo_build_legacy_config();

// Also make it available as a constant for places that need it
if (!defined('PUGO_CONFIG')) {
    define('PUGO_CONFIG', serialize($config));
}

// ============================================================================
// DEBUG HELPER
// ============================================================================

/**
 * Dump Pugo config for debugging
 */
function pugo_debug(): void
{
    if (!isset($_GET['debug']) || $_GET['debug'] !== 'pugo') {
        return;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'pugo_yaml_path' => HUGO_ROOT . '/pugo.yaml',
        'pugo_yaml_exists' => file_exists(HUGO_ROOT . '/pugo.yaml'),
        'config' => pugo()->all(),
    ], JSON_PRETTY_PRINT);
    exit;
}

