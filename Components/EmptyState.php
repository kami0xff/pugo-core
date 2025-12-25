<?php
/**
 * Pugo Core - Empty State Component
 * 
 * Displays a placeholder when content lists are empty.
 * Includes an icon, title, optional description, and call-to-action.
 * 
 * Icons are loaded from the centralized Icons library (assets/Icons.php)
 */

namespace Pugo\Components;

use Pugo\Assets\Icons;

// Load the centralized Icons library
require_once dirname(__DIR__) . '/assets/Icons.php';

/**
 * Call-to-action button for empty states
 */
final class EmptyStateAction
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $onclick = null,
        public readonly ?string $href = null,
        public readonly ButtonVariant $variant = ButtonVariant::Primary,
    ) {
        if (empty(trim($label))) {
            throw new \InvalidArgumentException('Action label cannot be empty');
        }
        if ($onclick === null && $href === null) {
            throw new \InvalidArgumentException('Action must have onclick or href');
        }
    }

    /**
     * Create a button action
     */
    public static function button(string $label, string $onclick): self
    {
        return new self(label: $label, onclick: $onclick);
    }

    /**
     * Create a link action
     */
    public static function link(string $label, string $href): self
    {
        return new self(label: $label, href: $href);
    }
}

/**
 * Empty state configuration properties
 */
final class EmptyStateProps
{
    public function __construct(
        public readonly string $title = 'No items yet',
        public readonly ?string $description = null,
        public readonly string $icon = 'file-text',  // Icon name from centralized Icons
        public readonly ?EmptyStateAction $action = null,
        public readonly ?string $id = 'pugo-empty-state',
    ) {
    }

    /**
     * Create for articles/content
     */
    public static function forContent(string $contentType = 'articles'): self
    {
        return new self(
            title: "No {$contentType} yet",
            description: "Create your first {$contentType} to get started.",
            icon: 'file-text',
        );
    }

    /**
     * Create for search results
     */
    public static function forSearch(string $query = ''): self
    {
        $description = $query
            ? "No results found for \"{$query}\""
            : 'Try adjusting your search or filters';

        return new self(
            title: 'No results found',
            description: $description,
            icon: 'search',
        );
    }

    /**
     * Create for media library
     */
    public static function forMedia(): self
    {
        return new self(
            title: 'No media files',
            description: 'Upload images or videos to get started.',
            icon: 'image',
        );
    }

    /**
     * Add an action to this empty state
     */
    public function withAction(EmptyStateAction $action): self
    {
        return new self(
            title: $this->title,
            description: $this->description,
            icon: $this->icon,
            action: $action,
            id: $this->id,
        );
    }

    /**
     * Set custom description
     */
    public function withDescription(string $description): self
    {
        return new self(
            title: $this->title,
            description: $description,
            icon: $this->icon,
            action: $this->action,
            id: $this->id,
        );
    }
    
    /**
     * Set custom icon
     */
    public function withIcon(string $icon): self
    {
        return new self(
            title: $this->title,
            description: $this->description,
            icon: $icon,
            action: $this->action,
            id: $this->id,
        );
    }
}

/**
 * Empty State Component
 * 
 * @example Basic usage:
 * ```php
 * echo EmptyState::create(new EmptyStateProps(
 *     title: 'No articles yet',
 *     description: 'Create your first article to get started.',
 *     icon: 'file-text'
 * ));
 * ```
 * 
 * @example With action:
 * ```php
 * echo EmptyState::forContent('articles')
 *     ->withAction(EmptyStateAction::button('Create Article', 'openEditor()'));
 * ```
 * 
 * @example For search:
 * ```php
 * echo EmptyState::forSearch($searchQuery);
 * ```
 */
class EmptyState extends Component
{
    private EmptyStateProps $emptyStateProps;

    public function __construct(EmptyStateProps|array $props = [])
    {
        if ($props instanceof EmptyStateProps) {
            $this->emptyStateProps = $props;
            parent::__construct([]);
        } else {
            // Legacy array support
            $action = null;
            if (!empty($props['action_label']) && !empty($props['action_onclick'])) {
                $action = EmptyStateAction::button(
                    $props['action_label'],
                    $props['action_onclick']
                );
            }

            $this->emptyStateProps = new EmptyStateProps(
                title: $props['title'] ?? 'No items yet',
                description: $props['description'] ?? null,
                icon: $props['icon'] ?? 'file-text',
                action: $action,
                id: $props['id'] ?? 'pugo-empty-state',
            );
            parent::__construct($props);
        }
    }

    /**
     * Create with props object
     */
    public static function create(EmptyStateProps $props): static
    {
        return new static($props);
    }

    /**
     * Create for content type
     */
    public static function forContent(string $contentType = 'articles'): static
    {
        return new static(EmptyStateProps::forContent($contentType));
    }

    /**
     * Create for search results
     */
    public static function forSearch(string $query = ''): static
    {
        return new static(EmptyStateProps::forSearch($query));
    }

    /**
     * Create for media library
     */
    public static function forMedia(): static
    {
        return new static(EmptyStateProps::forMedia());
    }

    /**
     * Add an action (fluent interface)
     */
    public function withAction(EmptyStateAction $action): static
    {
        $this->emptyStateProps = $this->emptyStateProps->withAction($action);
        return $this;
    }

    /**
     * Set description (fluent interface)
     */
    public function withDescription(string $description): static
    {
        $this->emptyStateProps = $this->emptyStateProps->withDescription($description);
        return $this;
    }

    /**
     * Render the empty state
     */
    public function render(): string
    {
        $props = $this->emptyStateProps;

        $attrs = [];
        $attrs['class'] = 'pugo-empty-state';
        if ($props->id) {
            $attrs['id'] = $props->id;
        }

        $html = '<div' . $this->buildAttributes($attrs) . '>';

        // Icon from centralized Icons library
        $html .= Icons::render($props->icon, 48, 'pugo-empty-state-icon');

        // Title
        $html .= '<p class="pugo-empty-state-title">' . $this->e($props->title) . '</p>';

        // Description
        if ($props->description) {
            $html .= '<p class="pugo-empty-state-desc">' . $this->e($props->description) . '</p>';
        }

        // Action button
        if ($props->action) {
            $html .= $this->renderAction($props->action);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the action button/link
     */
    private function renderAction(EmptyStateAction $action): string
    {
        $class = 'pugo-btn pugo-btn--' . $action->variant->value;

        if ($action->href) {
            return '<a href="' . $this->e($action->href) . '" class="' . $class . '">'
                . $this->e($action->label)
                . '</a>';
        }

        return '<button type="button" class="' . $class . '" onclick="' . $this->e($action->onclick) . '">'
            . $this->e($action->label)
            . '</button>';
    }

    protected function getDefaultProps(): array
    {
        return [];
    }
}

/**
 * Backward compatibility: EmptyStateIcon enum that maps to icon names
 * @deprecated Use icon name strings directly (e.g., 'file-text' instead of EmptyStateIcon::FileText)
 */
enum EmptyStateIcon: string
{
    case FileText = 'file-text';
    case Video = 'video';
    case HelpCircle = 'help-circle';
    case Book = 'book';
    case Grid = 'grid';
    case Database = 'database';
    case Eye = 'eye';
    case Search = 'search';
    case Inbox = 'inbox';
    case Image = 'image';
    case Users = 'users';
    case Folder = 'folder';
    case Settings = 'settings';

    /**
     * Get the SVG path for this icon from centralized Icons library
     */
    public function path(): string
    {
        return Icons::path($this->value);
    }
}
