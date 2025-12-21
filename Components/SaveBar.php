<?php
/**
 * Pugo Core - Save Bar Component
 * 
 * Fixed bottom action bar with save/cancel actions and optional extra buttons.
 * Used for form pages where users need persistent access to save controls.
 */

namespace Pugo\Components;

/**
 * Button variant for styling
 */
enum ButtonVariant: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Danger = 'danger';
    case Ghost = 'ghost';
}

/**
 * Represents a button in the save bar
 */
final class ActionButton
{
    public function __construct(
        public readonly string $label,
        public readonly ButtonVariant $variant = ButtonVariant::Secondary,
        public readonly string $type = 'button',
        public readonly ?string $onclick = null,
        public readonly ?string $href = null,
        public readonly ?string $formId = null,
        public readonly ?string $icon = null,
        public readonly bool $disabled = false,
    ) {
        if (empty(trim($label))) {
            throw new \InvalidArgumentException('Button label cannot be empty');
        }
    }

    /**
     * Create a submit button
     */
    public static function submit(string $label = 'Save Changes', ?string $formId = null): self
    {
        return new self(
            label: $label,
            variant: ButtonVariant::Primary,
            type: 'submit',
            formId: $formId,
            icon: self::saveIconPath(),
        );
    }

    /**
     * Create a cancel link button
     */
    public static function cancel(string $url, string $label = 'Cancel'): self
    {
        return new self(
            label: $label,
            variant: ButtonVariant::Secondary,
            href: $url,
        );
    }

    /**
     * Create a delete button
     */
    public static function delete(string $onclick, string $label = 'Delete'): self
    {
        return new self(
            label: $label,
            variant: ButtonVariant::Danger,
            onclick: $onclick,
        );
    }

    /**
     * Create a preview button
     */
    public static function preview(string $onclick, string $label = 'Preview'): self
    {
        return new self(
            label: $label,
            variant: ButtonVariant::Ghost,
            onclick: $onclick,
        );
    }

    /**
     * Create a disabled copy of this button
     */
    public function disabled(): self
    {
        return new self(
            label: $this->label,
            variant: $this->variant,
            type: $this->type,
            onclick: $this->onclick,
            href: $this->href,
            formId: $this->formId,
            icon: $this->icon,
            disabled: true,
        );
    }

    /**
     * Get the save icon SVG path
     */
    private static function saveIconPath(): string
    {
        return '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>'
            . '<polyline points="17 21 17 13 7 13 7 21"/>'
            . '<polyline points="7 3 7 8 15 8"/>';
    }
}

/**
 * Save bar configuration properties
 */
final class SaveBarProps
{
    /** @var ActionButton[] */
    public readonly array $extraButtons;

    /**
     * @param ActionButton[] $extraButtons
     */
    public function __construct(
        public readonly ?string $info = null,
        public readonly string $saveLabel = 'Save Changes',
        public readonly ?string $cancelUrl = null,
        public readonly string $cancelLabel = 'Cancel',
        array $extraButtons = [],
        public readonly ?string $formId = null,
        public readonly ?string $itemCountId = 'pugo-item-count',
    ) {
        // Validate extra buttons
        foreach ($extraButtons as $button) {
            if (!$button instanceof ActionButton) {
                throw new \InvalidArgumentException(
                    'Extra buttons must be ActionButton instances'
                );
            }
        }
        $this->extraButtons = $extraButtons;
    }

    /**
     * Create with a custom form target
     */
    public function forForm(string $formId): self
    {
        return new self(
            info: $this->info,
            saveLabel: $this->saveLabel,
            cancelUrl: $this->cancelUrl,
            cancelLabel: $this->cancelLabel,
            extraButtons: $this->extraButtons,
            formId: $formId,
            itemCountId: $this->itemCountId,
        );
    }

    /**
     * Add an extra button
     */
    public function withButton(ActionButton $button): self
    {
        return new self(
            info: $this->info,
            saveLabel: $this->saveLabel,
            cancelUrl: $this->cancelUrl,
            cancelLabel: $this->cancelLabel,
            extraButtons: [...$this->extraButtons, $button],
            formId: $this->formId,
            itemCountId: $this->itemCountId,
        );
    }

    /**
     * Set info text
     */
    public function withInfo(string $info): self
    {
        return new self(
            info: $info,
            saveLabel: $this->saveLabel,
            cancelUrl: $this->cancelUrl,
            cancelLabel: $this->cancelLabel,
            extraButtons: $this->extraButtons,
            formId: $this->formId,
            itemCountId: $this->itemCountId,
        );
    }
}

/**
 * Save Bar Component
 * 
 * @example Basic usage:
 * ```php
 * echo SaveBar::create(new SaveBarProps(
 *     cancelUrl: '/admin/articles',
 *     info: 'Editing article'
 * ));
 * ```
 * 
 * @example With extra buttons:
 * ```php
 * $saveBar = SaveBar::create(new SaveBarProps(
 *     cancelUrl: '/admin',
 *     extraButtons: [
 *         ActionButton::preview('previewArticle()'),
 *         ActionButton::delete('confirmDelete()'),
 *     ]
 * ));
 * ```
 * 
 * @example Simple builder:
 * ```php
 * echo SaveBar::simple('/admin/articles')
 *     ->withInfo('3 items selected')
 *     ->addButton(ActionButton::delete('deleteSelected()'));
 * ```
 */
class SaveBar extends Component
{
    private SaveBarProps $saveBarProps;

