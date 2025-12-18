<?php
/**
 * Pugo Core 3.0 - Recent Pages Widget
 */

namespace Pugo\Dashboard\Widgets;

use Pugo\Dashboard\Widget;

class RecentPagesWidget extends Widget
{
    public function getInfo(): array
    {
        return [
            'name' => 'Recent Pages',
            'description' => 'Recently edited pages',
            'icon' => 'file-text',
            'size' => 'medium',
            'refreshable' => true,
        ];
    }
    
    public function render(): string
    {
        $pages = $this->getRecentPages();
        
        if (empty($pages)) {
            return '<div class="pugo-widget-empty">No pages yet</div>';
        }
        
        $html = '<div class="pugo-pages-list">';
        
        foreach ($pages as $page) {
            $draftBadge = $page['draft'] ? '<span class="pugo-draft-badge">Draft</span>' : '';
            $editUrl = '?page=edit&file=' . urlencode($page['path']);
            
            $html .= <<<HTML
            <a href="{$editUrl}" class="pugo-page-item">
                <div class="pugo-page-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
                <div class="pugo-page-info">
                    <div class="pugo-page-title">{$this->esc($page['title'])} {$draftBadge}</div>
                    <div class="pugo-page-path">{$this->esc($page['section'])}</div>
                </div>
                <div class="pugo-page-time">{$page['time_ago']}</div>
            </a>
HTML;
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    protected function getRecentPages(int $limit = 5): array
    {
        $hugoRoot = defined('HUGO_ROOT') ? HUGO_ROOT : getcwd();
        $contentDir = $hugoRoot . '/content';
        
        if (!is_dir($contentDir)) {
            return [];
        }
        
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contentDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = [
                    'path' => $file->getPathname(),
                    'mtime' => $file->getMTime(),
                ];
            }
        }
        
        usort($files, fn($a, $b) => $b['mtime'] - $a['mtime']);
        $files = array_slice($files, 0, $limit);
        
        $pages = [];
        foreach ($files as $file) {
            $content = file_get_contents($file['path']);
            $title = 'Untitled';
            $draft = false;
            
            // Parse front matter
            if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
                if (preg_match('/^title:\s*["\']?(.+?)["\']?\s*$/m', $matches[1], $titleMatch)) {
                    $title = $titleMatch[1];
                }
                if (preg_match('/^draft:\s*true/m', $matches[1])) {
                    $draft = true;
                }
            }
            
            $relativePath = str_replace($contentDir . '/', '', $file['path']);
            $section = dirname($relativePath);
            if ($section === '.') $section = 'root';
            
            $pages[] = [
                'path' => $relativePath,
                'title' => $title,
                'section' => $section,
                'draft' => $draft,
                'mtime' => $file['mtime'],
                'time_ago' => $this->timeAgo($file['mtime']),
            ];
        }
        
        return $pages;
    }
    
    protected function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) return 'now';
        if ($diff < 3600) return floor($diff / 60) . 'm';
        if ($diff < 86400) return floor($diff / 3600) . 'h';
        if ($diff < 604800) return floor($diff / 86400) . 'd';
        
        return date('M j', $timestamp);
    }
}

