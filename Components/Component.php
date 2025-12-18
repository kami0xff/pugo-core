<?php
/**
 * Pugo Core - Base Component
 * 
 * All UI components extend this base class.
 */

namespace Pugo\Components;

abstract class Component
{
    protected array $props = [];
    protected array $attributes = [];
    
    public function __construct(array $props = [])
    {
        $this->props = array_merge($this->getDefaultProps(), $props);
    }
    
    /**
     * Default props for the component
     */
    protected function getDefaultProps(): array
    {
        return [];
    }
    
    /**
     * Set a prop value
     */
    public function prop(string $key, mixed $value): static
    {
        $this->props[$key] = $value;
        return $this;
    }
    
    /**
     * Set HTML attributes
     */
    public function attr(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }
    
    /**
     * Add CSS class
     */
    public function addClass(string $class): static
    {
        $existing = $this->attributes['class'] ?? '';
        $this->attributes['class'] = trim($existing . ' ' . $class);
        return $this;
    }
    
    /**
     * Render the component and return HTML
     */
    abstract public function render(): string;
    
    /**
     * Output the component directly
     */
    public function output(): void
    {
        echo $this->render();
    }
    
    /**
     * Magic method to render component as string
     */
    public function __toString(): string
    {
        return $this->render();
    }
    
    /**
     * Build HTML attributes string
     */
    protected function buildAttributes(array $extra = []): string
    {
        $attrs = array_merge($this->attributes, $extra);
        $parts = [];
        
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = htmlspecialchars($key);
            } elseif ($value !== false && $value !== null) {
                $parts[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        return implode(' ', $parts);
    }
    
    /**
     * Escape HTML
     */
    protected function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

