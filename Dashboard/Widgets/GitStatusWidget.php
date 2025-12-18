<?php
/**
 * Pugo Core 3.0 - Git Status Widget
 */

namespace Pugo\Dashboard\Widgets;

use Pugo\Dashboard\Widget;

class GitStatusWidget extends Widget
{
    public function getInfo(): array
    {
        return [
            'name' => 'Git Status',
            'description' => 'Current repository status',
            'icon' => 'git-branch',
            'size' => 'medium',
            'refreshable' => true,
        ];
    }
    
    public function render(): string
    {
        $hugoRoot = defined('HUGO_ROOT') ? HUGO_ROOT : getcwd();
        
        // Check if git is available
        exec('cd ' . escapeshellarg($hugoRoot) . ' && git status --porcelain 2>&1', $statusOutput, $code);
        
        if ($code !== 0) {
            return '<div class="pugo-widget-empty">Git not initialized</div>';
        }
        
        // Get branch
        exec('cd ' . escapeshellarg($hugoRoot) . ' && git branch --show-current 2>&1', $branchOutput);
        $branch = trim($branchOutput[0] ?? 'unknown');
        
        // Get last commit
        exec('cd ' . escapeshellarg($hugoRoot) . ' && git log -1 --format="%h %s (%ar)" 2>&1', $commitOutput);
        $lastCommit = $commitOutput[0] ?? 'No commits';
        
        // Count changes
        $modified = 0;
        $added = 0;
        $deleted = 0;
        
        foreach ($statusOutput as $line) {
            if (preg_match('/^(.)(.)/', $line, $m)) {
                $status = trim($m[1] . $m[2]);
                if (str_contains($status, 'M')) $modified++;
                elseif (str_contains($status, 'A') || str_contains($status, '?')) $added++;
                elseif (str_contains($status, 'D')) $deleted++;
            }
        }
        
        $totalChanges = $modified + $added + $deleted;
        $statusClass = $totalChanges > 0 ? 'has-changes' : 'clean';
        $statusText = $totalChanges > 0 ? "{$totalChanges} changes" : 'Clean';
        
        return <<<HTML
        <div class="pugo-git-status {$statusClass}">
            <div class="pugo-git-branch">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="6" y1="3" x2="6" y2="15"/>
                    <circle cx="18" cy="6" r="3"/>
                    <circle cx="6" cy="18" r="3"/>
                    <path d="M18 9a9 9 0 0 1-9 9"/>
                </svg>
                <span>{$this->esc($branch)}</span>
            </div>
            
            <div class="pugo-git-changes">
                <span class="pugo-git-status-badge {$statusClass}">{$statusText}</span>
                
                <div class="pugo-git-change-counts">
                    <span class="modified" title="Modified">~{$modified}</span>
                    <span class="added" title="Added">+{$added}</span>
                    <span class="deleted" title="Deleted">-{$deleted}</span>
                </div>
            </div>
            
            <div class="pugo-git-commit">
                <small>Last: {$this->esc($lastCommit)}</small>
            </div>
        </div>
HTML;
    }
    
    public function getData(): array
    {
        $hugoRoot = defined('HUGO_ROOT') ? HUGO_ROOT : getcwd();
        
        exec('cd ' . escapeshellarg($hugoRoot) . ' && git status --porcelain 2>&1', $output);
        
        return [
            'changes' => count($output),
        ];
    }
}

