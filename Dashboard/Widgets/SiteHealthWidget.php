<?php
/**
 * Pugo Core 3.0 - Site Health Widget
 */

namespace Pugo\Dashboard\Widgets;

use Pugo\Dashboard\Widget;

class SiteHealthWidget extends Widget
{
    public function getInfo(): array
    {
        return [
            'name' => 'Site Health',
            'description' => 'Check site health status',
            'icon' => 'activity',
            'size' => 'medium',
            'refreshable' => true,
        ];
    }
    
    public function render(): string
    {
        $checks = $this->runHealthChecks();
        $passed = count(array_filter($checks, fn($c) => $c['status'] === 'ok'));
        $total = count($checks);
        
        $overallStatus = $passed === $total ? 'healthy' : ($passed > $total / 2 ? 'warning' : 'error');
        $overallIcon = $overallStatus === 'healthy' 
            ? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
            : '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        
        $html = <<<HTML
        <div class="pugo-health-overview {$overallStatus}">
            <div class="pugo-health-icon">{$overallIcon}</div>
            <div class="pugo-health-summary">
                <strong>{$passed}/{$total}</strong> checks passed
            </div>
        </div>
        <div class="pugo-health-checks">
HTML;
        
        foreach ($checks as $check) {
            $statusIcon = $check['status'] === 'ok' 
                ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'
                : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            
            $html .= <<<HTML
            <div class="pugo-health-check {$check['status']}">
                <span class="pugo-health-check-icon">{$statusIcon}</span>
                <span class="pugo-health-check-name">{$this->esc($check['name'])}</span>
            </div>
HTML;
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    protected function runHealthChecks(): array
    {
        $checks = [];
        $hugoRoot = defined('HUGO_ROOT') ? HUGO_ROOT : getcwd();
        
        // Hugo installed
        exec('which hugo 2>&1', $hugoOutput, $hugoCode);
        $checks[] = [
            'name' => 'Hugo installed',
            'status' => $hugoCode === 0 ? 'ok' : 'error',
        ];
        
        // Content directory exists
        $checks[] = [
            'name' => 'Content directory',
            'status' => is_dir($hugoRoot . '/content') ? 'ok' : 'error',
        ];
        
        // Config file exists
        $hasConfig = file_exists($hugoRoot . '/hugo.toml') || 
                     file_exists($hugoRoot . '/hugo.yaml') || 
                     file_exists($hugoRoot . '/config.toml');
        $checks[] = [
            'name' => 'Hugo config',
            'status' => $hasConfig ? 'ok' : 'error',
        ];
        
        // Public directory writable
        $publicDir = $hugoRoot . '/public';
        $checks[] = [
            'name' => 'Build output writable',
            'status' => (!is_dir($publicDir) || is_writable($publicDir)) ? 'ok' : 'error',
        ];
        
        // Git configured
        exec('cd ' . escapeshellarg($hugoRoot) . ' && git remote -v 2>&1', $gitOutput, $gitCode);
        $checks[] = [
            'name' => 'Git repository',
            'status' => $gitCode === 0 && !empty($gitOutput) ? 'ok' : 'warning',
        ];
        
        // Pagefind installed
        exec('which pagefind 2>&1', $pfOutput, $pfCode);
        $checks[] = [
            'name' => 'Pagefind installed',
            'status' => $pfCode === 0 ? 'ok' : 'warning',
        ];
        
        return $checks;
    }
}

