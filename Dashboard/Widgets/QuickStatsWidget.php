<?php
/**
 * Pugo Core 3.0 - Quick Stats Widget
 */

namespace Pugo\Dashboard\Widgets;

use Pugo\Dashboard\Widget;

class QuickStatsWidget extends Widget
{
    public function getInfo(): array
    {
        return [
            'name' => 'Quick Stats',
            'description' => 'Overview of your site content',
            'icon' => 'bar-chart',
            'size' => 'full',
            'refreshable' => true,
        ];
    }
    
    public function render(): string
    {
        $stats = $this->getStats();
        
        $html = '<div class="pugo-stats-grid">';
        
        foreach ($stats as $stat) {
            $changeClass = '';
            $changeIcon = '';
            if (isset($stat['change'])) {
                $changeClass = $stat['change'] > 0 ? 'positive' : ($stat['change'] < 0 ? 'negative' : '');
                $changeIcon = $stat['change'] > 0 ? '↑' : ($stat['change'] < 0 ? '↓' : '');
            }
            
            $html .= <<<HTML
            <div class="pugo-stat-card" style="--stat-color: {$stat['color']}">
                <div class="pugo-stat-icon">
                    {$stat['icon_svg']}
                </div>
                <div class="pugo-stat-content">
                    <div class="pugo-stat-value">{$stat['value']}</div>
                    <div class="pugo-stat-label">{$stat['label']}</div>
                </div>
HTML;
            
            if (isset($stat['change'])) {
                $html .= "<div class='pugo-stat-change {$changeClass}'>{$changeIcon} {$stat['change']}%</div>";
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    public function getData(): array
    {
        return $this->getStats();
    }
    
    protected function getStats(): array
    {
        $contentDir = defined('CONTENT_DIR') ? CONTENT_DIR : getcwd() . '/content';
        $dataDir = defined('DATA_DIR') ? DATA_DIR : getcwd() . '/data';
        
        // Count pages
        $pages = 0;
        if (is_dir($contentDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($contentDir)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $pages++;
                }
            }
        }
        
        // Count data files
        $dataFiles = 0;
        if (is_dir($dataDir)) {
            $files = glob($dataDir . '/*.yaml') ?: [];
            $dataFiles = count($files);
        }
        
        // Count images
        $imagesDir = defined('IMAGES_DIR') ? IMAGES_DIR : getcwd() . '/static/images';
        $images = 0;
        if (is_dir($imagesDir)) {
            $imgFiles = glob($imagesDir . '/*.{jpg,jpeg,png,gif,webp,svg}', GLOB_BRACE) ?: [];
            $images = count($imgFiles);
        }
        
        // Get last build time
        $publicDir = defined('HUGO_ROOT') ? HUGO_ROOT . '/public' : getcwd() . '/public';
        $lastBuild = 'Never';
        if (is_dir($publicDir)) {
            $mtime = filemtime($publicDir);
            $lastBuild = date('M j, H:i', $mtime);
        }
        
        return [
            [
                'label' => 'Pages',
                'value' => $pages,
                'color' => '#6366f1',
                'icon_svg' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
            ],
            [
                'label' => 'Data Files',
                'value' => $dataFiles,
                'color' => '#10b981',
                'icon_svg' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',
            ],
            [
                'label' => 'Images',
                'value' => $images,
                'color' => '#f59e0b',
                'icon_svg' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
            ],
            [
                'label' => 'Last Build',
                'value' => $lastBuild,
                'color' => '#8b5cf6',
                'icon_svg' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            ],
        ];
    }
}

