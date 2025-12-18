<?php
/**
 * Pugo Core - Registry System
 * 
 * Central registry for all Pugo modules: sections, content types, data editors, pages.
 * Allows enabling/disabling features per project.
 */

namespace Pugo\Registry;

class Registry
{
    private static ?Registry $instance = null;
    
    private array $sections = [];
    private array $contentTypes = [];
    private array $dataEditors = [];
    private array $pages = [];
    private array $components = [];
    
    private function __construct() {}
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // =========================================================================
    // SECTIONS (users, models, studios, blog, etc.)
    // =========================================================================
    
    public function registerSection(string $key, array $config): self
    {
        $this->sections[$key] = array_merge([
            'name' => ucfirst($key),
            'icon' => 'folder',
            'color' => '#6b7280',
            'enabled' => true,
            'content_type' => 'article',
        ], $config);
        
        return $this;
    }
    
    public function getSections(bool $enabledOnly = true): array
    {
        if (!$enabledOnly) {
            return $this->sections;
        }
        return array_filter($this->sections, fn($s) => $s['enabled'] ?? true);
    }
    
    public function getSection(string $key): ?array
    {
        return $this->sections[$key] ?? null;
    }
    
    public function enableSection(string $key): self
    {
        if (isset($this->sections[$key])) {
            $this->sections[$key]['enabled'] = true;
        }
        return $this;
    }
    
    public function disableSection(string $key): self
    {
        if (isset($this->sections[$key])) {
            $this->sections[$key]['enabled'] = false;
        }
        return $this;
    }
    
    // =========================================================================
    // CONTENT TYPES (article, faq, tutorial, review, product, etc.)
    // =========================================================================
    
    public function registerContentType(string $key, array $config): self
    {
        $this->contentTypes[$key] = array_merge([
            'name' => ucfirst($key),
            'icon' => 'file',
            'enabled' => true,
            'fields' => [],
            'layout' => [],
        ], $config);
        
        return $this;
    }
    
    public function getContentTypes(bool $enabledOnly = true): array
    {
        if (!$enabledOnly) {
            return $this->contentTypes;
        }
        return array_filter($this->contentTypes, fn($t) => $t['enabled'] ?? true);
    }
    
    public function getContentType(string $key): ?array
    {
        return $this->contentTypes[$key] ?? null;
    }
    
    // =========================================================================
    // DATA EDITORS (faqs, topics, tutorials, quickaccess, etc.)
    // =========================================================================
    
    public function registerDataEditor(string $key, array $config): self
    {
        $this->dataEditors[$key] = array_merge([
            'name' => ucfirst(str_replace('_', ' ', $key)),
            'icon' => 'database',
            'enabled' => true,
            'editor_class' => null,  // Will use default if null
            'data_file' => $key,
            'fields' => [],
            'supports_translations' => true,
        ], $config);
        
        return $this;
    }
    
    public function getDataEditors(bool $enabledOnly = true): array
    {
        if (!$enabledOnly) {
            return $this->dataEditors;
        }
        return array_filter($this->dataEditors, fn($e) => $e['enabled'] ?? true);
    }
    
    public function getDataEditor(string $key): ?array
    {
        return $this->dataEditors[$key] ?? null;
    }
    
    // =========================================================================
    // ADMIN PAGES
    // =========================================================================
    
    public function registerPage(string $key, array $config): self
    {
        $this->pages[$key] = array_merge([
            'name' => ucfirst($key),
            'icon' => 'file',
            'enabled' => true,
            'nav_group' => 'main',  // main, data, settings
            'nav_order' => 100,
            'handler' => null,
        ], $config);
        
        return $this;
    }
    
    public function getPages(bool $enabledOnly = true): array
    {
        if (!$enabledOnly) {
            return $this->pages;
        }
        return array_filter($this->pages, fn($p) => $p['enabled'] ?? true);
    }
    
    public function getPagesByGroup(string $group): array
    {
        $pages = $this->getPages(true);
        $filtered = array_filter($pages, fn($p) => ($p['nav_group'] ?? 'main') === $group);
        uasort($filtered, fn($a, $b) => ($a['nav_order'] ?? 100) <=> ($b['nav_order'] ?? 100));
        return $filtered;
    }
    
    // =========================================================================
    // UI COMPONENTS
    // =========================================================================
    
    public function registerComponent(string $key, string $class): self
    {
        $this->components[$key] = $class;
        return $this;
    }
    
    public function getComponent(string $key): ?string
    {
        return $this->components[$key] ?? null;
    }
    
    // =========================================================================
    // BULK CONFIGURATION
    // =========================================================================
    
    public function configure(array $config): self
    {
        // Enable/disable sections
        if (isset($config['sections'])) {
            foreach ($config['sections'] as $key => $settings) {
                if (is_bool($settings)) {
                    $settings ? $this->enableSection($key) : $this->disableSection($key);
                } elseif (is_array($settings)) {
                    $this->registerSection($key, $settings);
                }
            }
        }
        
        // Enable/disable data editors
        if (isset($config['data_editors'])) {
            foreach ($config['data_editors'] as $key => $settings) {
                if (is_bool($settings)) {
                    if (isset($this->dataEditors[$key])) {
                        $this->dataEditors[$key]['enabled'] = $settings;
                    }
                } elseif (is_array($settings)) {
                    $this->registerDataEditor($key, $settings);
                }
            }
        }
        
        // Enable/disable content types
        if (isset($config['content_types'])) {
            foreach ($config['content_types'] as $key => $settings) {
                if (is_bool($settings)) {
                    if (isset($this->contentTypes[$key])) {
                        $this->contentTypes[$key]['enabled'] = $settings;
                    }
                } elseif (is_array($settings)) {
                    $this->registerContentType($key, $settings);
                }
            }
        }
        
        return $this;
    }
    
    // =========================================================================
    // SERIALIZATION
    // =========================================================================
    
    public function toArray(): array
    {
        return [
            'sections' => $this->sections,
            'content_types' => $this->contentTypes,
            'data_editors' => $this->dataEditors,
            'pages' => $this->pages,
            'components' => $this->components,
        ];
    }
}

// Global helper function
function pugo_registry(): Registry
{
    return Registry::getInstance();
}

