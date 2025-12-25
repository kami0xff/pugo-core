<?php
/**
 * Pugo Core - Grouped List Editor
 * 
 * Editor for data grouped by sections (Topics: users/models/studios, etc.)
 * Supports multi-language with separate files per language.
 */

namespace Pugo\DataEditors;

use Pugo\Components\EmptyState;
use Pugo\Components\Tabs;

class GroupedListEditor extends BaseDataEditor
{
    protected array $sections = [];
    protected string $currentSection = '';
    
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'sections' => [],  // ['users' => ['name' => 'Users', 'color' => '#3b82f6'], ...]
            'item_name' => 'topic',
            'item_name_plural' => 'topics',
        ]);
    }
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->sections = $config['sections'] ?? [];
        $this->currentSection = $_GET['section'] ?? array_key_first($this->sections);
        
        if (!isset($this->sections[$this->currentSection])) {
            $this->currentSection = array_key_first($this->sections);
        }
    }
    
    /**
     * Parse YAML with section groups
     */
    protected function parseYaml(string $content): array
    {
        $data = [];
        $currentSection = null;
        $currentItem = null;
        $fields = array_keys($this->config['fields']);
        
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\s*#/', $line) || trim($line) === '') {
                continue;
            }
            
            // Section header
            if (preg_match('/^([a-z_]+):\s*$/', $line, $m)) {
                if ($currentSection !== null && $currentItem !== null) {
                    $data[$currentSection][] = $currentItem;
                }
                $currentSection = $m[1];
                if (!isset($data[$currentSection])) {
                    $data[$currentSection] = [];
                }
                $currentItem = null;
                continue;
            }
            
            if ($currentSection === null) continue;
            
            // New item
            $firstField = $fields[0] ?? 'title';
            if (preg_match('/^\s*-\s*' . preg_quote($firstField) . ':\s*["\']?(.+?)["\']?\s*$/', $line, $m)) {
                if ($currentItem !== null) {
                    $data[$currentSection][] = $currentItem;
                }
                $currentItem = [$firstField => $m[1]];
                continue;
            }
            
            // Other fields
            if ($currentItem !== null) {
                foreach ($fields as $field) {
                    if (preg_match('/^\s+' . preg_quote($field) . ':\s*["\']?(.+?)["\']?\s*$/', $line, $m)) {
                        $currentItem[$field] = $m[1];
                        break;
                    }
                }
            }
        }
        
        // Add last item
        if ($currentSection !== null && $currentItem !== null) {
            $data[$currentSection][] = $currentItem;
        }
        
        // Ensure all sections exist
        foreach (array_keys($this->sections) as $section) {
            if (!isset($data[$section])) {
                $data[$section] = [];
            }
        }
        
        return $data;
    }
    
    /**
     * Generate YAML from data
     */
    protected function generateYaml(array $data): string
    {
        $yaml = "";
        $fields = array_keys($this->config['fields']);
        
        foreach (array_keys($this->sections) as $section) {
            if (!isset($data[$section]) || empty($data[$section])) continue;
            
            $yaml .= "{$section}:\n";
            
            foreach ($data[$section] as $item) {
                foreach ($fields as $i => $field) {
                    $value = str_replace('"', '\\"', $item[$field] ?? '');
                    $prefix = $i === 0 ? '  - ' : '    ';
                    $yaml .= "{$prefix}{$field}: \"{$value}\"\n";
                }
            }
            $yaml .= "\n";
        }
        
        return $yaml;
    }
    
    /**
     * Process form data
     */
    protected function processFormData(string $langCode): array
    {
        $data = [];
        $fields = array_keys($this->config['fields']);
        
        foreach (array_keys($this->sections) as $section) {
            $data[$section] = [];
            
            if (isset($_POST['items'][$langCode][$section])) {
                foreach ($_POST['items'][$langCode][$section] as $item) {
                    $firstField = $fields[0] ?? 'title';
                    if (!empty(trim($item[$firstField] ?? ''))) {
                        $processed = [];
                        foreach ($fields as $field) {
                            $processed[$field] = trim($item[$field] ?? '');
                        }
                        $data[$section][] = $processed;
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get item count for a language (across all sections)
     */
    protected function getItemCount(string $langCode): int
    {
        $total = 0;
        foreach ($this->data[$langCode] ?? [] as $section => $items) {
            $total += count($items);
        }
        return $total;
    }
    
    /**
     * Render the form
     */
    protected function renderForm(): void
    {
        $sectionColor = $this->sections[$this->currentSection]['color'] ?? '#6b7280';
        
        echo '<form method="POST" id="pugo-editor-form">';
        echo '<input type="hidden" name="action" value="save">';
        
        echo '<div class="pugo-editor-container" style="--section-color: ' . $this->e($sectionColor) . '">';
        
        // Editor panel
        echo '<div class="pugo-card">';
        echo '<div class="pugo-card-header">';
        echo '<div class="pugo-card-title">';
        echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>';
        echo '</svg>';
        echo 'Edit ' . $this->e($this->config['item_name_plural']);
        echo '</div>';
        echo '</div>';
        
        echo '<div class="pugo-card-body">';
        
        // Language tabs
        $this->renderLanguageTabs();
        
        // Section tabs
        $this->renderSectionTabs();
        
        echo '<div class="pugo-items" id="pugo-items">';
        $this->renderItems();
        echo '</div>';
        
        $this->renderAddButton('Add New ' . ucfirst($this->config['item_name']));
        
        // File badges
        $this->renderFileBadges();
        
        echo '</div>';
        echo '</div>';
        
        // Preview panel
        if ($this->config['preview']) {
            $this->renderPreview();
        }
        
        echo '</div>';
        
        // Hidden fields for other languages/sections
        $this->renderHiddenData();
        
        echo '</form>';
        
        $this->renderSaveBar();
    }
    
    /**
     * Render section tabs
     */
    protected function renderSectionTabs(): void
    {
        $counts = [];
        foreach ($this->sections as $key => $section) {
            $counts[$key] = count($this->data[$this->currentLang][$key] ?? []);
        }
        
        $baseUrl = '?' . http_build_query(array_filter([
            'lang' => $this->currentLang,
        ]));
        
        echo Tabs::sections($this->sections, $this->currentSection, $baseUrl, $counts);
    }
    
    /**
     * Render items for current language and section
     */
    protected function renderItems(): void
    {
        $items = $this->data[$this->currentLang][$this->currentSection] ?? [];
        
        if (empty($items)) {
            $sectionName = $this->sections[$this->currentSection]['name'] ?? $this->currentSection;
            $langName = $this->languages[$this->currentLang]['name'] ?? $this->currentLang;
            
            $empty = new EmptyState([
                'icon' => 'book',
                'title' => "No {$this->config['item_name_plural']} for {$sectionName} in {$langName} yet.",
            ]);
            echo $empty;
            return;
        }
        
        foreach ($items as $index => $item) {
            $this->renderItem($index, $item);
        }
    }
    
    /**
     * Render a single item
     */
    protected function renderItem(int $index, array $item): void
    {
        $lang = $this->currentLang;
        $section = $this->currentSection;
        $fields = $this->config['fields'];
        
        echo '<div class="pugo-item" data-index="' . $index . '">';
        echo '<div class="pugo-item-header">';
        echo '<span class="pugo-item-number">' . ($index + 1) . '</span>';
        echo '<button type="button" class="pugo-item-delete" onclick="pugoDeleteItem(this)" title="Delete">';
        echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>';
        echo '</svg>';
        echo '</button>';
        echo '</div>';
        
        echo '<div class="pugo-item-fields">';
        foreach ($fields as $name => $config) {
            $fieldName = "items[{$lang}][{$section}][{$index}][{$name}]";
            $type = $config['type'] ?? 'text';
            $label = $config['label'] ?? ucfirst($name);
            $placeholder = $config['placeholder'] ?? '';
            $value = $item[$name] ?? '';
            
            echo '<div class="pugo-field">';
            echo '<label class="pugo-field-label">' . $this->e($label) . '</label>';
            
            if ($type === 'textarea') {
                echo '<textarea name="' . $this->e($fieldName) . '" class="pugo-textarea" placeholder="' . $this->e($placeholder) . '" oninput="pugoUpdatePreview()">' . $this->e($value) . '</textarea>';
            } else {
                echo '<input type="text" name="' . $this->e($fieldName) . '" value="' . $this->e($value) . '" class="pugo-input" placeholder="' . $this->e($placeholder) . '" oninput="pugoUpdatePreview()">';
            }
            
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render file badges
     */
    protected function renderFileBadges(): void
    {
        echo '<div class="pugo-file-badges">';
        echo '<span style="font-size: 11px; color: var(--text-muted); margin-right: 8px;">Generated files:</span>';
        
        foreach ($this->languages as $code => $lang) {
            $suffix = $lang['suffix'] ?? '';
            $filename = $this->config['data_file'] . $suffix . '.yaml';
            echo '<span class="pugo-file-badge"><span class="check">✓</span> ' . $this->e($filename) . '</span>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render hidden fields for non-active languages/sections
     */
    protected function renderHiddenData(): void
    {
        $fields = array_keys($this->config['fields']);
        
        foreach ($this->languages as $langCode => $langInfo) {
            foreach (array_keys($this->sections) as $section) {
                if ($langCode === $this->currentLang && $section === $this->currentSection) continue;
                
                foreach ($this->data[$langCode][$section] ?? [] as $index => $item) {
                    foreach ($fields as $field) {
                        $name = "items[{$langCode}][{$section}][{$index}][{$field}]";
                        $value = $item[$field] ?? '';
                        echo '<input type="hidden" name="' . $this->e($name) . '" value="' . $this->e($value) . '">';
                    }
                }
            }
        }
    }
    
    /**
     * Override preview to show section-specific content
     */
    protected function renderPreview(): void
    {
        $sectionName = $this->sections[$this->currentSection]['name'] ?? $this->currentSection;
        $langFlag = $this->languages[$this->currentLang]['flag'] ?? '';
        
        echo '<div class="pugo-card pugo-preview-panel" style="--section-color: ' . $this->e($this->sections[$this->currentSection]['color'] ?? '#6b7280') . '">';
        echo '<div class="pugo-card-header">';
        echo '<div class="pugo-card-title">';
        echo '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        echo '</svg>';
        echo 'Preview';
        echo '<span class="pugo-badge pugo-badge--success">Live</span>';
        echo '</div>';
        echo '<span style="font-size: 12px; color: var(--text-muted);">' . $this->e($sectionName) . ' • ' . $langFlag . '</span>';
        echo '</div>';
        echo '<div class="pugo-card-body" style="max-height: 500px; overflow-y: auto;">';
        echo '<div id="pugo-preview-content"></div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Get JavaScript
     */
    protected function getScripts(): string
    {
        $lang = json_encode($this->currentLang);
        $section = json_encode($this->currentSection);
        $fields = json_encode(array_keys($this->config['fields']));
        $fieldConfigs = json_encode($this->config['fields']);
        $sectionColor = json_encode($this->sections[$this->currentSection]['color'] ?? '#6b7280');
        $itemName = json_encode($this->config['item_name']);
        $count = count($this->data[$this->currentLang][$this->currentSection] ?? []);
        
        return "
const pugoLang = {$lang};
const pugoSection = {$section};
const pugoFields = {$fields};
const pugoFieldConfigs = {$fieldConfigs};
const pugoSectionColor = {$sectionColor};
const pugoItemName = {$itemName};
let pugoItemCounter = {$count};

function pugoAddItem() {
    const container = document.getElementById('pugo-items');
    const emptyState = document.getElementById('pugo-empty-state');
    if (emptyState) emptyState.remove();
    
    const index = pugoItemCounter++;
    const item = document.createElement('div');
    item.className = 'pugo-item';
    item.dataset.index = index;
    
    let fieldsHtml = '';
    pugoFields.forEach(field => {
        const config = pugoFieldConfigs[field] || {};
        const label = config.label || field;
        const placeholder = config.placeholder || '';
        const type = config.type || 'text';
        
        fieldsHtml += '<div class=\"pugo-field\">';
        fieldsHtml += '<label class=\"pugo-field-label\">' + pugoEscape(label) + '</label>';
        
        if (type === 'textarea') {
            fieldsHtml += '<textarea name=\"items[' + pugoLang + '][' + pugoSection + '][' + index + '][' + field + ']\" class=\"pugo-textarea\" placeholder=\"' + pugoEscape(placeholder) + '\" oninput=\"pugoUpdatePreview()\"></textarea>';
        } else {
            fieldsHtml += '<input type=\"text\" name=\"items[' + pugoLang + '][' + pugoSection + '][' + index + '][' + field + ']\" value=\"\" class=\"pugo-input\" placeholder=\"' + pugoEscape(placeholder) + '\" oninput=\"pugoUpdatePreview()\">';
        }
        
        fieldsHtml += '</div>';
    });
    
    item.innerHTML = '<div class=\"pugo-item-header\"><span class=\"pugo-item-number\">' + (index + 1) + '</span><button type=\"button\" class=\"pugo-item-delete\" onclick=\"pugoDeleteItem(this)\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M3 6h18\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6\"/><path d=\"M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg></button></div><div class=\"pugo-item-fields\">' + fieldsHtml + '</div>';
    
    container.appendChild(item);
    item.querySelector('input, textarea')?.focus();
    pugoUpdateNumbers();
    pugoUpdatePreview();
}

function pugoDeleteItem(button) {
    if (confirm('Delete this ' + pugoItemName + '?')) {
        button.closest('.pugo-item').remove();
        pugoUpdateNumbers();
        pugoUpdatePreview();
    }
}

function pugoUpdateNumbers() {
    document.querySelectorAll('.pugo-item').forEach((item, i) => {
        item.querySelector('.pugo-item-number').textContent = i + 1;
    });
}

function pugoUpdatePreview() {
    const container = document.getElementById('pugo-preview-content');
    if (!container) return;
    
    const items = document.querySelectorAll('.pugo-item');
    
    if (items.length === 0) {
        container.innerHTML = '<div class=\"pugo-empty-state\"><svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><path d=\"M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\"/><circle cx=\"12\" cy=\"12\" r=\"3\"/></svg><p>Add items to see the preview</p></div>';
        return;
    }
    
    let html = '';
    items.forEach(item => {
        const values = {};
        pugoFields.forEach(field => {
            const input = item.querySelector('[name*=\"[' + field + ']\"]');
            if (input) values[field] = input.value;
        });
        
        const title = values[pugoFields[0]] || '(No title)';
        const desc = values[pugoFields[1]] || '';
        const url = values[pugoFields[2]] || '';
        
        html += '<div class=\"pugo-preview-topic\"><div class=\"pugo-preview-topic-title\" style=\"--dot-color: ' + pugoSectionColor + '\">' + pugoEscape(title) + '</div>';
        if (desc) html += '<div class=\"pugo-preview-topic-desc\">' + pugoEscape(desc) + '</div>';
        if (url) html += '<div class=\"pugo-preview-topic-url\">' + pugoEscape(url) + '</div>';
        html += '</div>';
    });
    
    container.innerHTML = html;
}

function pugoEscape(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

pugoUpdatePreview();

document.querySelectorAll('.pugo-toast[data-auto-dismiss]').forEach(toast => {
    setTimeout(() => {
        toast.style.animation = 'pugo-slide-in 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, parseInt(toast.dataset.autoDismiss) || 3000);
});

document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('pugo-editor-form').submit();
    }
});
";
    }
    
    /**
     * Override styles to add topic preview styles
     */
    protected function getStyles(): string
    {
        return parent::getStyles() . '
/* Topic Preview Styles */
.pugo-preview-topic {
    padding: 14px 0;
    border-bottom: 1px solid var(--border-color);
}

.pugo-preview-topic:last-child {
    border-bottom: none;
}

.pugo-preview-topic-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pugo-preview-topic-title::before {
    content: "";
    width: 6px;
    height: 6px;
    background: var(--dot-color, var(--section-color, var(--accent-primary)));
    border-radius: 50%;
}

.pugo-preview-topic-desc {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 4px;
    padding-left: 14px;
}

.pugo-preview-topic-url {
    font-size: 11px;
    color: var(--text-muted);
    font-family: "JetBrains Mono", monospace;
    padding-left: 14px;
}

/* Section-colored item numbers */
.pugo-item-number {
    background: var(--section-color, var(--accent-primary)) !important;
}

.pugo-item:hover {
    border-color: var(--section-color, var(--accent-primary));
}

.pugo-input:focus,
.pugo-textarea:focus {
    border-color: var(--section-color, var(--accent-primary));
}

.pugo-add-btn:hover {
    border-color: var(--section-color, var(--accent-primary));
    color: var(--section-color, var(--accent-primary));
}
';
    }
}

