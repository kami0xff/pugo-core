<?php
/**
 * PugoConfig - Centralized Configuration Loader
 * 
 * Loads ALL configuration from pugo.yaml - the single source of truth.
 * No more PHP config files needed for site setup!
 * 
 * Usage in a new project:
 *   1. Copy pugo.example.yaml to your project as pugo.yaml
 *   2. Edit pugo.yaml with your settings
 *   3. That's it! No PHP config needed.
 */

namespace Pugo\Config;

class PugoConfig
{
    private static ?array $config = null;
    private static ?string $configPath = null;
    private static ?PugoConfig $instance = null;
    
    /**
     * Get singleton instance (for OOP-style access)
     * 
     * @param string|null $hugoRoot Optional Hugo root path
     * @return PugoConfig
     */
    public static function getInstance(?string $hugoRoot = null): PugoConfig
    {
        if (self::$instance === null) {
            self::$instance = new self();
            
            // Try to find and load config
            if ($hugoRoot) {
                $yamlPath = $hugoRoot . '/pugo.yaml';
                if (!file_exists($yamlPath)) {
                    $yamlPath = $hugoRoot . '/admin/pugo.yaml';
                }
                if (file_exists($yamlPath)) {
                    self::load($yamlPath);
                }
            } else {
                // Auto-detect
                try {
                    self::load();
                } catch (\Exception $e) {
                    // Config not found, will use defaults
                }
            }
        }
        return self::$instance;
    }
    
    /**
     * Load configuration from pugo.yaml
     * 
     * @param string|null $path Path to pugo.yaml (auto-detected if null)
     * @return array The configuration array
     */
    public static function load(?string $path = null): array
    {
        if (self::$config !== null && ($path === null || $path === self::$configPath)) {
            return self::$config;
        }
        
        // Find pugo.yaml
        $configPath = $path ?? self::findConfigFile();
        
        if (!$configPath || !file_exists($configPath)) {
            throw new \RuntimeException(
                "pugo.yaml not found. Create one from pugo.example.yaml\n" .
                "Searched: " . ($path ?? 'auto-detect')
            );
        }
        
        self::$configPath = $configPath;
        
        // Parse YAML
        $yaml = file_get_contents($configPath);
        $parsed = self::parseYaml($yaml);
        
        // Build complete config with defaults
        self::$config = self::buildConfig($parsed, dirname($configPath));
        
        // Define PHP constants for backward compatibility
        self::defineConstants(self::$config);
        
        return self::$config;
    }
    
    /**
     * Get a config value by dot notation (static version)
     * 
     * @param string $key Dot notation key (e.g., 'site.name', 'languages.en.flag')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // Ensure config is loaded
        if (self::$config === null) {
            try {
                self::load();
            } catch (\Exception $e) {
                return $default;
            }
        }
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Get all loaded configuration
     */
    public static function all(): array
    {
        if (self::$config === null) {
            try {
                self::load();
            } catch (\Exception $e) {
                return [];
            }
        }
        return self::$config ?? [];
    }
    
