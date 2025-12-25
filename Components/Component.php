<?php
/**
 * Pugo Core - Base Component
 * 
 * All UI components extend this base class.
 * Provides common functionality for rendering, escaping, and attribute handling.
 */

namespace Pugo\Components;

use Pugo\Assets\Icons;

/**
 * Base class for all Pugo UI components.
 * 
 * Components can be instantiated in two ways:
 * 1. With a typed Props object (recommended):
 *    ```php
 *    $toast = new Toast(new ToastProps(message: 'Saved!', type: ToastType::Success));
 *    ```
 * 
 * 2. With an array (legacy, for backwards compatibility):
 *    ```php
 *    $toast = new Toast(['message' => 'Saved!', 'type' => 'success']);
 *    ```
 */
abstract class Component
{
    /** @var array Legacy props array (deprecated, use typed Props objects) */
    protected array $props = [];

    /** @var array HTML attributes to add to the root element */
    protected array $attributes = [];

    /**
     * Create a new component instance
     * 
     * @param array $props Legacy array-based props (prefer typed Props objects)
     */
    public function __construct(array $props = [])
    {
        $this->props = array_merge($this->getDefaultProps(), $props);
    }

    /**
     * Get default props for legacy array-based initialization
     * 
     * @deprecated Prefer using typed Props objects instead
     */
    protected function getDefaultProps(): array
    {
        return [];
    }

    /**
     * Set a prop value (legacy API)
     * 
     * @deprecated Use typed Props objects with immutable updates
     */
    public function setProp(string $key, mixed $value): static
    {
        $this->props[$key] = $value;
        return $this;
    }

    /**
     * Get a prop value
     */
    public function getProp(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    /**
     * Set an HTML attribute on the root element
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get an HTML attribute
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Add a CSS class to the root element
     */
    public function addClass(string $class): static
    {
        $existing = $this->attributes['class'] ?? '';
        $this->attributes['class'] = trim($existing . ' ' . $class);
        return $this;
    }

    /**
     * Set multiple CSS classes (replaces existing)
     */
    public function setClass(string $class): static
    {
        $this->attributes['class'] = $class;
        return $this;
    }

    /**
     * Set the id attribute
     */
    public function setId(string $id): static
    {
        $this->attributes['id'] = $id;
        return $this;
    }

    /**
     * Render the component to HTML string
     */
    abstract public function render(): string;

    /**
     * Output the component directly to the browser
     */
    public function renderOutput(): void
    {
        echo $this->render();
    }

    /**
     * Alias for renderOutput() for convenience
     */
    public function output(): void
    {
        $this->renderOutput();
    }

    /**
     * Magic method to render component as string
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            // __toString cannot throw exceptions in PHP < 8
            return '<!-- Component render error: ' . htmlspecialchars($e->getMessage()) . ' -->';
        }
    }

    /**
     * Build HTML attributes string from array
     * 
     * @param array $extra Additional attributes to merge
     * @return string Space-prefixed attribute string (e.g., ' class="foo" id="bar"')
     */
    protected function buildAttributes(array $extra = []): string
    {
        $attrs = array_merge($this->attributes, $extra);

        if (empty($attrs)) {
            return '';
        }

        $parts = [];

        foreach ($attrs as $key => $value) {
            // Boolean true = attribute with no value (e.g., "disabled")
            if ($value === true) {
                $parts[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            }
            // Skip false/null values
            elseif ($value !== false && $value !== null) {
                $parts[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
                    . '="'
                    . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
                    . '"';
            }
        }

        return empty($parts) ? '' : ' ' . implode(' ', $parts);
    }

    /**
     * Escape HTML entities for safe output
     */
    protected function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Conditionally include content
     */
    protected function when(bool $condition, string $content): string
    {
        return $condition ? $content : '';
    }

    /**
     * Render an SVG icon from the centralized Icons library
     * 
     * @param string $nameOrPath Icon name (e.g., 'home', 'settings') or raw SVG path
     * @param int $size Icon size in pixels
     * @param array $attrs Additional SVG attributes
     */
    protected function icon(string $nameOrPath, int $size = 20, array $attrs = []): string
    {
        // Load the centralized Icons class
        require_once dirname(__DIR__) . '/assets/Icons.php';
        
        // If it's an icon name (no < character), use the Icons library
        if (!str_contains($nameOrPath, '<')) {
            $class = $attrs['class'] ?? null;
            unset($attrs['class']);
            return Icons::render($nameOrPath, $size, $class, $attrs);
        }
        
        // Legacy: raw SVG path passed directly
        $defaultAttrs = [
            'width' => $size,
            'height' => $size,
            'viewBox' => '0 0 24 24',
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-width' => '2',
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
        ];

        $attrs = array_merge($defaultAttrs, $attrs);
        $attrString = '';

        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . $key . '="' . $this->e((string) $value) . '"';
        }

        return '<svg' . $attrString . '>' . $nameOrPath . '</svg>';
    }
    
    /**
     * Render an icon by name from the centralized Icons library
     * 
     * @param string $name Icon name (e.g., 'home', 'settings')
     * @param int $size Icon size in pixels
     * @param string|null $class CSS class
     */
    protected function iconByName(string $name, int $size = 20, ?string $class = null): string
    {
        require_once dirname(__DIR__) . '/assets/Icons.php';
        return Icons::render($name, $size, $class);
    }
}
