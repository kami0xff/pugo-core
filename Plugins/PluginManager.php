<?php
/**
 * Pugo Core 3.0 - Plugin Manager
 * 
 * Event-driven plugin architecture for extending Pugo functionality.
 */

namespace Pugo\Plugins;

use Pugo\Config\PugoConfig;

class PluginManager
{
    private static ?PluginManager $instance = null;
    private array $plugins = [];
    private array $hooks = [];
    private array $filters = [];
    private string $pluginsDir;
    private bool $initialized = false;
    
    private function __construct()
    {
        $this->pluginsDir = (defined('PUGO_ROOT') ? PUGO_ROOT : getcwd()) . '/plugins';
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize plugins
     */
    public function initialize(): void
    {
        if ($this->initialized) return;
        
        $this->loadPlugins();
        $this->initialized = true;
        
        $this->doAction('pugo_init');
    }
    
    /**
     * Load plugins from plugins directory and config
     */
    protected function loadPlugins(): void
    {
        // Load from pugo.yaml config
        $config = PugoConfig::getInstance();
        $configPlugins = $config->plugins();
        
        foreach ($configPlugins as $id => $pluginConfig) {
            if (!($pluginConfig['enabled'] ?? true)) continue;
            
            $class = $pluginConfig['class'] ?? null;
            if ($class && class_exists($class)) {
                $this->register($id, new $class($pluginConfig));
            }
        }
        
        // Auto-load from plugins directory
        if (is_dir($this->pluginsDir)) {
            $dirs = glob($this->pluginsDir . '/*', GLOB_ONLYDIR);
            
            foreach ($dirs as $dir) {
                $pluginFile = $dir . '/plugin.php';
                if (file_exists($pluginFile)) {
                    $plugin = require $pluginFile;
                    if ($plugin instanceof Plugin) {
                        $this->register(basename($dir), $plugin);
                    }
                }
            }
        }
    }
    
    /**
     * Register a plugin
     */
    public function register(string $id, Plugin $plugin): self
    {
        $this->plugins[$id] = $plugin;
        $plugin->register($this);
        return $this;
    }
    
    /**
     * Get all plugins
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }
    
    /**
     * Get a plugin by ID
     */
    public function getPlugin(string $id): ?Plugin
    {
        return $this->plugins[$id] ?? null;
    }
    
    /**
     * Add an action hook
     */
    public function addAction(string $hook, callable $callback, int $priority = 10): self
    {
        if (!isset($this->hooks[$hook])) {
            $this->hooks[$hook] = [];
        }
        
        $this->hooks[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
        
        // Sort by priority
        usort($this->hooks[$hook], fn($a, $b) => $a['priority'] <=> $b['priority']);
        
        return $this;
    }
    
    /**
     * Execute action hooks
     */
    public function doAction(string $hook, ...$args): void
    {
        if (!isset($this->hooks[$hook])) return;
        
        foreach ($this->hooks[$hook] as $handler) {
            call_user_func_array($handler['callback'], $args);
        }
    }
    
    /**
     * Add a filter
     */
    public function addFilter(string $filter, callable $callback, int $priority = 10): self
    {
        if (!isset($this->filters[$filter])) {
            $this->filters[$filter] = [];
        }
        
        $this->filters[$filter][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
        
        usort($this->filters[$filter], fn($a, $b) => $a['priority'] <=> $b['priority']);
        
        return $this;
    }
    
    /**
     * Apply filters
     */
    public function applyFilters(string $filter, mixed $value, ...$args): mixed
    {
        if (!isset($this->filters[$filter])) {
            return $value;
        }
        
        foreach ($this->filters[$filter] as $handler) {
            $value = call_user_func_array($handler['callback'], array_merge([$value], $args));
        }
        
        return $value;
    }
    
    /**
     * Check if a hook has handlers
     */
    public function hasAction(string $hook): bool
    {
        return !empty($this->hooks[$hook]);
    }
    
    /**
     * Check if a filter has handlers
     */
    public function hasFilter(string $filter): bool
    {
        return !empty($this->filters[$filter]);
    }
    
    /**
     * Remove an action
     */
    public function removeAction(string $hook, ?callable $callback = null): self
    {
        if ($callback === null) {
            unset($this->hooks[$hook]);
        } else {
            if (isset($this->hooks[$hook])) {
                $this->hooks[$hook] = array_filter(
                    $this->hooks[$hook],
                    fn($h) => $h['callback'] !== $callback
                );
            }
        }
        return $this;
    }
    
    /**
     * Remove a filter
     */
    public function removeFilter(string $filter, ?callable $callback = null): self
    {
        if ($callback === null) {
            unset($this->filters[$filter]);
        } else {
            if (isset($this->filters[$filter])) {
                $this->filters[$filter] = array_filter(
                    $this->filters[$filter],
                    fn($f) => $f['callback'] !== $callback
                );
            }
        }
        return $this;
    }
}

/**
 * Global helpers
 */
function pugo_plugins(): PluginManager
{
    return PluginManager::getInstance();
}

function pugo_add_action(string $hook, callable $callback, int $priority = 10): void
{
    PluginManager::getInstance()->addAction($hook, $callback, $priority);
}

function pugo_do_action(string $hook, ...$args): void
{
    PluginManager::getInstance()->doAction($hook, ...$args);
}

function pugo_add_filter(string $filter, callable $callback, int $priority = 10): void
{
    PluginManager::getInstance()->addFilter($filter, $callback, $priority);
}

function pugo_apply_filters(string $filter, mixed $value, ...$args): mixed
{
    return PluginManager::getInstance()->applyFilters($filter, $value, ...$args);
}

