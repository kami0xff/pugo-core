<?php
/**
 * Pugo Core 3.0 - Deployment Status Widget
 */

namespace Pugo\Dashboard\Widgets;

use Pugo\Dashboard\Widget;
use Pugo\Deployment\DeploymentManager;

class DeploymentStatusWidget extends Widget
{
    public function getInfo(): array
    {
        return [
            'name' => 'Deployment',
            'description' => 'Current deployment status',
            'icon' => 'cloud',
            'size' => 'medium',
            'refreshable' => true,
        ];
    }
    
    public function render(): string
    {
        $manager = new DeploymentManager();
        $adapter = $manager->getActiveAdapter();
        
        if (!$adapter) {
            return '<div class="pugo-widget-empty">No deployment configured</div>';
        }
        
        $status = $adapter->getStatus();
        $adapterName = $adapter->getName();
        $configured = $adapter->isConfigured();
        
        $statusClass = $configured ? 'configured' : 'not-configured';
        $statusIcon = $configured 
            ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
            : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        
        $html = <<<HTML
        <div class="pugo-deployment-status {$statusClass}">
            <div class="pugo-deployment-header">
                <span class="pugo-deployment-icon">{$statusIcon}</span>
                <span class="pugo-deployment-method">{$this->esc($adapterName)}</span>
            </div>
HTML;
        
        if ($configured && $status) {
            // Show deployment-specific status
            if (isset($status['branch'])) {
                $html .= '<div class="pugo-deployment-info">';
                $html .= '<span>Branch:</span> <strong>' . $this->esc($status['branch']) . '</strong>';
                $html .= '</div>';
            }
            
            if (isset($status['last_commit'])) {
                $commit = $status['last_commit'];
                $hash = substr($commit['hash'] ?? '', 0, 7);
                $message = strlen($commit['message'] ?? '') > 40 
                    ? substr($commit['message'], 0, 40) . '...' 
                    : ($commit['message'] ?? '');
                
                $html .= '<div class="pugo-deployment-commit">';
                $html .= '<code>' . $this->esc($hash) . '</code> ';
                $html .= '<span>' . $this->esc($message) . '</span>';
                $html .= '</div>';
            }
            
            if (isset($status['pending_changes']) && count($status['pending_changes']) > 0) {
                $count = count($status['pending_changes']);
                $html .= '<div class="pugo-deployment-pending">';
                $html .= '<span class="pending-badge">' . $count . ' pending change' . ($count > 1 ? 's' : '') . '</span>';
                $html .= '</div>';
            }
        } else {
            $html .= '<div class="pugo-deployment-setup">';
            $html .= '<p>Configure deployment in <a href="?page=settings#deployment">Settings</a></p>';
            $html .= '</div>';
        }
        
        $html .= '<div class="pugo-deployment-actions">';
        $html .= '<a href="?page=settings#build" class="pugo-btn pugo-btn-sm">Build</a>';
        if ($configured) {
            $html .= '<a href="?page=settings#deploy" class="pugo-btn pugo-btn-sm pugo-btn-primary">Deploy</a>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
}

