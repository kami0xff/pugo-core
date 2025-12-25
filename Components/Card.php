<?php
/**
 * Pugo Core - Card Component
 * 
 * A card container with optional header, footer, and icon.
 * Used for grouping related content with visual structure.
 * 
 * Icons are loaded from the centralized Icons library (assets/Icons.php)
 */

namespace Pugo\Components;

use Pugo\Assets\Icons;

// Load the centralized Icons library
require_once dirname(__DIR__) . '/assets/Icons.php';

/**
 * Card configuration properties
 */
final class CardProps
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $icon = null,  // Icon name string (e.g., 'settings', 'file-text')
        public readonly ?string $headerRight = null,
        public readonly ?string $footer = null,
        public readonly ?string $bodyClass = null,
        public readonly bool $scrollable = false,
        public readonly ?string $maxHeight = null,
        public readonly ?string $id = null,
        public readonly ?string $color = null,
    ) {
    }

    /**
     * Create a basic card with just a title
     */
    public static function titled(string $title, ?string $icon = null): self
    {
        return new self(title: $title, icon: $icon);
    }

    /**
     * Create with scrollable body
     */
    public function scrollable(string $maxHeight = '500px'): self
    {
        return new self(
            title: $this->title,
            icon: $this->icon,
            headerRight: $this->headerRight,
            footer: $this->footer,
            bodyClass: $this->bodyClass,
            scrollable: true,
            maxHeight: $maxHeight,
            id: $this->id,
            color: $this->color,
        );
    }

    /**
     * Add header right content
     */
    public function withHeaderRight(string $content): self
    {
        return new self(
            title: $this->title,
            icon: $this->icon,
            headerRight: $content,
            footer: $this->footer,
            bodyClass: $this->bodyClass,
            scrollable: $this->scrollable,
            maxHeight: $this->maxHeight,
            id: $this->id,
            color: $this->color,
        );
    }

    /**
     * Add footer content
     */
    public function withFooter(string $content): self
    {
        return new self(
            title: $this->title,
            icon: $this->icon,
            headerRight: $this->headerRight,
            footer: $content,
            bodyClass: $this->bodyClass,
            scrollable: $this->scrollable,
            maxHeight: $this->maxHeight,
            id: $this->id,
            color: $this->color,
        );
    }

    /**
     * Set a color theme
     */
    public function withColor(string $color): self
    {
        return new self(
            title: $this->title,
            icon: $this->icon,
            headerRight: $this->headerRight,
            footer: $this->footer,
            bodyClass: $this->bodyClass,
            scrollable: $this->scrollable,
            maxHeight: $this->maxHeight,
            id: $this->id,
            color: $color,
        );
    }

    /**
     * Set a different icon
     */
    public function withIcon(string $icon): self
    {
        return new self(
            title: $this->title,
            icon: $icon,
            headerRight: $this->headerRight,
            footer: $this->footer,
            bodyClass: $this->bodyClass,
            scrollable: $this->scrollable,
            maxHeight: $this->maxHeight,
            id: $this->id,
            color: $this->color,
        );
    }
}

/**
 * Card Component
 * 
 * @example Basic card with title:
 * ```php
 * echo Card::create(CardProps::titled('Settings', 'settings'))
 *     ->setContent('<p>Card content here</p>');
 * ```
 * 
 * @example Scrollable card:
 * ```php
 * echo Card::create(CardProps::titled('Long List')->scrollable('300px'))
 *     ->setContent($longListHtml);
 * ```
 * 
 * @example Simple card without header:
 * ```php
 * echo Card::simple()->setContent('Just content, no header');
 * ```
 * 
 * @example Card with footer:
 * ```php
 * $props = CardProps::titled('Form')
 *     ->withFooter('<button type="submit">Save</button>');
 * echo Card::create($props)->setContent($formHtml);
 * ```
 */
class Card extends Component
{
    private CardProps $cardProps;
    private string $content = '';

