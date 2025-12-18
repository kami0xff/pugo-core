<?php
/**
 * Pugo Core - Tabs Component
 * 
 * Language tabs, category tabs, section tabs - all unified.
 */

namespace Pugo\Components;

class Tabs extends Component
{
    protected function getDefaultProps(): array
    {
        return [
            'items' => [],
            'active' => null,
            'base_url' => '',
            'param' => 'tab',
            'size' => 'default',  // default, small, large
            'variant' => 'default',  // default, pills, buttons
            'show_count' => true,
        ];
    }
    
    /**
     * Add a tab item
     */
    public function addItem(string $key, string $label, array $options = []): static
    {
        $this->props['items'][$key] = array_merge([
            'label' => $label,
            'icon' => null,
            'flag' => null,
            'count' => null,
            'color' => null,
            'url' => null,
        ], $options);
        
        return $this;
    }
    
    /**
     * Set multiple items at once
     */
    public function items(array $items): static
    {
        foreach ($items as $key => $item) {
            if (is_string($item)) {
                $this->addItem($key, $item);
            } else {
                $this->addItem($key, $item['label'] ?? $key, $item);
            }
        }
        return $this;
    }
    
    /**
     * Create language tabs from config
     */
    public static function languages(array $languages, string $active, string $baseUrl = '', array $counts = []): static
    {
        $tabs = new static([
            'active' => $active,
            'base_url' => $baseUrl,
            'param' => 'lang',
        ]);
        
        foreach ($languages as $code => $lang) {
            $tabs->addItem($code, $lang['name'] ?? $code, [
                'flag' => $lang['flag'] ?? null,
                'count' => $counts[$code] ?? null,
            ]);
        }
        
        return $tabs;
    }
    
    /**
     * Create section tabs
     */
    public static function sections(array $sections, string $active, string $baseUrl = '', array $counts = []): static
    {
        $tabs = new static([
            'active' => $active,
            'base_url' => $baseUrl,
            'param' => 'section',
            'variant' => 'buttons',
        ]);
        
        foreach ($sections as $key => $section) {
            $tabs->addItem($key, $section['name'] ?? $key, [
                'color' => $section['color'] ?? null,
                'count' => $counts[$key] ?? null,
            ]);
        }
        
        return $tabs;
    }
    
    /**
     * Create category tabs
     */
    public static function categories(array $categories, string $active, string $baseUrl = ''): static
    {
        $tabs = new static([
            'active' => $active,
            'base_url' => $baseUrl,
            'param' => 'category',
            'variant' => 'pills',
        ]);
        
        $tabs->addItem('all', 'All');
        
        foreach ($categories as $key => $cat) {
            $tabs->addItem($key, $cat['label'] ?? $key, [
                'color' => $cat['color'] ?? null,
            ]);
        }
        
        return $tabs;
    }
    
    public function render(): string
    {
        $items = $this->props['items'];
        $active = $this->props['active'];
        $baseUrl = $this->props['base_url'];
        $param = $this->props['param'];
        $variant = $this->props['variant'];
        $showCount = $this->props['show_count'];
        
        if (empty($items)) {
            return '';
        }
        
        $class = 'pugo-tabs pugo-tabs--' . $variant;
        
        $html = '<div class="' . $class . '">';
        
        foreach ($items as $key => $item) {
            $isActive = $key === $active;
            $label = $item['label'] ?? $key;
            $flag = $item['flag'] ?? null;
            $count = $item['count'] ?? null;
            $color = $item['color'] ?? null;
            $url = $item['url'] ?? $this->buildUrl($baseUrl, $param, $key);
            
            $tabClass = 'pugo-tab';
            if ($isActive) {
                $tabClass .= ' active';
            }
            
            $style = $color ? '--tab-color: ' . $this->e($color) : '';
            
            $html .= '<a href="' . $this->e($url) . '" class="' . $tabClass . '" style="' . $style . '">';
            
            if ($flag) {
                $html .= '<span class="pugo-tab-flag">' . $this->e($flag) . '</span>';
            }
            
            if ($color && $variant === 'buttons') {
                $html .= '<span class="pugo-tab-dot" style="background: ' . $this->e($color) . '"></span>';
            }
            
            $html .= '<span class="pugo-tab-label">' . $this->e($label) . '</span>';
            
            if ($showCount && $count !== null) {
                $html .= '<span class="pugo-tab-count">' . intval($count) . '</span>';
            }
            
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    protected function buildUrl(string $baseUrl, string $param, string $value): string
    {
        // Parse existing URL
        $parts = parse_url($baseUrl);
        $query = [];
        
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        
        // Add/update the param
        $query[$param] = $value;
        
        // Rebuild URL
        $path = $parts['path'] ?? '';
        return $path . '?' . http_build_query($query);
    }
}

