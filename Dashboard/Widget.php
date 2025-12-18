<?php
/**
 * Pugo Core 3.0 - Widget Base Class
 */

namespace Pugo\Dashboard;

abstract class Widget
{
    protected array $config = [];
    protected bool $enabled = true;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * Get widget info
     */
    abstract public function getInfo(): array;
    
    /**
     * Render widget HTML
     */
    abstract public function render(): string;
    
    /**
     * Get widget data (for AJAX refresh)
     */
    public function getData(): array
    {
        return [];
    }
    
    /**
     * Check if widget is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * Enable widget
     */
    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }
    
    /**
     * Disable widget
     */
    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }
    
    /**
     * Get config value
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Helper: escape HTML
     */
    protected function esc(?string $text): string
    {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }
}

