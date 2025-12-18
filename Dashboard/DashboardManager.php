<?php
/**
 * Pugo Core 3.0 - Dashboard Manager
 * 
 * Manages dashboard widgets and layout.
 */

namespace Pugo\Dashboard;

use Pugo\Config\PugoConfig;
use Pugo\Plugins\PluginManager;

class DashboardManager
{
    private static ?DashboardManager $instance = null;
    private array $widgets = [];
    private array $layout = [];
    
    private function __construct()
    {
        $this->registerDefaultWidgets();
        $this->loadLayout();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register default widgets
     */
    protected function registerDefaultWidgets(): void
    {
        // Quick Stats Widget
        $this->register('quick-stats', new Widgets\QuickStatsWidget());
        
        // Recent Activity Widget
        $this->register('recent-activity', new Widgets\RecentActivityWidget());
        
        // Quick Actions Widget
        $this->register('quick-actions', new Widgets\QuickActionsWidget());
        
        // Git Status Widget
        $this->register('git-status', new Widgets\GitStatusWidget());
        
        // Site Health Widget
        $this->register('site-health', new Widgets\SiteHealthWidget());
        
        // Recent Pages Widget
        $this->register('recent-pages', new Widgets\RecentPagesWidget());
        
        // Deployment Status Widget
        $this->register('deployment-status', new Widgets\DeploymentStatusWidget());
    }
    
    /**
     * Load layout from config or use default
     */
    protected function loadLayout(): void
    {
        $config = PugoConfig::getInstance();
        $savedLayout = $config->get('dashboard.layout');
        
        if ($savedLayout) {
            $this->layout = $savedLayout;
        } else {
            // Default layout
            $this->layout = [
                'row1' => ['quick-stats'],
                'row2' => ['quick-actions', 'git-status'],
                'row3' => ['recent-activity', 'recent-pages'],
                'row4' => ['site-health', 'deployment-status'],
            ];
        }
    }
    
    /**
     * Register a widget
     */
    public function register(string $id, Widget $widget): self
    {
        $this->widgets[$id] = $widget;
        return $this;
    }
    
    /**
     * Get all widgets
     */
    public function getWidgets(): array
    {
        return $this->widgets;
    }
    
    /**
     * Get a widget by ID
     */
    public function getWidget(string $id): ?Widget
    {
        return $this->widgets[$id] ?? null;
    }
    
    /**
     * Get current layout
     */
    public function getLayout(): array
    {
        return $this->layout;
    }
    
    /**
     * Set layout
     */
    public function setLayout(array $layout): self
    {
        $this->layout = $layout;
        
        // Save to config
        $config = PugoConfig::getInstance();
        $config->set('dashboard.layout', $layout);
        $config->save();
        
        return $this;
    }
    
    /**
     * Render the dashboard
     */
    public function render(): string
    {
        $html = '<div class="pugo-dashboard">';
        
        // Allow plugins to modify dashboard
        $layout = PluginManager::getInstance()->applyFilters('pugo_dashboard_layout', $this->layout);
        
        foreach ($layout as $rowId => $widgetIds) {
            $html .= '<div class="pugo-dashboard-row" data-row="' . htmlspecialchars($rowId) . '">';
            
            foreach ($widgetIds as $widgetId) {
                $widget = $this->getWidget($widgetId);
                if ($widget && $widget->isEnabled()) {
                    $html .= $this->renderWidget($widgetId, $widget);
                }
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render a single widget
     */
    protected function renderWidget(string $id, Widget $widget): string
    {
        $info = $widget->getInfo();
        $size = $info['size'] ?? 'medium';
        $refreshable = $info['refreshable'] ?? false;
        
        $html = '<div class="pugo-widget pugo-widget-' . $size . '" data-widget="' . htmlspecialchars($id) . '">';
        
        // Widget header
        $html .= '<div class="pugo-widget-header">';
        $html .= '<h3 class="pugo-widget-title">' . htmlspecialchars($info['name']) . '</h3>';
        
        $html .= '<div class="pugo-widget-actions">';
        if ($refreshable) {
            $html .= '<button class="pugo-widget-refresh" onclick="refreshWidget(\'' . $id . '\')" title="Refresh">';
            $html .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>';
            $html .= '</button>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Widget content
        $html .= '<div class="pugo-widget-content">';
        try {
            $html .= $widget->render();
        } catch (\Exception $e) {
            $html .= '<div class="pugo-widget-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get widget data via AJAX
     */
    public function getWidgetData(string $id): ?array
    {
        $widget = $this->getWidget($id);
        
        if (!$widget) {
            return null;
        }
        
        return [
            'id' => $id,
            'html' => $widget->render(),
            'data' => $widget->getData(),
        ];
    }
    
    /**
     * Get available widgets for widget picker
     */
    public function getAvailableWidgets(): array
    {
        $available = [];
        
        foreach ($this->widgets as $id => $widget) {
            $info = $widget->getInfo();
            $available[$id] = [
                'id' => $id,
                'name' => $info['name'],
                'description' => $info['description'] ?? '',
                'size' => $info['size'] ?? 'medium',
                'icon' => $info['icon'] ?? 'box',
            ];
        }
        
        return $available;
    }
}

