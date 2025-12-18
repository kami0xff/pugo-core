<?php
/**
 * Pugo Core - Toast Notification Component
 */

namespace Pugo\Components;

class Toast extends Component
{
    protected function getDefaultProps(): array
    {
        return [
            'message' => '',
            'type' => 'info',  // info, success, error, warning
            'dismissible' => true,
            'auto_dismiss' => true,
            'duration' => 3000,  // ms
        ];
    }
    
    /**
     * Create a success toast
     */
    public static function success(string $message): static
    {
        return new static(['message' => $message, 'type' => 'success']);
    }
    
    /**
     * Create an error toast
     */
    public static function error(string $message): static
    {
        return new static(['message' => $message, 'type' => 'error']);
    }
    
    /**
     * Create a warning toast
     */
    public static function warning(string $message): static
    {
        return new static(['message' => $message, 'type' => 'warning']);
    }
    
    /**
     * Create an info toast
     */
    public static function info(string $message): static
    {
        return new static(['message' => $message, 'type' => 'info']);
    }
    
    public function render(): string
    {
        $message = $this->props['message'];
        $type = $this->props['type'];
        $dismissible = $this->props['dismissible'];
        $autoDismiss = $this->props['auto_dismiss'];
        $duration = $this->props['duration'];
        
        if (!$message) {
            return '';
        }
        
        $icons = [
            'success' => '<polyline points="20 6 9 17 4 12"/>',
            'error' => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
            'warning' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        ];
        
        $icon = $icons[$type] ?? $icons['info'];
        $dataAttrs = $autoDismiss ? ' data-auto-dismiss="' . $duration . '"' : '';
        
        $html = '<div class="pugo-toast pugo-toast--' . $type . '"' . $dataAttrs . '>';
        
        $html .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $icon;
        $html .= '</svg>';
        
        $html .= '<span class="pugo-toast-message">' . $this->e($message) . '</span>';
        
        if ($dismissible) {
            $html .= '<button type="button" class="pugo-toast-close" onclick="this.parentElement.remove()">';
            $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
            $html .= '</svg>';
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

