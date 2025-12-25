<?php
/**
 * Pugo Core - Toast Notification Component
 * 
 * Displays temporary notification messages with support for
 * different types (success, error, warning, info) and auto-dismiss.
 */

namespace Pugo\Components;

/**
 * Toast notification type
 */
enum ToastType: string
{
    case Success = 'success';
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    /**
     * Get the SVG icon path for this type
     */
    public function iconPath(): string
    {
        return match ($this) {
            self::Success => '<polyline points="20 6 9 17 4 12"/>',
            self::Error => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
            self::Warning => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            self::Info => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        };
    }

    /**
     * Get accessible label for screen readers
     */
    public function ariaLabel(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Error => 'Error',
            self::Warning => 'Warning',
            self::Info => 'Information',
        };
    }
}

/**
 * Toast notification properties
 */
final class ToastProps
{
    public function __construct(
        public readonly string $message,
        public readonly ToastType $type = ToastType::Info,
        public readonly bool $dismissible = true,
        public readonly bool $autoDismiss = true,
        public readonly int $duration = 3000,
        public readonly ?string $id = null,
    ) {
        if (empty(trim($message))) {
            throw new \InvalidArgumentException('Toast message cannot be empty');
        }
        if ($duration < 0) {
            throw new \InvalidArgumentException('Duration must be non-negative');
        }
    }

    /**
     * Create a success toast props
     */
    public static function success(string $message, bool $autoDismiss = true): self
    {
        return new self(message: $message, type: ToastType::Success, autoDismiss: $autoDismiss);
    }

    /**
     * Create an error toast props
     */
    public static function error(string $message, bool $autoDismiss = false): self
    {
        // Errors don't auto-dismiss by default - user should acknowledge
        return new self(message: $message, type: ToastType::Error, autoDismiss: $autoDismiss);
    }

    /**
     * Create a warning toast props
     */
    public static function warning(string $message, bool $autoDismiss = true): self
    {
        return new self(message: $message, type: ToastType::Warning, autoDismiss: $autoDismiss);
    }

    /**
     * Create an info toast props
     */
    public static function info(string $message, bool $autoDismiss = true): self
    {
        return new self(message: $message, type: ToastType::Info, autoDismiss: $autoDismiss);
    }

    /**
     * Create a copy with different duration
     */
    public function withDuration(int $duration): self
    {
        return new self(
            message: $this->message,
            type: $this->type,
            dismissible: $this->dismissible,
            autoDismiss: $this->autoDismiss,
            duration: $duration,
            id: $this->id,
        );
    }

    /**
     * Create a copy that won't auto-dismiss
     */
    public function persistent(): self
    {
        return new self(
            message: $this->message,
            type: $this->type,
            dismissible: $this->dismissible,
            autoDismiss: false,
            duration: $this->duration,
            id: $this->id,
        );
    }
}

/**
 * Toast Notification Component
 * 
 * @example Basic usage:
 * ```php
 * echo Toast::success('Changes saved successfully!');
 * echo Toast::error('Failed to save changes.');
 * ```
 * 
 * @example With props object:
 * ```php
 * $toast = new Toast(new ToastProps(
 *     message: 'File uploaded',
 *     type: ToastType::Success,
 *     duration: 5000
 * ));
 * echo $toast;
 * ```
 * 
 * @example Persistent error:
 * ```php
 * echo Toast::create(ToastProps::error('Critical error!')->persistent());
 * ```
 */
class Toast extends Component
{
    private ToastProps $toastProps;

