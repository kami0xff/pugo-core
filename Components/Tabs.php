<?php
/**
 * Pugo Core - Tabs Component
 * 
 * Unified tab navigation for languages, sections, categories, and custom use cases.
 * Uses value objects for type safety and validation.
 */

namespace Pugo\Components;

/**
 * Represents a single tab item with all its visual and behavioral properties.
 */
final class TabItem
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly ?string $flag = null,
        public readonly ?int $count = null,
        public readonly ?string $color = null,
        public readonly ?string $url = null,
        public readonly ?string $countryCode = null,
    ) {
        if (empty($key)) {
            throw new \InvalidArgumentException('TabItem key cannot be empty');
        }
        if (empty($label)) {
            throw new \InvalidArgumentException('TabItem label cannot be empty');
        }
        if ($color !== null && !$this->isValidColor($color)) {
            throw new \InvalidArgumentException("Invalid color format: {$color}");
        }
    }

    /**
     * Create a language tab item
     */
    public static function language(string $code, string $name, ?string $flag = null, ?int $count = null): self
    {
        return new self(
            key: $code,
            label: $name,
            flag: $flag,
            count: $count,
            countryCode: $code,
        );
    }

    /**
     * Create a section tab item
     */
    public static function section(string $key, string $name, ?string $color = null, ?int $count = null): self
    {
        return new self(
            key: $key,
            label: $name,
            color: $color,
            count: $count,
        );
    }

    /**
     * Create a category tab item
     */
    public static function category(string $key, string $label, ?string $color = null): self
    {
        return new self(
            key: $key,
            label: $label,
            color: $color,
        );
    }

    /**
     * Create from array (for backwards compatibility)
     */
    public static function fromArray(string $key, array $data): self
    {
        return new self(
            key: $key,
            label: $data['label'] ?? $data['name'] ?? $key,
            icon: $data['icon'] ?? null,
            flag: $data['flag'] ?? null,
            count: isset($data['count']) ? (int) $data['count'] : null,
            color: $data['color'] ?? null,
            url: $data['url'] ?? null,
            countryCode: $data['countryCode'] ?? $data['code'] ?? null,
        );
    }

    /**
     * Create a copy with a custom URL
     */
    public function withUrl(string $url): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            icon: $this->icon,
            flag: $this->flag,
            count: $this->count,
            color: $this->color,
            url: $url,
            countryCode: $this->countryCode,
        );
    }

    /**
     * Create a copy with updated count
     */
    public function withCount(int $count): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            icon: $this->icon,
            flag: $this->flag,
            count: $count,
            color: $this->color,
            url: $this->url,
            countryCode: $this->countryCode,
        );
    }

    private function isValidColor(string $color): bool
    {
        // Accept hex colors, rgb/rgba, hsl, or CSS color names
        return preg_match('/^(#[0-9a-fA-F]{3,8}|rgb|hsl|[a-z]+)/i', $color) === 1;
    }
}

/**
 * Configuration properties for the Tabs component.
 */
final class TabsProps
{
    /** @var TabItem[] */
    public readonly array $items;

    public function __construct(
        array $items = [],
        public readonly ?string $activeKey = null,
        public readonly string $baseUrl = '',
        public readonly string $param = 'tab',
        public readonly TabSize $size = TabSize::Default ,
        public readonly TabVariant $variant = TabVariant::Default ,
        public readonly bool $showCount = true,
    ) {
        // Validate and convert items to TabItem objects
        $validatedItems = [];
        foreach ($items as $key => $item) {
            if ($item instanceof TabItem) {
                $validatedItems[$item->key] = $item;
            } elseif (is_array($item)) {
                $validatedItems[$key] = TabItem::fromArray($key, $item);
            } elseif (is_string($item)) {
                $validatedItems[$key] = new TabItem(key: $key, label: $item);
            } else {
                throw new \InvalidArgumentException(
                    "Invalid item type at key '{$key}': expected TabItem, array, or string"
                );
            }
        }
        $this->items = $validatedItems;
    }

    /**
     * Create new props with an additional item
     */
    public function withItem(TabItem $item): self
    {
        $items = $this->items;
        $items[$item->key] = $item;

        return new self(
            items: $items,
            activeKey: $this->activeKey,
            baseUrl: $this->baseUrl,
            param: $this->param,
            size: $this->size,
            variant: $this->variant,
            showCount: $this->showCount,
        );
    }

    /**
     * Create new props with different active key
     */
    public function withActiveKey(string $key): self
    {
        return new self(
            items: $this->items,
            activeKey: $key,
            baseUrl: $this->baseUrl,
            param: $this->param,
            size: $this->size,
            variant: $this->variant,
            showCount: $this->showCount,
        );
    }

