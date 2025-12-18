<?php
/**
 * Pugo Core 3.0 - Quick Actions Widget
 */

namespace Pugo\Dashboard\Widgets;

use Pugo\Dashboard\Widget;

class QuickActionsWidget extends Widget
{
    public function getInfo(): array
    {
        return [
            'name' => 'Quick Actions',
            'description' => 'Common actions at your fingertips',
            'icon' => 'zap',
            'size' => 'medium',
        ];
    }
    
    public function render(): string
    {
        $actions = [
            [
                'label' => 'New Page',
                'url' => '?page=new',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
                'color' => '#6366f1',
            ],
            [
                'label' => 'Upload Media',
                'url' => '?page=media',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
                'color' => '#10b981',
            ],
            [
                'label' => 'Build Site',
                'url' => '?page=settings#build',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
                'color' => '#f59e0b',
            ],
            [
                'label' => 'Deploy',
                'url' => '?page=settings#deploy',
                'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>',
                'color' => '#ec4899',
            ],
        ];
        
        $html = '<div class="pugo-quick-actions">';
        
        foreach ($actions as $action) {
            $html .= <<<HTML
            <a href="{$action['url']}" class="pugo-quick-action" style="--action-color: {$action['color']}">
                <span class="pugo-quick-action-icon">{$action['icon']}</span>
                <span class="pugo-quick-action-label">{$action['label']}</span>
            </a>
HTML;
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

