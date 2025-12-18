<?php
/**
 * Pugo Core - Card Component
 * 
 * A card container with optional header and footer.
 */

namespace Pugo\Components;

class Card extends Component
{
    protected function getDefaultProps(): array
    {
        return [
            'title' => '',
            'icon' => null,
            'header_right' => '',
            'footer' => '',
            'body_class' => '',
            'scrollable' => false,
            'max_height' => null,
        ];
    }
    
    public function render(): string
    {
        $title = $this->props['title'];
        $icon = $this->props['icon'];
        $headerRight = $this->props['header_right'];
        $footer = $this->props['footer'];
        $bodyClass = $this->props['body_class'];
        $scrollable = $this->props['scrollable'];
        $maxHeight = $this->props['max_height'];
        $content = $this->props['content'] ?? '';
        
        $hasHeader = $title || $icon || $headerRight;
        
        $bodyStyle = '';
        if ($scrollable || $maxHeight) {
            $height = $maxHeight ?? '500px';
            $bodyStyle = "max-height: {$height}; overflow-y: auto;";
        }
        
        $html = '<div class="pugo-card">';
        
        // Header
        if ($hasHeader) {
            $html .= '<div class="pugo-card-header">';
            $html .= '<div class="pugo-card-title">';
            
            if ($icon) {
                $html .= $this->renderIcon($icon);
            }
            
            if ($title) {
                $html .= $this->e($title);
            }
            
            $html .= '</div>';
            
            if ($headerRight) {
                $html .= '<div class="pugo-card-header-right">' . $headerRight . '</div>';
            }
            
            $html .= '</div>';
        }
        
        // Body
        $html .= '<div class="pugo-card-body ' . $this->e($bodyClass) . '" style="' . $bodyStyle . '">';
        $html .= $content;
        $html .= '</div>';
        
        // Footer
        if ($footer) {
            $html .= '<div class="pugo-card-footer">' . $footer . '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    protected function renderIcon(string $icon): string
    {
        // Common icons mapping
        $icons = [
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
            'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
            'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'help-circle' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'video' => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>',
            'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
            'plus' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
            'trash' => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        ];
        
        $path = $icons[$icon] ?? $icons['file-text'];
        
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }
}