    public function __construct(CardProps|array $props = [])
    {
        if ($props instanceof CardProps) {
            $this->cardProps = $props;
            parent::__construct([]);
        } else {
            // Legacy array support
            $this->cardProps = new CardProps(
                title: $props['title'] ?? null,
                icon: $props['icon'] ?? null,  // Now just a string
                headerRight: $props['header_right'] ?? $props['headerRight'] ?? null,
                footer: $props['footer'] ?? null,
                bodyClass: $props['body_class'] ?? $props['bodyClass'] ?? null,
                scrollable: $props['scrollable'] ?? false,
                maxHeight: $props['max_height'] ?? $props['maxHeight'] ?? null,
                id: $props['id'] ?? null,
                color: $props['color'] ?? null,
            );
            $this->content = $props['content'] ?? '';
            parent::__construct($props);
        }
    }

    /**
     * Create card with props object
     */
    public static function create(CardProps $props): static
    {
        return new static($props);
    }

    /**
     * Create a simple card without header
     */
    public static function simple(): static
    {
        return new static(new CardProps());
    }

    /**
     * Create a titled card
     */
    public static function titled(string $title, ?string $icon = null): static
    {
        return new static(CardProps::titled($title, $icon));
    }

    /**
     * Set the card content (fluent interface)
     */
    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Alias for setContent for convenience
     */
    public function content(string $content): static
    {
        return $this->setContent($content);
    }

    /**
     * Render the card
     */
    public function render(): string
    {
        $props = $this->cardProps;

        $hasHeader = $props->title || $props->icon || $props->headerRight;

        // Build body style
        $bodyStyle = '';
        if ($props->scrollable || $props->maxHeight) {
            $height = $props->maxHeight ?? '500px';
            $bodyStyle = "max-height: {$height}; overflow-y: auto;";
        }

        // Build container attributes
        $containerClass = 'pugo-card';
        $containerStyle = '';
        if ($props->color) {
            $containerStyle = '--card-color: ' . $this->e($props->color);
        }

        $attrs = ['class' => $containerClass];
        if ($props->id) {
            $attrs['id'] = $props->id;
        }
        if ($containerStyle) {
            $attrs['style'] = $containerStyle;
        }

        $html = '<div' . $this->buildAttributes($attrs) . '>';

        // Header
        if ($hasHeader) {
            $html .= $this->renderHeader($props);
        }

        // Body
        $bodyClass = 'pugo-card-body';
        if ($props->bodyClass) {
            $bodyClass .= ' ' . $props->bodyClass;
        }

        $html .= '<div class="' . $this->e($bodyClass) . '"';
        if ($bodyStyle) {
            $html .= ' style="' . $bodyStyle . '"';
        }
        $html .= '>';
        $html .= $this->content;
        $html .= '</div>';

        // Footer
        if ($props->footer) {
            $html .= '<div class="pugo-card-footer">' . $props->footer . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the card header
     */
    private function renderHeader(CardProps $props): string
    {
        $html = '<div class="pugo-card-header">';
        $html .= '<div class="pugo-card-title">';

        // Use centralized Icons library
        if ($props->icon) {
            $html .= Icons::render($props->icon, 20);
        }

        if ($props->title) {
            $html .= $this->e($props->title);
        }

        $html .= '</div>';

        if ($props->headerRight) {
            $html .= '<div class="pugo-card-header-right">' . $props->headerRight . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    protected function getDefaultProps(): array
    {
        return [];
    }
}

/**
 * Backward compatibility: CardIcon enum that delegates to Icons class
 * @deprecated Use icon name strings directly (e.g., 'settings' instead of CardIcon::Settings)
 */
enum CardIcon: string
{
    case FileText = 'file-text';
    case Database = 'database';
    case Grid = 'grid';
    case Eye = 'eye';
    case Settings = 'settings';
    case HelpCircle = 'help-circle';
    case Video = 'video';
    case Book = 'book';
    case Save = 'save';
    case Plus = 'plus';
    case Trash = 'trash';
    case Edit = 'edit';
    case Users = 'users';
    case Folder = 'folder';
    case Image = 'image';
    case Search = 'search';
    case Star = 'star';
    case Heart = 'heart';
    case Zap = 'zap';
    case Shield = 'shield';
    case Globe = 'globe';
    case Code = 'code';
    case Terminal = 'terminal';
    case Layout = 'layout';

    /**
     * Get the SVG path for this icon from centralized Icons library
     */
    public function path(): string
    {
        return Icons::path($this->value);
    }
}
