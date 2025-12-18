<?php
/**
 * Pugo Core 3.0 - Plugin Base Class
 */

namespace Pugo\Plugins;

abstract class Plugin
{
    protected array $config;
    protected PluginManager $manager;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * Get plugin info
     */
    abstract public function getInfo(): array;
    
    /**
     * Register plugin hooks and filters
     */
    abstract public function register(PluginManager $manager): void;
    
    /**
     * Called when plugin is activated
     */
    public function activate(): void
    {
        // Override in subclass
    }
    
    /**
     * Called when plugin is deactivated
     */
    public function deactivate(): void
    {
        // Override in subclass
    }
    
    /**
     * Get plugin ID
     */
    public function getId(): string
    {
        return $this->getInfo()['id'] ?? get_class($this);
    }
    
    /**
     * Get plugin name
     */
    public function getName(): string
    {
        return $this->getInfo()['name'] ?? $this->getId();
    }
    
    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return $this->getInfo()['version'] ?? '1.0.0';
    }
    
    /**
     * Get config value
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Add action hook
     */
    protected function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->manager->addAction($hook, $callback, $priority);
    }
    
    /**
     * Add filter
     */
    protected function addFilter(string $filter, callable $callback, int $priority = 10): void
    {
        $this->manager->addFilter($filter, $callback, $priority);
    }
}