    /**
     * Check if a key is the active tab
     */
    public function isActive(string $key): bool
    {
        return $this->activeKey === $key;
    }

    /**
     * Get a specific item by key
     */
    public function getItem(string $key): ?TabItem
    {
        return $this->items[$key] ?? null;
    }
}

/**
 * Tab size options
 * for now i think this enum is never used in the admin panel
 */
enum TabSize: string
{
    case Small = 'small';
    case Default = 'default';
    case Large = 'large';
}

/**
 * Tab visual variant options
 * at the moment i think we only have the buttonn variant in use 
 */
enum TabVariant: string
{
    case Default = 'default';
    case Pills = 'pills';
    case Buttons = 'buttons';
    case Underline = 'underline';
}

/**
 * Tabs Component
 * 
 * Renders a set of tab navigation items with support for:
 * - Language switching (with flags)
 * - Section navigation (with colored indicators)
 * - Category filtering (pill style)
 * - Custom tab configurations
 * 
 * @example Basic usage:
 * ```php
 * $tabs = Tabs::create()
 *     ->addItem(TabItem::category('all', 'All'))
 *     ->addItem(TabItem::category('active', 'Active', '#22c55e'))
 *     ->setActive('all');
 * echo $tabs->render();
 * ```
 * 
 * @example Language tabs:
 * ```php
 * $tabs = Tabs::forLanguages($config['languages'], $currentLang, '/admin/articles');
 * echo $tabs->render();
 * ```
 */
class Tabs extends Component
{
    private TabsProps $tabsProps;

    public function __construct(TabsProps|array $props = [])
    {
        if ($props instanceof TabsProps) {
            $this->tabsProps = $props;
            parent::__construct([]);
        } else {
            // Legacy array support
            $this->tabsProps = new TabsProps(
                items: $props['items'] ?? [],
                activeKey: $props['active'] ?? $props['activeKey'] ?? null,
                baseUrl: $props['base_url'] ?? $props['baseUrl'] ?? '',
                param: $props['param'] ?? 'tab',
                size: $this->parseSize($props['size'] ?? 'default'),
                variant: $this->parseVariant($props['variant'] ?? 'default'),
                showCount: $props['show_count'] ?? $props['showCount'] ?? true,
            );
            parent::__construct($props);
        }
    }

    /**
     * Create a new Tabs instance with fluent interface
     */
    public static function create(TabsProps|array $props = []): static
    {
        return new static($props);
    }

    /**
     * Add a tab item (fluent interface)
     */
    public function addItem(TabItem $item): static
    {
        $this->tabsProps = $this->tabsProps->withItem($item);
        return $this;
    }

    /**
     * Set the active tab key (fluent interface)
     */
    public function setActive(string $key): static
    {
        $this->tabsProps = $this->tabsProps->withActiveKey($key);
        return $this;
    }

    /**
     * Create language tabs from configuration
     * 
     * @param array $languages Language config: ['en' => ['name' => 'English', 'flag' => '🇬🇧'], ...]
     * @param string $activeCode Currently active language code
     * @param string $baseUrl Base URL for tab links
     * @param array $counts Optional item counts per language: ['en' => 42, 'fr' => 38, ...]
     */
    public static function forLanguages(
        array $languages,
        string $activeCode,
        string $baseUrl = '',
        array $counts = []
    ): static {
        $items = [];
        foreach ($languages as $code => $lang) {
            $items[] = TabItem::language(
                code: $code,
                name: $lang['name'] ?? $code,
                flag: $lang['flag'] ?? null,
                count: $counts[$code] ?? null,
            );
        }

        return new static(new TabsProps(
            items: $items,
            activeKey: $activeCode,
            baseUrl: $baseUrl,
            param: 'lang',
            variant: TabVariant::Default ,
        ));
    }

    /**
     * Create section tabs from configuration
     * 
     * @param array $sections Section config: ['blog' => ['name' => 'Blog', 'color' => '#3b82f6'], ...]
     * @param string $activeKey Currently active section key
     * @param string $baseUrl Base URL for tab links
     * @param array $counts Optional item counts per section
     */
    public static function forSections(
        array $sections,
        string $activeKey,
        string $baseUrl = '',
        array $counts = []
    ): static {
        $items = [];
        foreach ($sections as $key => $section) {
            $items[] = TabItem::section(
                key: $key,
                name: $section['name'] ?? $key,
                color: $section['color'] ?? null,
                count: $counts[$key] ?? null,
            );
        }

        return new static(new TabsProps(
            items: $items,
            activeKey: $activeKey,
            baseUrl: $baseUrl,
            param: 'section',
            variant: TabVariant::Buttons,
        ));
    }

