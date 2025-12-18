<?php
/**
 * Pugo Core - Simple List Editor
 * 
 * Editor for simple list data (FAQs, quick access, testimonials, etc.)
 * Supports multi-language files or language-grouped single file.
 */

namespace Pugo\DataEditors;

use Pugo\Components\Card;
use Pugo\Components\EmptyState;
use Pugo\Components\FormFields\FieldFactory;

class SimpleListEditor extends BaseDataEditor
{
    protected string $dataFormat = 'multi_file';  // multi_file or grouped
    
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'data_format' => 'multi_file',  // multi_file = separate files per lang, grouped = single file with lang sections
            'item_name' => 'item',
            'item_name_plural' => 'items',
            'preview_type' => 'qa',  // qa, card, simple
        ]);
    }
    
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->dataFormat = $config['data_format'] ?? 'multi_file';
    }
    
    /**
     * Parse YAML content
     */
    protected function parseYaml(string $content): array
    {
        if ($this->dataFormat === 'grouped') {
            return $this->parseGroupedYaml($content);
        }
        return $this->parseSimpleListYaml($content);
    }
    
    /**
     * Parse simple list YAML (array of items)
     */
    protected function parseSimpleListYaml(string $content): array
    {
        $items = [];
        $fields = array_keys($this->config['fields']);
        $currentItem = null;
        
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^#/', trim($line)) || trim($line) === '') {
                continue;
            }
            
            // New item
            $firstField = $fields[0] ?? 'title';
            if (preg_match('/^-\s*' . preg_quote($firstField) . ':\s*["\']?(.+?)["\']?\s*$/', $line, $m)) {
                if ($currentItem !== null) {
                    $items[] = $currentItem;
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
                    // Boolean field
                    if (preg_match('/^\s+' . preg_quote($field) . ':\s*(true|false)\s*$/', $line, $m)) {
                        $currentItem[$field] = $m[1] === 'true';
                        break;
                    }
                }
            }
        }
        
        if ($currentItem !== null) {
            $items[] = $currentItem;
        }
        
        return $items;
    }
    
    /**
     * Parse grouped YAML (language sections like FAQs)
     */
    protected function parseGroupedYaml(string $content): array
    {
        $allData = [];
        $currentLang = null;
        $currentItem = null;
        $fields = array_keys($this->config['fields']);
        
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^#/', trim($line)) || (trim($line) === '' && $currentLang === null)) {
                continue;
            }
            
            // Language header
            if (preg_match('/^([A-Za-z]+):\s*$/', $line, $m)) {
                $currentLang = $m[1];
                if (!isset($allData[$currentLang])) {
                    $allData[$currentLang] = [];
                }
                $currentItem = null;
                continue;
            }
            
            if ($currentLang === null) continue;
            
            // New item
            $firstField = $fields[0] ?? 'question';
            if (preg_match('/^-\s*' . preg_quote($firstField) . ':\s*["\']?(.+?)["\']?\s*$/', $line, $m)) {
                if ($currentItem !== null) {
                    $allData[$currentLang][] = $currentItem;
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
        if ($currentLang !== null && $currentItem !== null) {
            $allData[$currentLang][] = $currentItem;
        }
        
        return $allData;
    }
    
    /**
     * Load data - handle grouped format differently
     */
    protected function loadData(): void
    {
        if ($this->dataFormat === 'grouped') {
            // Single file with all languages
            $file = $this->config['data_dir'] . '/' . $this->config['data_file'] . '.yaml';
            $allData = $this->loadYamlFile($file);
            
            // Map language names to codes
            foreach ($this->languages as $code => $lang) {
                $langName = $lang['name'] ?? ucfirst($code);
                // Try both code and name
                $this->data[$code] = $allData[$langName] ?? $allData[ucfirst($code)] ?? $allData[$code] ?? [];
            }
        } else {
            parent::loadData();
        }
    }
    
    /**
     * Process form data for a language
     */
    protected function processFormData(string $langCode): array
    {
        $items = [];
        $fields = array_keys($this->config['fields']);
        
        if (isset($_POST['items'][$langCode])) {
            foreach ($_POST['items'][$langCode] as $item) {
                $firstField = $fields[0] ?? 'title';
                if (!empty(trim($item[$firstField] ?? ''))) {
                    $processed = [];
                    foreach ($fields as $field) {
                        $value = trim($item[$field] ?? '');
                        $fieldConfig = $this->config['fields'][$field] ?? [];
                        
                        if (($fieldConfig['type'] ?? 'text') === 'checkbox') {
                            $processed[$field] = !empty($item[$field]);
                        } else {
                            $processed[$field] = $value;
                        }
                    }
                    $items[] = $processed;
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Generate YAML from data
     */
    protected function generateYaml(array $data): string
    {
        $yaml = "";
        $fields = array_keys($this->config['fields']);
        
        foreach ($data as $item) {
            foreach ($fields as $i => $field) {
                $value = $item[$field] ?? '';
                $fieldConfig = $this->config['fields'][$field] ?? [];
                $prefix = $i === 0 ? '- ' : '  ';
                
                if (($fieldConfig['type'] ?? 'text') === 'checkbox') {
                    $yaml .= "{$prefix}{$field}: " . ($value ? 'true' : 'false') . "\n";
                } else {
                    $escaped = str_replace('"', '\\"', $value);
                    $yaml .= "{$prefix}{$field}: \"{$escaped}\"\n";
                }
            }
            $yaml .= "\n";
        }
        
        return $yaml;
    }
    
    /**
     * Save all data - handle grouped format
     */
    protected function saveAllData(array $data): bool
    {
        if ($this->dataFormat === 'grouped') {
            // Single file with language sections
            $yaml = "";
            foreach ($this->languages as $code => $lang) {
                $langName = $lang['name'] ?? ucfirst($code);
                $yaml .= "{$langName}:\n";
                
                foreach ($data[$code] ?? [] as $item) {
                    $fields = array_keys($this->config['fields']);
                    foreach ($fields as $i => $field) {
                        $value = $item[$field] ?? '';
                        $escaped = str_replace('"', '\\"', $value);
                        $prefix = $i === 0 ? '- ' : '  ';
                        $yaml .= "{$prefix}{$field}: \"{$escaped}\"\n";
                    }
                    $yaml .= "\n";
                }
                $yaml .= "\n";
            }
            
            $file = $this->config['data_dir'] . '/' . $this->config['data_file'] . '.yaml';
            return file_put_contents($file, $yaml) !== false;
        }
        
        return parent::saveAllData($data);
    }
    
    /**
     * Render the form
     */
    protected function renderForm(): void
    {
        echo '<form method="POST" id="pugo-editor-form">';
        echo '<input type="hidden" name="action" value="save">';
        
        echo '<div class="pugo-editor-container">';
        
        // Editor panel
        echo '<div class="pugo-card">';
        echo '<div class="pugo-card-header">';
        echo '<div class="pugo-card-title">';
        echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        echo '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>';
        echo '</svg>';
        echo 'Edit ' . $this->e($this->config['item_name_plural']);
        echo '</div>';
        echo '</div>';
        
        echo '<div class="pugo-card-body">';
        $this->renderLanguageTabs();
        echo '<div class="pugo-items" id="pugo-items">';
        $this->renderItems();
        echo '</div>';
        $this->renderAddButton('Add New ' . ucfirst($this->config['item_name']));
        echo '</div>';
        echo '</div>';
        
        // Preview panel
        if ($this->config['preview']) {
            $this->renderPreview();
        }
        
        echo '</div>';
        
        // Hidden fields for other languages
        $this->renderHiddenLanguageData();
        
        echo '</form>';
        
        $this->renderSaveBar();
    }
    
    /**
     * Render items for current language
     */
    protected function renderItems(): void
    {
        $items = $this->data[$this->currentLang] ?? [];
        $fields = $this->config['fields'];
        
        if (empty($items)) {
            $empty = new EmptyState([
                'icon' => 'help-circle',
                'title' => 'No ' . $this->config['item_name_plural'] . ' for ' . ($this->languages[$this->currentLang]['name'] ?? $this->currentLang) . ' yet.',
                'description' => 'Click the button below to add your first ' . $this->config['item_name'] . '.',
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
            $this->renderField($lang, $index, $name, $config, $item[$name] ?? '');
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render a single field
     */
    protected function renderField(string $lang, int $index, string $name, array $config, mixed $value): void
    {
        $fieldName = "items[{$lang}][{$index}][{$name}]";
        $type = $config['type'] ?? 'text';
        $label = $config['label'] ?? ucfirst($name);
        $placeholder = $config['placeholder'] ?? '';
        $required = $config['required'] ?? false;
        
        echo '<div class="pugo-field">';
        echo '<label class="pugo-field-label">' . $this->e($label);
        if ($required) echo '<span class="pugo-field-required">*</span>';
        echo '</label>';
        
        switch ($type) {
            case 'textarea':
                echo '<textarea name="' . $this->e($fieldName) . '" class="pugo-textarea" placeholder="' . $this->e($placeholder) . '" oninput="pugoUpdatePreview()">' . $this->e($value) . '</textarea>';
                break;
            
            case 'select':
                echo '<select name="' . $this->e($fieldName) . '" class="pugo-select" onchange="pugoUpdatePreview()">';
                foreach ($config['options'] ?? [] as $optValue => $optLabel) {
                    $selected = $value == $optValue ? ' selected' : '';
                    echo '<option value="' . $this->e($optValue) . '"' . $selected . '>' . $this->e($optLabel) . '</option>';
                }
                echo '</select>';
                break;
            
            case 'checkbox':
                $checked = $value ? ' checked' : '';
                echo '<label class="pugo-checkbox-label">';
                echo '<input type="checkbox" name="' . $this->e($fieldName) . '" value="1" class="pugo-checkbox"' . $checked . '>';
                echo '<span class="pugo-checkbox-text">' . $this->e($config['inline_label'] ?? $label) . '</span>';
                echo '</label>';
                break;
            
            default:
                echo '<input type="' . $this->e($type) . '" name="' . $this->e($fieldName) . '" value="' . $this->e($value) . '" class="pugo-input" placeholder="' . $this->e($placeholder) . '" oninput="pugoUpdatePreview()">';
        }
        
        echo '</div>';
    }
    
    /**
     * Render hidden fields for non-active languages
     */
    protected function renderHiddenLanguageData(): void
    {
        $fields = array_keys($this->config['fields']);
        
        foreach ($this->languages as $langCode => $langInfo) {
            if ($langCode === $this->currentLang) continue;
            
            foreach ($this->data[$langCode] ?? [] as $index => $item) {
                foreach ($fields as $field) {
                    $value = $item[$field] ?? '';
                    $name = "items[{$langCode}][{$index}][{$field}]";
                    
                    if (($this->config['fields'][$field]['type'] ?? 'text') === 'checkbox') {
                        if ($value) {
                            echo '<input type="hidden" name="' . $this->e($name) . '" value="1">';
                        }
                    } else {
                        echo '<input type="hidden" name="' . $this->e($name) . '" value="' . $this->e($value) . '">';
                    }
                }
            }
        }
    }
    
    /**
     * Get JavaScript
     */
    protected function getScripts(): string
    {
        $lang = json_encode($this->currentLang);
        $fields = json_encode(array_keys($this->config['fields']));
        $fieldConfigs = json_encode($this->config['fields']);
        $previewType = json_encode($this->config['preview_type']);
        $itemName = json_encode($this->config['item_name']);
        $count = count($this->data[$this->currentLang] ?? []);
        
        return "
const pugoLang = {$lang};
const pugoFields = {$fields};
const pugoFieldConfigs = {$fieldConfigs};
const pugoPreviewType = {$previewType};
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
        const type = config.type || 'text';
        const placeholder = config.placeholder || '';
        const required = config.required ? '<span class=\"pugo-field-required\">*</span>' : '';
        
        fieldsHtml += '<div class=\"pugo-field\">';
        fieldsHtml += '<label class=\"pugo-field-label\">' + pugoEscape(label) + required + '</label>';
        
        if (type === 'textarea') {
            fieldsHtml += '<textarea name=\"items[' + pugoLang + '][' + index + '][' + field + ']\" class=\"pugo-textarea\" placeholder=\"' + pugoEscape(placeholder) + '\" oninput=\"pugoUpdatePreview()\"></textarea>';
        } else if (type === 'select') {
            fieldsHtml += '<select name=\"items[' + pugoLang + '][' + index + '][' + field + ']\" class=\"pugo-select\" onchange=\"pugoUpdatePreview()\">';
            for (const [v, l] of Object.entries(config.options || {})) {
                fieldsHtml += '<option value=\"' + pugoEscape(v) + '\">' + pugoEscape(l) + '</option>';
            }
            fieldsHtml += '</select>';
        } else if (type === 'checkbox') {
            fieldsHtml += '<label class=\"pugo-checkbox-label\"><input type=\"checkbox\" name=\"items[' + pugoLang + '][' + index + '][' + field + ']\" value=\"1\" class=\"pugo-checkbox\"><span class=\"pugo-checkbox-text\">' + pugoEscape(config.inline_label || label) + '</span></label>';
        } else {
            fieldsHtml += '<input type=\"' + type + '\" name=\"items[' + pugoLang + '][' + index + '][' + field + ']\" value=\"\" class=\"pugo-input\" placeholder=\"' + pugoEscape(placeholder) + '\" oninput=\"pugoUpdatePreview()\">';
        }
        
        fieldsHtml += '</div>';
    });
    
    item.innerHTML = '<div class=\"pugo-item-header\"><span class=\"pugo-item-number\">' + (index + 1) + '</span><button type=\"button\" class=\"pugo-item-delete\" onclick=\"pugoDeleteItem(this)\" title=\"Delete\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M3 6h18\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6\"/><path d=\"M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg></button></div><div class=\"pugo-item-fields\">' + fieldsHtml + '</div>';
    
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
    const items = document.querySelectorAll('.pugo-item');
    items.forEach((item, i) => {
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
            if (input) {
                values[field] = input.type === 'checkbox' ? input.checked : input.value;
            }
        });
        
        html += pugoRenderPreviewItem(values);
    });
    
    container.innerHTML = html;
}

function pugoRenderPreviewItem(values) {
    const firstField = pugoFields[0];
    const secondField = pugoFields[1];
    
    if (pugoPreviewType === 'qa') {
        return '<div class=\"pugo-preview-item\"><div class=\"pugo-preview-question\">' + pugoEscape(values[firstField] || '(No ' + firstField + ')') + '</div><div class=\"pugo-preview-answer\">' + pugoEscape(values[secondField] || '') + '</div></div>';
    }
    
    return '<div class=\"pugo-preview-item\"><div class=\"pugo-preview-title\">' + pugoEscape(values[firstField] || '(No ' + firstField + ')') + '</div></div>';
}

function pugoEscape(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Initialize
pugoUpdatePreview();

// Auto-dismiss toasts
document.querySelectorAll('.pugo-toast[data-auto-dismiss]').forEach(toast => {
    const duration = parseInt(toast.dataset.autoDismiss) || 3000;
    setTimeout(() => {
        toast.style.animation = 'pugo-slide-in 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, duration);
});

// Ctrl+S to save
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('pugo-editor-form').submit();
    }
});
";
    }
    
    /**
     * Override getStyles to add preview styles
     */
    protected function getStyles(): string
    {
        return parent::getStyles() . '
/* Preview Styles */
.pugo-preview-item {
    padding: 14px 0;
    border-bottom: 1px solid var(--border-color);
}

.pugo-preview-item:last-child {
    border-bottom: none;
}

.pugo-preview-question {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.pugo-preview-question::before {
    content: "Q";
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    background: var(--accent-primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

.pugo-preview-answer {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.6;
    padding-left: 34px;
}

.pugo-preview-title {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
}
';
    }
}

