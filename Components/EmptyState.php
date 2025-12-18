<?php
/**
 * Pugo Core - Empty State Component
 */

namespace Pugo\Components;

class EmptyState extends Component
{
    protected function getDefaultProps(): array
    {
        return [
            'icon' => 'file-text',
            'title' => 'No items yet',
            'description' => '',
            'action_label' => null,
            'action_onclick' => null,
        ];
    }
    
    public function render(): string
    {
        $icon = $this->props['icon'];
        $title = $this->props['title'];
        $description = $this->props['description'];
        $actionLabel = $this->props['action_label'];
        $actionOnclick = $this->props['action_onclick'];
        
        $icons = [
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
            'video' => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>',
            'help-circle' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
            'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
            'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        ];
        
        $iconPath = $icons[$icon] ?? $icons['file-text'];
        
        $html = '<div class="pugo-empty-state" id="pugo-empty-state">';
        
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
        $html .= $iconPath;
        $html .= '</svg>';
        
        $html .= '<p class="pugo-empty-state-title">' . $this->e($title) . '</p>';
        
        if ($description) {
            $html .= '<p class="pugo-empty-state-desc">' . $this->e($description) . '</p>';
        }
        
        if ($actionLabel) {
            $html .= '<button type="button" class="pugo-btn pugo-btn--primary" onclick="' . $this->e($actionOnclick) . '">';
            $html .= $this->e($actionLabel);
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

