<?php
/**
 * Pugo Core - Save Bar Component
 * 
 * Fixed bottom bar with save/cancel actions.
 */

namespace Pugo\Components;

class SaveBar extends Component
{
    protected function getDefaultProps(): array
    {
        return [
            'info' => '',
            'save_label' => 'Save Changes',
            'save_icon' => 'save',
            'cancel_url' => null,
            'cancel_label' => 'Cancel',
            'extra_buttons' => [],  // Additional buttons before save
            'form_id' => null,
        ];
    }
    
    public function render(): string
    {
        $info = $this->props['info'];
        $saveLabel = $this->props['save_label'];
        $cancelUrl = $this->props['cancel_url'];
        $cancelLabel = $this->props['cancel_label'];
        $extraButtons = $this->props['extra_buttons'];
        $formId = $this->props['form_id'];
        
        $html = '<div class="pugo-save-bar">';
        
        // Info section
        $html .= '<div class="pugo-save-bar-info">';
        $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>';
        $html .= '</svg>';
        if ($info) {
            $html .= '<span>' . $info . '</span>';
        }
        $html .= '<span id="pugo-item-count"></span>';
        $html .= '</div>';
        
        // Actions
        $html .= '<div class="pugo-save-bar-actions">';
        
        if ($cancelUrl) {
            $html .= '<a href="' . $this->e($cancelUrl) . '" class="pugo-btn pugo-btn--secondary">' . $this->e($cancelLabel) . '</a>';
        }
        
        foreach ($extraButtons as $btn) {
            $btnClass = 'pugo-btn pugo-btn--' . ($btn['variant'] ?? 'secondary');
            $html .= '<button type="' . ($btn['type'] ?? 'button') . '" class="' . $btnClass . '"';
            if (isset($btn['onclick'])) {
                $html .= ' onclick="' . $this->e($btn['onclick']) . '"';
            }
            $html .= '>' . $this->e($btn['label']) . '</button>';
        }
        
        $saveAttrs = 'type="submit" class="pugo-btn pugo-btn--primary"';
        if ($formId) {
            $saveAttrs .= ' form="' . $this->e($formId) . '"';
        }
        
        $html .= '<button ' . $saveAttrs . '>';
        $html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>';
        $html .= '<polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>';
        $html .= '</svg>';
        $html .= $this->e($saveLabel);
        $html .= '</button>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