    public function __construct(SaveBarProps|array $props = [])
    {
        if ($props instanceof SaveBarProps) {
            $this->saveBarProps = $props;
            parent::__construct([]);
        } else {
            // Legacy array support
            $extraButtons = [];
            foreach ($props['extra_buttons'] ?? [] as $btn) {
                if ($btn instanceof ActionButton) {
                    $extraButtons[] = $btn;
                } elseif (is_array($btn)) {
                    $extraButtons[] = new ActionButton(
                        label: $btn['label'] ?? 'Button',
                        variant: $this->parseVariant($btn['variant'] ?? 'secondary'),
                        type: $btn['type'] ?? 'button',
                        onclick: $btn['onclick'] ?? null,
                    );
                }
            }

            $this->saveBarProps = new SaveBarProps(
                info: $props['info'] ?? null,
                saveLabel: $props['save_label'] ?? $props['saveLabel'] ?? 'Save Changes',
                cancelUrl: $props['cancel_url'] ?? $props['cancelUrl'] ?? null,
                cancelLabel: $props['cancel_label'] ?? $props['cancelLabel'] ?? 'Cancel',
                extraButtons: $extraButtons,
                formId: $props['form_id'] ?? $props['formId'] ?? null,
            );
            parent::__construct($props);
        }
    }

    /**
     * Create save bar with props
     */
    public static function create(SaveBarProps $props): static
    {
        return new static($props);
    }

    /**
     * Create a simple save bar with just a cancel URL
     */
    public static function simple(?string $cancelUrl = null): static
    {
        return new static(new SaveBarProps(cancelUrl: $cancelUrl));
    }

    /**
     * Set info text (fluent interface)
     */
    public function withInfo(string $info): static
    {
        $this->saveBarProps = $this->saveBarProps->withInfo($info);
        return $this;
    }

    /**
     * Add an extra button (fluent interface)
     */
    public function addButton(ActionButton $button): static
    {
        $this->saveBarProps = $this->saveBarProps->withButton($button);
        return $this;
    }

    /**
     * Set the target form ID (fluent interface)
     */
    public function forForm(string $formId): static
    {
        $this->saveBarProps = $this->saveBarProps->forForm($formId);
        return $this;
    }

    /**
     * Render the save bar
     */
    public function render(): string
    {
        $props = $this->saveBarProps;

        $html = '<div class="pugo-save-bar">';

        // Info section
        $html .= $this->renderInfoSection($props);

        // Actions section
        $html .= $this->renderActionsSection($props);

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the info section on the left
     */
    private function renderInfoSection(SaveBarProps $props): string
    {
        $infoIcon = '<circle cx="12" cy="12" r="10"/>'
            . '<line x1="12" y1="16" x2="12" y2="12"/>'
            . '<line x1="12" y1="8" x2="12.01" y2="8"/>';

        $html = '<div class="pugo-save-bar-info">';
        $html .= $this->icon($infoIcon, 16);

        if ($props->info) {
            $html .= '<span>' . $this->e($props->info) . '</span>';
        }

        if ($props->itemCountId) {
            $html .= '<span id="' . $this->e($props->itemCountId) . '"></span>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the actions section on the right
     */
    private function renderActionsSection(SaveBarProps $props): string
    {
        $html = '<div class="pugo-save-bar-actions">';

        // Cancel button
        if ($props->cancelUrl) {
            $html .= $this->renderLink(
                url: $props->cancelUrl,
                label: $props->cancelLabel,
                variant: ButtonVariant::Secondary
            );
        }

        // Extra buttons
        foreach ($props->extraButtons as $button) {
            $html .= $this->renderButton($button);
        }

        // Save button
        $html .= $this->renderSaveButton($props);

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the primary save button
     */
    private function renderSaveButton(SaveBarProps $props): string
    {
        $saveIcon = '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>'
            . '<polyline points="17 21 17 13 7 13 7 21"/>'
            . '<polyline points="7 3 7 8 15 8"/>';

        $attrs = 'type="submit" class="pugo-btn pugo-btn--primary"';
        if ($props->formId) {
            $attrs .= ' form="' . $this->e($props->formId) . '"';
        }

        return '<button ' . $attrs . '>'
            . $this->icon($saveIcon, 16)
            . $this->e($props->saveLabel)
            . '</button>';
    }

    /**
     * Render an action button
     */
    private function renderButton(ActionButton $button): string
    {
        $class = 'pugo-btn pugo-btn--' . $button->variant->value;

        // If it has an href, render as link
        if ($button->href) {
            return $this->renderLink($button->href, $button->label, $button->variant);
        }

        $attrs = 'type="' . $this->e($button->type) . '" class="' . $class . '"';

        if ($button->onclick) {
            $attrs .= ' onclick="' . $this->e($button->onclick) . '"';
        }

        if ($button->formId) {
            $attrs .= ' form="' . $this->e($button->formId) . '"';
        }

        if ($button->disabled) {
            $attrs .= ' disabled';
        }

        $content = '';
        if ($button->icon) {
            $content .= $this->icon($button->icon, 16);
        }
        $content .= $this->e($button->label);

        return '<button ' . $attrs . '>' . $content . '</button>';
    }

    /**
     * Render a link styled as button
     */
    private function renderLink(string $url, string $label, ButtonVariant $variant): string
    {
        $class = 'pugo-btn pugo-btn--' . $variant->value;
        return '<a href="' . $this->e($url) . '" class="' . $class . '">'
            . $this->e($label)
            . '</a>';
    }

    /**
     * Parse variant string to enum
     */
    private function parseVariant(string $variant): ButtonVariant
    {
        return match (strtolower($variant)) {
            'primary' => ButtonVariant::Primary,
            'danger' => ButtonVariant::Danger,
            'ghost' => ButtonVariant::Ghost,
            default => ButtonVariant::Secondary,
        };
    }

    protected function getDefaultProps(): array
    {
        return [];
    }
}
