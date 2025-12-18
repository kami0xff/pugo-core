<?php
/**
 * Pugo Core 3.0 - Recent Activity Widget
 */

namespace Pugo\Dashboard\Widgets;

use Pugo\Dashboard\Widget;

class RecentActivityWidget extends Widget
{
    public function getInfo(): array
    {
        return [
            'name' => 'Recent Activity',
            'description' => 'Latest changes and actions',
            'icon' => 'activity',
            'size' => 'medium',
            'refreshable' => true,
        ];
    }
    
    public function render(): string
    {
        $activities = $this->getRecentActivity();
        
        if (empty($activities)) {
            return '<div class="pugo-widget-empty">No recent activity</div>';
        }
        
        $html = '<div class="pugo-activity-list">';
        
        foreach ($activities as $activity) {
            $timeAgo = $this->timeAgo($activity['time']);
            $typeClass = $this->esc($activity['type']);
            
            $html .= <<<HTML
            <div class="pugo-activity-item {$typeClass}">
                <div class="pugo-activity-icon">{$activity['icon']}</div>
                <div class="pugo-activity-content">
                    <div class="pugo-activity-title">{$this->esc($activity['title'])}</div>
                    <div class="pugo-activity-meta">
                        <span class="pugo-activity-file">{$this->esc($activity['file'])}</span>
                        <span class="pugo-activity-time">{$timeAgo}</span>
                    </div>
                </div>
            </div>
HTML;
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    protected function getRecentActivity(): array
    {
        $hugoRoot = defined('HUGO_ROOT') ? HUGO_ROOT : getcwd();
        $activities = [];
        
        // Get recently modified files
        $contentDir = $hugoRoot . '/content';
        $dataDir = $hugoRoot . '/data';
        
        $files = [];
        
        // Content files
        if (is_dir($contentDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($contentDir)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $files[] = [
                        'path' => $file->getPathname(),
                        'name' => $file->getFilename(),
                        'mtime' => $file->getMTime(),
                        'type' => 'content',
                    ];
                }
            }
        }
        
        // Data files
        if (is_dir($dataDir)) {
            foreach (glob($dataDir . '/*.yaml') as $file) {
                $files[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'mtime' => filemtime($file),
                    'type' => 'data',
                ];
            }
        }
        
        // Sort by mtime desc
        usort($files, fn($a, $b) => $b['mtime'] - $a['mtime']);
        
        // Take top 5
        $files = array_slice($files, 0, 5);
        
        foreach ($files as $file) {
            $icon = $file['type'] === 'content' 
                ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'
                : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>';
            
            $activities[] = [
                'type' => $file['type'],
                'title' => 'Modified',
                'file' => $file['name'],
                'time' => $file['mtime'],
                'icon' => $icon,
            ];
        }
        
        return $activities;
    }
    
    protected function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        
        return date('M j', $timestamp);
    }
}