    public function __construct(ToastProps|array $props = [])
    {
        if ($props instanceof ToastProps) {
            $this->toastProps = $props;
            parent::__construct([]);
        } else {
            // Legacy array support
            $message = $props['message'] ?? '';
            if (empty($message)) {
                // Allow empty for backwards compatibility, will render nothing
                $this->toastProps = new ToastProps(message: ' ', type: ToastType::Info);
                $this->toastProps = new class(' ') extends ToastProps {
                    public function __construct(string $m) {
                        // Skip validation for empty legacy toasts
                    }
                    public readonly string $message;
                    public readonly ToastType $type;
                    public readonly bool $dismissible;
                    public readonly bool $autoDismiss;
                    public readonly int $duration;
                    public readonly ?string $id;
                };
                $this->toastProps = $this->createEmptyProps();
            } else {
                $this->toastProps = new ToastProps(
                    message: $message,
                    type: $this->parseType($props['type'] ?? 'info'),
                    dismissible: $props['dismissible'] ?? true,
                    autoDismiss: $props['auto_dismiss'] ?? $props['autoDismiss'] ?? true,
                    duration: $props['duration'] ?? 3000,
                    id: $props['id'] ?? null,
                );
            }
            parent::__construct($props);
        }
    }

    /**
     * Create empty props for legacy empty message handling
     */
    private function createEmptyProps(): ToastProps
    {
        return new class extends ToastProps {
            public readonly string $message;
            public readonly ToastType $type;
            public readonly bool $dismissible;
            public readonly bool $autoDismiss;
            public readonly int $duration;
            public readonly ?string $id;

            public function __construct()
            {
                $this->message = '';
                $this->type = ToastType::Info;
                $this->dismissible = true;
                $this->autoDismiss = true;
                $this->duration = 3000;
                $this->id = null;
            }
        };
    }

    /**
     * Create toast with props object (fluent API)
     */
    public static function create(ToastProps $props): static
    {
        return new static($props);
    }

    /**
     * Create a success toast
     */
    public static function success(string $message, int $duration = 3000): static
    {
        return new static(new ToastProps(
            message: $message,
            type: ToastType::Success,
            duration: $duration,
        ));
    }

    /**
     * Create an error toast
     */
    public static function error(string $message, bool $autoDismiss = false): static
    {
        return new static(new ToastProps(
            message: $message,
            type: ToastType::Error,
            autoDismiss: $autoDismiss,
        ));
    }

    /**
     * Create a warning toast
     */
    public static function warning(string $message, int $duration = 4000): static
    {
        return new static(new ToastProps(
            message: $message,
            type: ToastType::Warning,
            duration: $duration,
        ));
    }

    /**
     * Create an info toast
     */
    public static function info(string $message, int $duration = 3000): static
    {
        return new static(new ToastProps(
            message: $message,
            type: ToastType::Info,
            duration: $duration,
        ));
    }

    /**
     * Render the toast notification
     */
    public function render(): string
    {
        $props = $this->toastProps;

        if (empty($props->message)) {
            return '';
        }

        $attrs = $this->buildToastAttributes($props);

        $html = '<div' . $attrs . '>';

        // Type icon
        $html .= $this->icon($props->type->iconPath(), 20);

        // Message
        $html .= '<span class="pugo-toast-message">' . $this->e($props->message) . '</span>';

        // Dismiss button
        if ($props->dismissible) {
            $html .= $this->renderDismissButton();
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build attributes for the toast container
     */
    private function buildToastAttributes(ToastProps $props): string
    {
        $class = 'pugo-toast pugo-toast--' . $props->type->value;

        $attrs = [
            'class' => $class,
            'role' => 'alert',
            'aria-live' => $props->type === ToastType::Error ? 'assertive' : 'polite',
        ];

        if ($props->id) {
            $attrs['id'] = $props->id;
        }

        if ($props->autoDismiss) {
            $attrs['data-auto-dismiss'] = $props->duration;
        }

        return $this->buildAttributes($attrs);
    }

    /**
     * Render the dismiss button
     */
    private function renderDismissButton(): string
    {
        $closeIcon = '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';

        return '<button type="button" class="pugo-toast-close" onclick="this.parentElement.remove()" aria-label="Dismiss">'
            . $this->icon($closeIcon, 16)
            . '</button>';
    }

    /**
     * Parse type string to enum
     */
    private function parseType(string $type): ToastType
    {
        return match (strtolower($type)) {
            'success' => ToastType::Success,
            'error' => ToastType::Error,
            'warning' => ToastType::Warning,
            default => ToastType::Info,
        };
    }

    protected function getDefaultProps(): array
    {
        return [];
    }
}