    /**
     * Create category tabs with "All" as first option
     * 
     * @param array $categories Category config: ['news' => ['label' => 'News', 'color' => '#f59e0b'], ...]
     * @param string $activeKey Currently active category (or 'all')
     * @param string $baseUrl Base URL for tab links
     */
    public static function forCategories(
        array $categories,
        string $activeKey,
        string $baseUrl = ''
    ): static {
        $items = [TabItem::category('all', 'All')];

        foreach ($categories as $key => $cat) {
            $items[] = TabItem::category(
                key: $key,
                label: $cat['label'] ?? $cat['name'] ?? $key,
                color: $cat['color'] ?? null,
            );
        }

        return new static(new TabsProps(
            items: $items,
            activeKey: $activeKey,
            baseUrl: $baseUrl,
            param: 'category',
            variant: TabVariant::Pills,
        ));
    }

    /**
     * Render the tabs component to HTML
     */
    public function render(): string
    {
        $props = $this->tabsProps;

        if (empty($props->items)) {
            return '';
        }

        $cssClass = $this->buildCssClass($props);

        $html = '<div class="' . $cssClass . '">';

        foreach ($props->items as $item) {
            $html .= $this->renderTab($item, $props);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a single tab item
     */
    private function renderTab(TabItem $item, TabsProps $props): string
    {
        $isActive = $props->isActive($item->key);
        $url = $item->url ?? $this->buildUrl($props->baseUrl, $props->param, $item->key);

        $tabClass = 'pugo-tab' . ($isActive ? ' active' : '');
        $style = $item->color ? '--tab-color: ' . $this->e($item->color) : '';

        $html = '<a href="' . $this->e($url) . '" class="' . $tabClass . '"';
        if ($style) {
            $html .= ' style="' . $style . '"';
        }
        $html .= '>';

        // Flag (for language tabs)
        if ($item->flag) {
            $html .= '<span class="pugo-tab-flag">' . $this->e($item->flag) . '</span>';
        }

        // Icon
        if ($item->icon) {
            $html .= '<span class="pugo-tab-icon">' . $item->icon . '</span>';
        }

        // Colored dot (for button variant with color)
        if ($item->color && $props->variant === TabVariant::Buttons) {
            $html .= '<span class="pugo-tab-dot" style="background: ' . $this->e($item->color) . '"></span>';
        }

        // Label
        $html .= '<span class="pugo-tab-label">' . $this->e($item->label) . '</span>';

        // Count badge
        if ($props->showCount && $item->count !== null) {
            $html .= '<span class="pugo-tab-count">' . $item->count . '</span>';
        }

        $html .= '</a>';

        return $html;
    }

    /**
     * Build CSS class string for the tabs container
     */
    private function buildCssClass(TabsProps $props): string
    {
        $classes = ['pugo-tabs'];
        $classes[] = 'pugo-tabs--' . $props->variant->value;
        $classes[] = 'pugo-tabs--' . $props->size->value;

        return implode(' ', $classes);
    }

    /**
     * Build URL with query parameter
     */
    private function buildUrl(string $baseUrl, string $param, string $value): string
    {
        $parts = parse_url($baseUrl);
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query[$param] = $value;

        $path = $parts['path'] ?? '';
        return $path . '?' . http_build_query($query);
    }

    /**
     * Parse size string to enum
     */
    private function parseSize(string $size): TabSize
    {
        return match ($size) {
            'small' => TabSize::Small,
            'large' => TabSize::Large,
            default => TabSize::Default ,
        };
    }

    /**
     * Parse variant string to enum
     */
    private function parseVariant(string $variant): TabVariant
    {
        return match ($variant) {
            'pills' => TabVariant::Pills,
            'buttons' => TabVariant::Buttons,
            'underline' => TabVariant::Underline,
            default => TabVariant::Default ,
        };
    }

    // -------------------------------------------------------------------------
    // Legacy compatibility methods (deprecated, use new API)
    // -------------------------------------------------------------------------

    /**
     * @deprecated Use forLanguages() instead
     */
    public static function languages(array $languages, string $active, string $baseUrl = '', array $counts = []): static
    {
        return static::forLanguages($languages, $active, $baseUrl, $counts);
    }

    /**
     * @deprecated Use forSections() instead
     */
    public static function sections(array $sections, string $active, string $baseUrl = '', array $counts = []): static
    {
        return static::forSections($sections, $active, $baseUrl, $counts);
    }

    /**
     * @deprecated Use forCategories() instead
     */
    public static function categories(array $categories, string $active, string $baseUrl = ''): static
    {
        return static::forCategories($categories, $active, $baseUrl);
    }

    protected function getDefaultProps(): array
    {
        return [];
    }
}
