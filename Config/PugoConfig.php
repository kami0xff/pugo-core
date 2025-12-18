<?php
/**
 * Pugo Core 3.0 - Configuration System
 * 
 * Parses pugo.yaml and provides centralized configuration access.
 * Single source of truth for the entire Pugo ecosystem.
 */

namespace Pugo\Config;

class PugoConfig
{
    private static ?PugoConfig $instance = null;
    private array $config = [];
    private string $configPath;
    private bool $loaded = false;
    
    private function __construct(string $projectRoot)
    {
        $this->configPath = $projectRoot . '/pugo.yaml';
        $this->load();
    }
    
    public static function getInstance(?string $projectRoot = null): self
    {
        if (self::$instance === null) {
            if ($projectRoot === null) {
                $projectRoot = defined('HUGO_ROOT') ? HUGO_ROOT : dirname(__DIR__, 3);
            }
            self::$instance = new self($projectRoot);
        }
        return self::$instance;
    }
    
    /**
     * Load and parse pugo.yaml
     */
    public function load(): void
    {
        if ($this->loaded) return;
        
        // Start with defaults
        $this->config = $this->getDefaults();
        
        // Load pugo.yaml if exists
        if (file_exists($this->configPath)) {
            $yaml = $this->parseYaml(file_get_contents($this->configPath));
            $this->config = $this->mergeDeep($this->config, $yaml);
        }
        
        // Also check for legacy config.php
        $legacyConfig = dirname($this->configPath) . '/admin/config.php';
        if (file_exists($legacyConfig)) {
            $legacy = require $legacyConfig;
            if (is_array($legacy)) {
                $this->config = $this->mergeLegacyConfig($this->config, $legacy);
            }
        }
        
        $this->loaded = true;
    }
    
    /**
     * Get configuration value using dot notation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }
    
    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }
    
    /**
     * Get site configuration
     */
    public function site(): array
    {
        return $this->get('site', []);
    }
    
    /**
     * Get languages configuration
     */
    public function languages(): array
    {
        return $this->get('languages', ['en' => ['name' => 'English', 'flag' => '🇬🇧']]);
    }
    
    /**
     * Get sections configuration
     */
    public function sections(): array
    {
        return $this->get('sections', []);
    }
    
    /**
     * Get content types configuration
     */
    public function contentTypes(): array
    {
        return $this->get('content_types', []);
    }
    
    /**
     * Get data types configuration
     */
    public function dataTypes(): array
    {
        return $this->get('data_types', []);
    }
    
    /**
     * Get blocks configuration
     */
    public function blocks(): array
    {
        return $this->get('blocks', []);
    }
    
    /**
     * Get deployment configuration
     */
    public function deployment(): array
    {
        return $this->get('deployment', []);
    }
    
    /**
     * Get active deployment method
     */
    public function deploymentMethod(): string
    {
        return $this->get('deployment.method', 'git');
    }
    
    /**
     * Get plugins configuration
     */
    public function plugins(): array
    {
        return $this->get('plugins', []);
    }
    
    /**
     * Check if a feature is enabled
     */
    public function isEnabled(string $feature): bool
    {
        return (bool) $this->get("features.{$feature}", true);
    }
    
    /**
     * Get environment-specific config
     */
    public function forEnvironment(string $env): array
    {
        $base = $this->config;
        $envConfig = $this->get("environments.{$env}", []);
        
        return $this->mergeDeep($base, $envConfig);
    }
    
    /**
     * Save configuration to pugo.yaml
     */
    public function save(): bool
    {
        $yaml = $this->generateYaml($this->config);
        return file_put_contents($this->configPath, $yaml) !== false;
    }
    
    /**
     * Default configuration
     */
    protected function getDefaults(): array
    {
        return [
            'site' => [
                'name' => 'My Site',
                'url' => 'http://localhost',
                'default_language' => 'en',
            ],
            
            'languages' => [
                'en' => [
                    'name' => 'English',
                    'flag' => '🇬🇧',
                    'suffix' => '',
                ],
            ],
            
            'sections' => [],
            
            'content_types' => [
                'article' => [
                    'name' => 'Article',
                    'icon' => 'file-text',
                    'fields' => [
                        'title' => ['type' => 'text', 'required' => true],
                        'description' => ['type' => 'textarea', 'required' => true],
                        'date' => ['type' => 'date', 'required' => true],
                        'draft' => ['type' => 'checkbox'],
                    ],
                ],
            ],
            
            'data_types' => [],
            
            'blocks' => [],
            
            'deployment' => [
                'method' => 'git',
                'git' => [
                    'enabled' => true,
                    'branch' => 'main',
                    'auto_commit' => false,
                ],
            ],
            
            'plugins' => [],
            
            'features' => [
                'page_builder' => true,
                'media_library' => true,
                'seo_tools' => true,
                'analytics' => false,
            ],
            
            'auth' => [
                'enabled' => true,
                'session_lifetime' => 86400,
            ],
        ];
    }
    
