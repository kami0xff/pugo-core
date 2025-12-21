<?php
/**
 * Pugo Core - Base Data Editor
 * 
 * Base class for all YAML/JSON data editors.
 * Handles loading, saving, form handling, and rendering.
 */

namespace Pugo\DataEditors;

use Pugo\Components\Card;
use Pugo\Components\Tabs;
use Pugo\Components\Toast;
use Pugo\Components\SaveBar;
use Pugo\Components\EmptyState;
use Pugo\Components\FormFields\FieldFactory;
use Pugo\Config\PugoConfig;

abstract class BaseDataEditor
{
    protected array $config;
    protected array $data = [];
    protected array $languages = [];
    protected ?string $message = null;
    protected ?string $error = null;
    protected string $currentLang = 'en';

    public function __construct(array $config)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->languages = $this->config['languages'] ?? $this->getDefaultLanguages();
        $this->currentLang = $_GET['lang'] ?? array_key_first($this->languages);

        if (!isset($this->languages[$this->currentLang])) {
            $this->currentLang = array_key_first($this->languages);
        }
    }

    /**
     * Default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'title' => 'Data Editor',
            'subtitle' => '',
            'icon' => 'database',
            'data_file' => 'data',
            'data_dir' => DATA_DIR,
            'fields' => [],
            'supports_translations' => true,
            'preview' => true,
            'preview_type' => 'list',  // list, card, custom
            'languages' => null,  // Will use default if null
            'item_name' => 'item',
            'item_name_plural' => 'items',
        ];
    }

    /**
     * Get languages from PugoConfig (single source of truth)
     * Falls back to minimal English-only if config not available
     */
    protected function getDefaultLanguages(): array
    {
        // Try to get languages from PugoConfig (the single source of truth)
        try {
            $pugoConfig = PugoConfig::getInstance();
            $languages = $pugoConfig->languages();

            if (!empty($languages)) {
                // Ensure all language entries have required keys
                foreach ($languages as $code => &$lang) {
                    $lang['name'] = $lang['name'] ?? ucfirst($code);
                    $lang['flag'] = $lang['flag'] ?? '';
                    $lang['suffix'] = $lang['suffix'] ?? ($code === 'en' ? '' : "_{$code}");
                }
                unset($lang);
                return $languages;
            }
        } catch (\Exception $e) {
            // PugoConfig not available, use fallback
        }

        // Ultimate fallback - English only
        return [
            'en' => ['name' => 'English', 'flag' => '🇬🇧', 'suffix' => ''],
        ];
    }

    /**
     * Get data file path for a language
     */
    protected function getDataFilePath(string $langCode): string
    {
        $suffix = $this->languages[$langCode]['suffix'] ?? '';
        return $this->config['data_dir'] . '/' . $this->config['data_file'] . $suffix . '.yaml';
    }

    /**
     * Handle HTTP request (GET/POST)
     */
    public function handleRequest(): void
    {
        // Load data for all languages
        $this->loadData();

        // Handle POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
        }
    }

    /**
     * Load data from files
     */
    protected function loadData(): void
    {
        foreach ($this->languages as $langCode => $langInfo) {
            $file = $this->getDataFilePath($langCode);
            $this->data[$langCode] = $this->loadYamlFile($file);
        }
    }

    /**
     * Load and parse a YAML file
     */
    protected function loadYamlFile(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        return $this->parseYaml($content);
    }

    /**
     * Parse YAML content - override in subclasses for custom parsing
     */
    abstract protected function parseYaml(string $content): array;

    /**
     * Handle POST request
     */
    protected function handlePost(): void
    {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'save') {
            $this->processSave();
        }
    }

    /**
     * Process save action
     */
    protected function processSave(): void
    {
        $newData = [];

        foreach ($this->languages as $langCode => $langInfo) {
            $newData[$langCode] = $this->processFormData($langCode);
        }

        // Save to files
        if ($this->saveAllData($newData)) {
            $this->data = $newData;
            $this->message = $this->config['title'] . ' saved successfully!';
        } else {
            $this->error = 'Failed to save. Check file permissions.';
        }
    }

    /**
     * Process form data for a language - override in subclasses
     */
    abstract protected function processFormData(string $langCode): array;

    /**
     * Save all data to files
     */
    protected function saveAllData(array $data): bool
    {
        $success = true;

        foreach ($data as $langCode => $langData) {
            if (!empty($langData) || $langCode === array_key_first($this->languages)) {
                $file = $this->getDataFilePath($langCode);
                $yaml = $this->generateYaml($langData);

                if (file_put_contents($file, $yaml) === false) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Generate YAML from data - override in subclasses
     */
    abstract protected function generateYaml(array $data): string;

    /**
     * Render the complete editor
     */
    public function render(): void
    {
        $this->renderStyles();
        $this->renderToast();
        $this->renderHeader();
        $this->renderForm();
        $this->renderScripts();
    }

    /**
     * Render page header
     */
    protected function renderHeader(): void
    {
        $title = $this->config['title'];
        $subtitle = $this->config['subtitle'];

        echo '<div class="page-header">';
        echo '<div>';
        echo '<h1 class="page-title">' . htmlspecialchars($title) . '</h1>';
        if ($subtitle) {
            echo '<p class="page-subtitle">' . htmlspecialchars($subtitle) . '</p>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render toast notification
     */
    protected function renderToast(): void
    {
        if ($this->message) {
            echo Toast::success($this->message);
        }
        if ($this->error) {
            echo Toast::error($this->error);
        }
    }

    /**
     * Render the form and editor - override in subclasses
     */
    abstract protected function renderForm(): void;

    /**
     * Render language tabs
     */
    protected function renderLanguageTabs(): void
    {
        $counts = [];
        foreach ($this->languages as $code => $lang) {
            $counts[$code] = $this->getItemCount($code);
        }

        $baseUrl = '?' . http_build_query(array_filter([
            'section' => $_GET['section'] ?? null,
            'category' => $_GET['category'] ?? null,
        ]));

        echo Tabs::languages($this->languages, $this->currentLang, $baseUrl, $counts);
    }

    /**
     * Get item count for a language - override in subclasses
     */
    protected function getItemCount(string $langCode): int
    {
        return count($this->data[$langCode] ?? []);
    }

    /**
     * Render items for current language - override in subclasses
     */
    abstract protected function renderItems(): void;

    /**
     * Render add button
     */
    protected function renderAddButton(string $label = 'Add Item'): void
    {
        echo '<button type="button" class="pugo-add-btn" onclick="pugoAddItem()">';
        echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>';
        echo '</svg>';
        echo htmlspecialchars($label);
        echo '</button>';
    }

    /**
     * Render preview panel - override for custom preview
     */
    protected function renderPreview(): void
    {
        $card = new Card([
            'title' => 'Preview',
            'icon' => 'eye',
            'header_right' => '<span class="pugo-badge pugo-badge--success">Live</span>',
            'content' => '<div id="pugo-preview-content"></div>',
            'scrollable' => true,
            'max_height' => '500px',
        ]);

        echo '<div class="pugo-preview-panel">';
        echo $card;
        echo '</div>';
    }

    /**
     * Render save bar
     */
    protected function renderSaveBar(): void
    {
        $itemName = $this->config['item_name_plural'];
        $langName = $this->languages[$this->currentLang]['name'] ?? 'Unknown';
        $langFlag = $this->languages[$this->currentLang]['flag'] ?? '';

        $saveBar = new SaveBar([
            'info' => 'Editing: <strong>' . $langFlag . ' ' . htmlspecialchars($langName) . '</strong>',
            'save_label' => 'Save Changes',
            'cancel_url' => basename($_SERVER['PHP_SELF']),
            'form_id' => 'pugo-editor-form',
        ]);

        echo $saveBar;
    }

    /**
     * Render CSS styles
     */
    protected function renderStyles(): void
    {
        echo '<style>' . $this->getStyles() . '</style>';
    }

    /**
     * Get CSS styles - can be overridden
     */
    protected function getStyles(): string
    {
        return '
/* Pugo Data Editor Styles */
.pugo-editor-container {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1200px) {
    .pugo-editor-container {
        grid-template-columns: 1fr;
    }
}

/* Cards */
.pugo-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.pugo-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-tertiary);
}

.pugo-card-title {
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pugo-card-body {
    padding: 20px;
}

/* Tabs */
.pugo-tabs {
    display: flex;
    gap: 4px;
    background: var(--bg-tertiary);
    padding: 6px;
    border-radius: var(--radius-sm);
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.pugo-tab {
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}

.pugo-tab:hover {
    color: var(--text-primary);
    background: var(--bg-hover);
}

.pugo-tab.active {
    background: var(--accent-primary);
    color: white;
}

.pugo-tab-flag { font-size: 16px; }
.pugo-tab-count {
    background: rgba(255,255,255,0.15);
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 11px;
}

.pugo-tab-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

/* Section Tabs */
.pugo-tabs--buttons {
    background: transparent;
    padding: 0;
    gap: 8px;
}

.pugo-tabs--buttons .pugo-tab {
    flex: 1;
    justify-content: center;
    border: 2px solid var(--border-color);
    background: var(--bg-tertiary);
}

.pugo-tabs--buttons .pugo-tab:hover {
    border-color: var(--text-muted);
}

.pugo-tabs--buttons .pugo-tab.active {
    border-color: var(--tab-color, var(--accent-primary));
    background: color-mix(in srgb, var(--tab-color, var(--accent-primary)) 10%, transparent);
    color: var(--text-primary);
}

/* Pills Tabs */
.pugo-tabs--pills {
    background: transparent;
    padding: 0;
    gap: 8px;
}

.pugo-tabs--pills .pugo-tab {
    padding: 6px 12px;
    border-radius: 20px;
    border: 1px solid var(--border-color);
}

.pugo-tabs--pills .pugo-tab.active {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
}

/* Form Fields */
.pugo-field {
    margin-bottom: 12px;
}

.pugo-field:last-child {
    margin-bottom: 0;
}

.pugo-field-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.pugo-field-required {
    color: #e11d48;
    margin-left: 2px;
}

.pugo-input,
.pugo-textarea,
.pugo-select {
    width: 100%;
    padding: 10px 12px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    font-family: inherit;
    font-size: 14px;
    transition: border-color 0.15s;
}

.pugo-input:focus,
.pugo-textarea:focus,
.pugo-select:focus {
    outline: none;
    border-color: var(--accent-primary);
}

.pugo-textarea {
    min-height: 80px;
    resize: vertical;
}

.pugo-select {
    cursor: pointer;
}

.pugo-field--checkbox {
    margin-bottom: 12px;
}

.pugo-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
}

.pugo-checkbox {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Items */
.pugo-items {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.pugo-item {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 16px;
    position: relative;
    transition: border-color 0.15s;
}

.pugo-item:hover {
    border-color: var(--accent-primary);
}

.pugo-item-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.pugo-item-number {
    background: var(--accent-primary);
    color: white;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 10px;
}

.pugo-item-delete {
    margin-left: auto;
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.15s;
}

.pugo-item-delete:hover {
    background: rgba(225, 29, 72, 0.1);
    color: #e11d48;
}

.pugo-item-fields {
    display: grid;
    gap: 10px;
}

.pugo-item-fields--row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

/* Add Button */
.pugo-add-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px;
    background: transparent;
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    margin-top: 16px;
}

.pugo-add-btn:hover {
    border-color: var(--accent-primary);
    color: var(--accent-primary);
    background: rgba(225, 29, 72, 0.05);
}

/* Empty State */
.pugo-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.pugo-empty-state svg {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.pugo-empty-state-title {
    font-size: 14px;
    margin-bottom: 4px;
}

.pugo-empty-state-desc {
    font-size: 13px;
    opacity: 0.8;
}

/* Preview Panel */
.pugo-preview-panel {
    position: sticky;
    top: 24px;
}

.pugo-badge {
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pugo-badge--success {
    background: var(--accent-green);
    color: white;
}

/* Save Bar */
.pugo-save-bar {
    position: fixed;
    bottom: 0;
    left: 260px;
    right: 0;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border-color);
    padding: 16px 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 100;
}

.pugo-save-bar-info {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-secondary);
    font-size: 13px;
}

.pugo-save-bar-actions {
    display: flex;
    gap: 12px;
}

/* Toast */
.pugo-toast {
    position: fixed;
    top: 24px;
    right: 24px;
    padding: 14px 20px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 500;
    z-index: 1000;
    animation: pugo-slide-in 0.3s ease;
}

.pugo-toast--success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid #10b981;
    color: #10b981;
}

.pugo-toast--error {
    background: rgba(225, 29, 72, 0.15);
    border: 1px solid #e11d48;
    color: #e11d48;
}

.pugo-toast--warning {
    background: rgba(245, 158, 11, 0.15);
    border: 1px solid #f59e0b;
    color: #f59e0b;
}

.pugo-toast--info {
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid #3b82f6;
    color: #3b82f6;
}

.pugo-toast-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    margin-left: 8px;
    opacity: 0.7;
}

.pugo-toast-close:hover {
    opacity: 1;
}

@keyframes pugo-slide-in {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Buttons */
.pugo-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
    border: none;
}

.pugo-btn--primary {
    background: var(--accent-primary);
    color: white;
}

.pugo-btn--primary:hover {
    background: var(--accent-primary-hover);
}

.pugo-btn--secondary {
    background: var(--bg-tertiary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.pugo-btn--secondary:hover {
    background: var(--bg-hover);
}

/* File badges */
.pugo-file-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.pugo-file-badge {
    background: var(--bg-tertiary);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-family: "JetBrains Mono", monospace;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
}

.pugo-file-badge .check {
    color: var(--accent-green);
}
';
    }

    /**
     * Render JavaScript
     */
    protected function renderScripts(): void
    {
        echo '<script>' . $this->getScripts() . '</script>';
    }

    /**
     * Get JavaScript - can be overridden
     */
    abstract protected function getScripts(): string;

    /**
     * Escape HTML
     */
    protected function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