    /**
     * Find the pugo.yaml config file
     */
    private static function findConfigFile(): ?string
    {
        // Check common locations
        $searchPaths = [
            // From admin folder (typical Pugo structure)
            dirname(__DIR__, 2) . '/pugo.yaml',
            // From core folder
            dirname(__DIR__, 3) . '/pugo.yaml',
            // Current directory
            getcwd() . '/pugo.yaml',
            // Environment variable
            getenv('PUGO_CONFIG') ?: null,
        ];
        
        // Also check HUGO_ROOT if defined
        if (defined('HUGO_ROOT')) {
            array_unshift($searchPaths, HUGO_ROOT . '/pugo.yaml');
        }
        
        foreach ($searchPaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Simple YAML parser (handles common cases without external deps)
     */
    private static function parseYaml(string $yaml): array
    {
        // Use PHP YAML extension if available
        if (function_exists('yaml_parse')) {
            return yaml_parse($yaml) ?: [];
        }
        
        // Simple built-in parser for common YAML
        $result = [];
        $stack = [&$result];
        $indentStack = [-1];
        
        $lines = explode("\n", $yaml);
        
        foreach ($lines as $lineNum => $line) {
            // Skip comments and empty lines
            $trimmed = trim($line);
            if ($trimmed === '' || (strlen($trimmed) > 0 && $trimmed[0] === '#')) {
                continue;
            }
            
            // Calculate indent
            $indent = strlen($line) - strlen(ltrim($line));
            $line = $trimmed;
            
            // Pop stack for dedent
            while ($indent <= end($indentStack) && count($stack) > 1) {
                array_pop($stack);
                array_pop($indentStack);
            }
            
            // Parse list item first (- value or - { inline object })
            if (preg_match('/^-\s*(.*)$/', $line, $matches)) {
                $value = trim($matches[1]);
                $current = &$stack[count($stack) - 1];
                
                // Check if it's an inline object { key: value, ... }
                if (preg_match('/^\{\s*(.+)\s*\}$/', $value, $objMatch)) {
                    $obj = self::parseInlineObject($objMatch[1]);
                    $current[] = $obj;
                } elseif ($value === '' || $value === null) {
                    // Empty list item - create new nested array
                    $current[] = [];
                    $stack[] = &$current[count($current) - 1];
                    $indentStack[] = $indent;
                } else {
                    $current[] = self::parseValue($value);
                }
            }
            // Parse key: value
            elseif (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                
                // Remove quotes
                if (preg_match('/^["\'](.*)["\']\s*$/', $value, $vMatches)) {
                    $value = $vMatches[1];
                }
                
                $current = &$stack[count($stack) - 1];
                
                if ($value === '' || $value === '|' || $value === '>') {
                    // Nested object or multiline
                    $current[$key] = [];
                    $stack[] = &$current[$key];
                    $indentStack[] = $indent;
                } elseif (preg_match('/^\[(.+)\]$/', $value, $arrMatch)) {
                    // Inline array [a, b, c]
                    $current[$key] = array_map('trim', explode(',', $arrMatch[1]));
                } elseif (preg_match('/^\{(.+)\}$/', $value, $objMatch)) {
                    // Inline object
                    $current[$key] = self::parseInlineObject($objMatch[1]);
                } else {
                    // Scalar value
                    $current[$key] = self::parseValue($value);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Parse inline object like { name: value, type: text, required: true }
     */
    private static function parseInlineObject(string $content): array
    {
        $obj = [];
        
        // Split by comma, but handle nested values carefully
        $pairs = [];
        $current = '';
        $depth = 0;
        
        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];
            if ($char === '[' || $char === '{') $depth++;
            if ($char === ']' || $char === '}') $depth--;
            if ($char === ',' && $depth === 0) {
                $pairs[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if ($current !== '') {
            $pairs[] = trim($current);
        }
        
        foreach ($pairs as $pair) {
            // Match key: value where value can contain colons (like times)
            if (preg_match('/^([^:]+):\s*(.*)$/', $pair, $m)) {
                $key = trim($m[1]);
                $val = trim($m[2]);
                
                // Handle arrays in inline object
                if (preg_match('/^\[(.+)\]$/', $val, $arrMatch)) {
                    $obj[$key] = array_map(function($v) {
                        return self::parseValue(trim($v));
                    }, explode(',', $arrMatch[1]));
                } else {
                    $obj[$key] = self::parseValue($val);
                }
            }
        }
        
        return $obj;
    }
    
    /**
     * Parse a YAML value
     */
    private static function parseValue(string $value)
    {
        // Remove quotes
        if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
            return $matches[1];
        }
        
        // Boolean
        if ($value === 'true' || $value === 'yes') return true;
        if ($value === 'false' || $value === 'no') return false;
        
        // Null
        if ($value === 'null' || $value === '~') return null;
        
        // Number
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        return $value;
    }
    
    /**
     * Build complete config with defaults and derived values
     */
    private static function buildConfig(array $parsed, string $projectRoot): array
    {
        // Default configuration
        $defaults = [
            'site' => [
                'name' => 'My Site',
                'url' => 'http://localhost',
            ],
            'auth' => [
                'enabled' => true,
                'username' => 'admin',
                'password_hash' => '',
                'session_lifetime' => 86400,
            ],
            'languages' => [
                'en' => ['name' => 'English', 'flag' => '🇬🇧', 'default' => true]
            ],
            'sections' => [],
            'content_types' => [],
            'data' => [],
            'media' => [
                'allowed_images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
                'allowed_videos' => ['mp4', 'webm'],
                'max_upload_size' => 10485760, // 10MB
            ],
            'hugo' => [
                'command' => 'hugo --minify',
            ],
            'deploy' => [
                'enabled' => false,
                'auto_commit' => false,
            ],
        ];
        
        // Merge parsed with defaults
        $config = self::arrayMergeDeep($defaults, $parsed);
        
        // Set paths
        $config['_paths'] = [
            'root' => $projectRoot,
            'content' => $projectRoot . '/content',
            'static' => $projectRoot . '/static',
            'data' => $projectRoot . '/data',
            'layouts' => $projectRoot . '/layouts',
            'public' => $projectRoot . '/public',
            'images' => $projectRoot . '/static/images',
        ];
        
        // Process languages
        $config['languages'] = self::processLanguages($config['languages']);
        
        // Process sections with content types
        $config['sections'] = self::processSections($config['sections'] ?? [], $config['content_types'] ?? []);
        
        // Build backward-compatible format
        $config['site_name'] = $config['site']['name'];
        $config['site_url'] = $config['site']['url'];
        $config['default_language'] = self::getDefaultLanguage($config['languages']);
        
        return $config;
    }
    
    /**
     * Process languages config
     */
    private static function processLanguages(array $languages): array
    {
        $processed = [];
        
        foreach ($languages as $code => $lang) {
            if (is_string($lang)) {
                // Simple format: en: English
                $processed[$code] = [
                    'name' => $lang,
                    'flag' => '',
                    'default' => false,
                    'suffix' => $code === 'en' ? '' : '.' . $code,
                    'data_suffix' => $code === 'en' ? '' : '.' . $code,
                    'content_dir' => $code === 'en' ? 'content' : 'content.' . $code,
                ];
            } else {
                // Full format
                $isDefault = $lang['default'] ?? false;
                $processed[$code] = [
                    'name' => $lang['name'] ?? ucfirst($code),
                    'flag' => $lang['flag'] ?? '',
                    'default' => $isDefault,
                    'suffix' => $isDefault ? '' : ($lang['suffix'] ?? '.' . $code),
                    'data_suffix' => $isDefault ? '' : ($lang['data_suffix'] ?? '.' . $code),
                    'content_dir' => $isDefault ? 'content' : ($lang['content_dir'] ?? 'content.' . $code),
                ];
            }
        }
        
        return $processed;
    }
    
    /**
     * Process sections with content type fields
     */
    private static function processSections(array $sections, array $contentTypes): array
    {
        $defaultColors = [
            'blog' => '#e11d48',
            'pages' => '#3b82f6',
            'tutorials' => '#10b981',
            'reviews' => '#f59e0b',
            'docs' => '#0ea5e9',
            'news' => '#8b5cf6',
        ];
        
        $processed = [];
        
        foreach ($sections as $key => $section) {
            $processed[$key] = [
                'name' => $section['name'] ?? ucfirst($key),
                'icon' => $section['icon'] ?? '📄',
                'color' => $section['color'] ?? $defaultColors[$key] ?? '#6b7280',
                'path' => $section['path'] ?? 'content/' . $key,
                'fields' => $section['fields'] ?? [],
                'layout' => $section['layout'] ?? 'single',
            ];
            
            // Inherit from content_type if specified
            if (!empty($section['content_type']) && isset($contentTypes[$section['content_type']])) {
                $type = $contentTypes[$section['content_type']];
                $processed[$key] = array_merge($type, $processed[$key]);
            }
        }
        
        return $processed;
    }
    
    /**
     * Get default language code
     */
    private static function getDefaultLanguage(array $languages): string
    {
        foreach ($languages as $code => $lang) {
            if (!empty($lang['default'])) {
                return $code;
            }
        }
        return array_key_first($languages) ?? 'en';
    }
    
    /**
     * Deep merge arrays
     */
    private static function arrayMergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::arrayMergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
    
    /**
     * Define PHP constants for backward compatibility
     */
    private static function defineConstants(array $config): void
    {
        $paths = $config['_paths'];
        
        if (!defined('HUGO_ROOT')) define('HUGO_ROOT', $paths['root']);
        if (!defined('CONTENT_DIR')) define('CONTENT_DIR', $paths['content']);
        if (!defined('STATIC_DIR')) define('STATIC_DIR', $paths['static']);
        if (!defined('DATA_DIR')) define('DATA_DIR', $paths['data']);
        if (!defined('IMAGES_DIR')) define('IMAGES_DIR', $paths['images']);
    }
    
    /**
     * Get the path to the config file
     */
    public static function getConfigPath(): ?string
    {
        return self::$configPath;
    }
    
    /**
     * Reload configuration (useful after changes)
     */
    public static function reload(): array
    {
        self::$config = null;
        return self::load(self::$configPath);
    }
    
    // =========================================================================
    // INSTANCE METHODS (for pugo_init.php compatibility)
    // =========================================================================
    
    /**
     * Get languages configuration
     */
    public function languages(): array
    {
        return self::get('languages', [
            'en' => ['name' => 'English', 'flag' => '🇬🇧', 'default' => true, 'suffix' => '', 'data_suffix' => '']
        ]);
    }
    
    /**
     * Get sections configuration
     */
    public function sections(): array
    {
        return self::get('sections', []);
    }
    
    /**
     * Get content types configuration
     */
    public function contentTypes(): array
    {
        return self::get('content_types', []);
    }
    
    /**
     * Get data types configuration
     */
    public function dataTypes(): array
    {
        return self::get('data', []);
    }
    
    /**
     * Get deployment configuration
     */
    public function deployment(): array
    {
        return self::get('deploy', [
            'enabled' => false,
            'auto_commit' => false,
        ]);
    }
}