    /**
     * Simple YAML parser (for pugo.yaml)
     */
    protected function parseYaml(string $content): array
    {
        // Use Symfony YAML if available, otherwise simple parser
        if (function_exists('yaml_parse')) {
            return yaml_parse($content) ?: [];
        }
        
        // Simple YAML parser for basic structures
        return $this->simpleYamlParse($content);
    }
    
    /**
     * Simple YAML parser for basic pugo.yaml structure
     */
    protected function simpleYamlParse(string $content): array
    {
        $result = [];
        $stack = [&$result];
        $indentStack = [-1];
        
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (preg_match('/^\s*#/', $line) || trim($line) === '') {
                continue;
            }
            
            // Get indentation
            preg_match('/^(\s*)/', $line, $m);
            $indent = strlen($m[1]);
            
            // Trim the line
            $line = trim($line);
            
            // Pop stack for dedented lines
            while (count($indentStack) > 1 && $indent <= end($indentStack)) {
                array_pop($stack);
                array_pop($indentStack);
            }
            
            // Key: value pair
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.*)$/', $line, $m)) {
                $key = $m[1];
                $value = trim($m[2], '"\'');
                
                $current = &$stack[count($stack) - 1];
                
                if ($value === '' || $value === '|' || $value === '>') {
                    // Nested structure or multiline
                    $current[$key] = [];
                    $stack[] = &$current[$key];
                    $indentStack[] = $indent;
                } else {
                    // Simple value
                    if ($value === 'true') $value = true;
                    elseif ($value === 'false') $value = false;
                    elseif (is_numeric($value)) $value = $value + 0;
                    
                    $current[$key] = $value;
                }
            }
            // Array item
            elseif (preg_match('/^-\s*(.+)$/', $line, $m)) {
                $current = &$stack[count($stack) - 1];
                if (!is_array($current)) {
                    $current = [];
                }
                
                $value = trim($m[1], '"\'');
                
                // Check if it's a key:value in array item
                if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.*)$/', $m[1], $kv)) {
                    $item = [$kv[1] => trim($kv[2], '"\'')];
                    $current[] = $item;
                    $stack[] = &$current[count($current) - 1];
                    $indentStack[] = $indent;
                } else {
                    $current[] = $value;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Generate YAML from config array
     */
    protected function generateYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_int($key)) {
                // Array item
                if (is_array($value)) {
                    $yaml .= $prefix . "-\n";
                    $yaml .= $this->generateYaml($value, $indent + 1);
                } else {
                    $yaml .= $prefix . "- " . $this->formatValue($value) . "\n";
                }
            } else {
                // Key: value
                if (is_array($value) && !empty($value)) {
                    $yaml .= $prefix . "{$key}:\n";
                    $yaml .= $this->generateYaml($value, $indent + 1);
                } else {
                    $yaml .= $prefix . "{$key}: " . $this->formatValue($value) . "\n";
                }
            }
        }
        
        return $yaml;
    }
    
    /**
     * Format value for YAML output
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === true) return 'true';
        if ($value === false) return 'false';
        if ($value === null) return '';
        if (is_numeric($value)) return (string) $value;
        if (is_string($value) && (str_contains($value, ':') || str_contains($value, '#'))) {
            return '"' . addslashes($value) . '"';
        }
        return (string) $value;
    }
    
    /**
     * Deep merge arrays
     */
    protected function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
    
    /**
     * Merge legacy config.php format
     */
    protected function mergeLegacyConfig(array $config, array $legacy): array
    {
        // Map legacy keys to new structure
        if (isset($legacy['site_name'])) {
            $config['site']['name'] = $legacy['site_name'];
        }
        if (isset($legacy['site_url'])) {
            $config['site']['url'] = $legacy['site_url'];
        }
        if (isset($legacy['languages'])) {
            foreach ($legacy['languages'] as $code => $lang) {
                $config['languages'][$code] = [
                    'name' => $lang['name'] ?? ucfirst($code),
                    'flag' => $lang['flag'] ?? '',
                    'suffix' => $lang['data_suffix'] ?? ($lang['suffix'] ?? ''),
                ];
            }
        }
        if (isset($legacy['sections'])) {
            $config['sections'] = $legacy['sections'];
        }
        if (isset($legacy['auth'])) {
            $config['auth'] = array_merge($config['auth'], $legacy['auth']);
        }
        
        return $config;
    }
}

/**
 * Global helper function
 */
function pugo_config(?string $key = null, mixed $default = null): mixed
{
    $config = PugoConfig::getInstance();
    
    if ($key === null) {
        return $config;
    }
    
    return $config->get($key, $default);
}

