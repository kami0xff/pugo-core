<?php
/**
 * Pugo - Site Components Editor
 * A generic system for managing data-driven page sections
 * 
 * This provides a unified interface to create and edit any type of
 * component/section that's powered by YAML data files.
 * 
 * To customize components for your site, create admin/custom/components_registry.php
 * that returns an array of component definitions.
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Site Components';

/**
 * Component Types Registry
 * 
 * Load custom component registry if exists, otherwise use default example
 */
$custom_registry_path = dirname(__DIR__, 2) . '/custom/components_registry.php';

if (file_exists($custom_registry_path)) {
    $component_registry = require $custom_registry_path;
} else {
    // Default example components for starter projects
    $component_registry = [
        'faqs' => [
            'name' => 'FAQ',
            'description' => 'Frequently asked questions',
            'icon' => 'help-circle',
            'color' => '#8b5cf6',
            'file' => 'faqs.yaml',
            'template' => 'partials/faq.html',
            'supports_translations' => true,
            'fields' => [
                'question' => ['type' => 'text', 'label' => 'Question', 'required' => true],
                'answer' => ['type' => 'textarea', 'label' => 'Answer', 'required' => true],
            ],
            'preview_template' => 'faq'
        ],
        
        'quickaccess' => [
            'name' => 'Quick Access',
            'description' => 'Homepage quick access buttons',
            'icon' => 'zap',
            'color' => '#f59e0b',
            'file' => 'quickaccess.yaml',
            'template' => 'partials/quick-access.html',
            'supports_translations' => false,
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                'icon' => ['type' => 'select', 'label' => 'Icon', 'required' => true, 'options' => [
                    'star' => 'Star', 'heart' => 'Heart', 'settings' => 'Settings', 'help-circle' => 'Help',
                    'user' => 'User', 'home' => 'Home', 'search' => 'Search', 'mail' => 'Mail'
                ]],
                'url' => ['type' => 'text', 'label' => 'URL', 'required' => true, 'placeholder' => '/section/page/'],
            ],
            'preview_template' => 'quickaccess'
        ],
        
        'features' => [
            'name' => 'Features',
            'description' => 'Feature cards or highlights',
            'icon' => 'star',
            'color' => '#10b981',
            'file' => 'features.yaml',
            'template' => 'partials/features.html',
            'supports_translations' => true,
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
                'description' => ['type' => 'textarea', 'label' => 'Description', 'required' => true],
                'icon' => ['type' => 'text', 'label' => 'Icon', 'placeholder' => 'star'],
            ],
            'preview_template' => 'features'
        ],
    ];
}

// Languages - use config if available
$languages = $config['languages'] ?? [
    'en' => ['name' => 'English', 'flag' => '🇬🇧', 'suffix' => ''],
];

// Add suffix for non-English languages if not present
foreach ($languages as $lang_code => &$lang_config) {
    if (!isset($lang_config['suffix'])) {
        $lang_config['suffix'] = ($lang_code === 'en') ? '' : '_' . $lang_code;
    }
}
unset($lang_config);

/**
 * Load component data from YAML file
 */
function load_component_data($component, $lang = 'en', $languages = []) {
    $suffix = $languages[$lang]['suffix'] ?? '';
    $base_file = $component['file'];
    
    // Handle language suffix for files
    if ($component['supports_translations'] && $suffix) {
        $parts = pathinfo($base_file);
        $file = DATA_DIR . '/' . $parts['filename'] . $suffix . '.' . $parts['extension'];
    } else {
        $file = DATA_DIR . '/' . $base_file;
    }
    
    if (!file_exists($file)) {
        return [];
    }
    
    $content = file_get_contents($file);
    $data = parse_component_yaml($content, $component);
    
    // If this component uses a file_key (like topics.yaml with users/models/studios)
    if (isset($component['file_key']) && isset($data[$component['file_key']])) {
        return $data[$component['file_key']];
    }
    
    return $data;
}

/**
 * Parse component YAML based on its structure
 */
function parse_component_yaml($content, $component) {
    $items = [];
    $current_item = null;
    $current_section = null;
    $lines = explode("\n", $content);
    
    // Check if this is a grouped file (like topics.yaml with sections)
    $has_sections = array_key_exists('file_key', $component) && $component['file_key'] !== false;
    
    foreach ($lines as $line) {
        if (trim($line) === '' || preg_match('/^\s*#/', $line)) continue;
        
        // Section header (for grouped files)
        if ($has_sections && preg_match('/^([A-Za-z_]+):\s*$/', $line, $matches)) {
            if ($current_item !== null && $current_section) {
                $items[$current_section][] = $current_item;
                $current_item = null;
            }
            $current_section = $matches[1];
            if (!isset($items[$current_section])) {
                $items[$current_section] = [];
            }
            continue;
        }
        
        // New item
        $first_field = array_key_first($component['fields']);
        if (preg_match('/^\s*-\s*' . $first_field . ':\s*["\']?(.+?)["\']?\s*$/', $line, $matches)) {
            if ($current_item !== null) {
                if ($has_sections && $current_section) {
                    $items[$current_section][] = $current_item;
                } else {
                    $items[] = $current_item;
                }
            }
            $current_item = [$first_field => $matches[1]];
            continue;
        }
        
        // Other fields
        foreach ($component['fields'] as $field_name => $field_config) {
            if ($field_name === $first_field) continue;
            if (preg_match('/^\s+' . $field_name . ':\s*["\']?(.+?)["\']?\s*$/', $line, $matches)) {
                if ($current_item !== null) {
                    $current_item[$field_name] = $matches[1];
                }
                break;
            }
        }
    }
    
    // Don't forget the last item
    if ($current_item !== null) {
        if ($has_sections && $current_section) {
            $items[$current_section][] = $current_item;
        } else {
            $items[] = $current_item;
        }
    }
    
    return $items;
}

/**
 * Save component data to YAML file
 */
function save_component_data($component, $items, $lang = 'en', $languages = [], $all_sections_data = null) {
    $suffix = $languages[$lang]['suffix'] ?? '';
    $base_file = $component['file'];
    
    if ($component['supports_translations'] && $suffix) {
        $parts = pathinfo($base_file);
        $file = DATA_DIR . '/' . $parts['filename'] . $suffix . '.' . $parts['extension'];
    } else {
        $file = DATA_DIR . '/' . $base_file;
    }
    
    $yaml = "";
    
    // Handle sectioned files
    if (isset($component['file_key'])) {
        // Load existing data to preserve other sections
        if ($all_sections_data === null && file_exists($file)) {
            $existing = file_get_contents($file);
            $all_sections_data = parse_component_yaml($existing, [
                'fields' => $component['fields'], 
                'file_key' => '__all__'
            ]);
        }
        
        if (!is_array($all_sections_data)) {
            $all_sections_data = [];
        }
        $all_sections_data[$component['file_key']] = $items;
        
        foreach ($all_sections_data as $section_name => $section_items) {
            if (empty($section_items)) continue;
            $yaml .= "$section_name:\n";
            foreach ($section_items as $item) {
                $yaml .= generate_item_yaml($item, $component['fields'], '  ');
            }
            $yaml .= "\n";
        }
    } else {
        // Simple list
        foreach ($items as $item) {
            $yaml .= generate_item_yaml($item, $component['fields'], '');
        }
    }
    
    return file_put_contents($file, $yaml) !== false;
}

/**
 * Generate YAML for a single item
 */
function generate_item_yaml($item, $fields, $indent = '') {
    $yaml = "";
    $first = true;
    
    foreach ($fields as $field_name => $field_config) {
        $value = $item[$field_name] ?? '';
        
        // Handle checkbox/boolean fields
        if (($field_config['type'] ?? '') === 'checkbox') {
            if ($value && $value !== '0' && $value !== 'false') {
                if ($first) {
                    $yaml .= "{$indent}- {$field_name}: true\n";
                    $first = false;
                } else {
                    $yaml .= "{$indent}  {$field_name}: true\n";
                }
            }
            continue;
        }
        
        $value = str_replace('"', '\\"', $value);
        
        if ($first) {
            $yaml .= "{$indent}- {$field_name}: \"$value\"\n";
            $first = false;
        } else {
            $yaml .= "{$indent}  {$field_name}: \"$value\"\n";
        }
    }
    
    return $yaml;
}

// Current component
$current_component = $_GET['component'] ?? null;
$current_lang = $_GET['lang'] ?? 'en';

if (!isset($languages[$current_lang])) {
    $current_lang = 'en';
}

// Validate component exists - redirect if invalid
if ($current_component && !isset($component_registry[$current_component])) {
    header('Location: components.php');
    exit;
}

// Handle form submission
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_component && isset($component_registry[$current_component])) {
    $comp = $component_registry[$current_component];
    $items = [];
    
    if (isset($_POST['items'])) {
        foreach ($_POST['items'] as $item_data) {
            $item = [];
            $has_content = false;
            
            foreach ($comp['fields'] as $field_name => $field_config) {
                $value = trim($item_data[$field_name] ?? '');
                $item[$field_name] = $value;
                if (!empty($value) && ($field_config['required'] ?? false)) {
                    $has_content = true;
                }
            }
            
            // Only include items with at least the first required field
            $first_field = array_key_first($comp['fields']);
            if (!empty($item[$first_field])) {
                $items[] = $item;
            }
        }
    }
    
    if (save_component_data($comp, $items, $current_lang, $languages)) {
        $message = "Saved successfully! Updated " . $comp['file'];
        
        // Call post-save handler if defined
        if (isset($comp['post_save']) && function_exists($comp['post_save'])) {
            $handler = $comp['post_save'];
            if ($handler($items, $current_lang, $languages)) {
                $message .= " + generated derivative files";
            }
        }
    } else {
        $error = "Failed to save. Check file permissions.";
    }
}

// Load data if viewing a component
$component_data = [];
if ($current_component && isset($component_registry[$current_component])) {
    $component_data = load_component_data($component_registry[$current_component], $current_lang, $languages);
}

require __DIR__ . '/../includes/header.php';
?>

<style>
/* Components Page Styles */
.components-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.component-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 20px;
    text-decoration: none;
    transition: all 0.15s;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.component-card:hover {
    border-color: var(--component-color, var(--accent-primary));
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

.component-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.component-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.component-icon svg {
    width: 20px;
    height: 20px;
    color: white;
}

.component-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

.component-desc {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
}

.component-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: auto;
}

.component-badge {
    font-size: 10px;
    padding: 3px 6px;
    border-radius: 4px;
    background: var(--bg-tertiary);
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.component-badge.translatable {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
    font-family: inherit;
}

/* Editor Styles */
.editor-container {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1200px) {
    .editor-container { grid-template-columns: 1fr; }
}

.editor-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.editor-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.editor-card-title {
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.editor-card-body {
    padding: 20px;
}

/* Language tabs */
.comp-lang-tabs {
    display: flex;
    gap: 4px;
    background: var(--bg-tertiary);
    padding: 6px;
    border-radius: var(--radius-sm);
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.comp-lang-tab {
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}

.comp-lang-tab:hover { color: var(--text-primary); background: var(--bg-hover); }
.comp-lang-tab.active { background: var(--accent-primary); color: white; }
.comp-lang-tab .flag { font-size: 16px; }

/* Items */
.component-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.component-item {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 16px;
    position: relative;
    transition: border-color 0.15s;
}

.component-item:hover {
    border-color: var(--component-color, var(--accent-primary));
}

.item-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.item-number {
    background: var(--component-color, var(--accent-primary));
    color: white;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 10px;
}

.item-delete {
    margin-left: auto;
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.15s;
}

.item-delete:hover {
    background: rgba(225, 29, 72, 0.1);
    color: #e11d48;
}

.item-fields {
    display: grid;
    gap: 10px;
}

.item-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.item-field label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
}

.item-field input,
.item-field select,
.item-field textarea {
    padding: 10px 12px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    color: var(--text-primary);
    font-family: inherit;
    font-size: 14px;
    transition: border-color 0.15s;
}

.item-field input:focus,
.item-field select:focus,
.item-field textarea:focus {
    outline: none;
    border-color: var(--component-color, var(--accent-primary));
}

.item-field textarea {
    min-height: 60px;
    resize: vertical;
}

/* Checkbox field styling */
.item-field.checkbox-field {
    flex-direction: row;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
}

.item-field.checkbox-field input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--component-color, var(--accent-primary));
}

.item-field.checkbox-field label {
    cursor: pointer;
    font-size: 13px;
    text-transform: none;
    letter-spacing: 0;
    color: var(--text-secondary);
}

.add-item-btn {
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
    margin-top: 12px;
}

.add-item-btn:hover {
    border-color: var(--component-color, var(--accent-primary));
    color: var(--component-color, var(--accent-primary));
}

/* Preview styles */
.preview-panel { position: sticky; top: 24px; }

.preview-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.preview-item:last-child { border-bottom: none; }

/* Quick access preview */
.preview-quickaccess {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.preview-qa-btn {
    background: var(--bg-tertiary);
    border-radius: 8px;
    padding: 12px 8px;
    text-align: center;
    border: 1px solid var(--border-color);
}

.preview-qa-icon {
    width: 24px;
    height: 24px;
    margin: 0 auto 6px;
    background: var(--accent-primary);
    border-radius: 6px;
}

.preview-qa-label {
    font-size: 10px;
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Features preview */
.preview-feature-card {
    background: var(--bg-tertiary);
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 10px;
}

.preview-feature-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.preview-feature-desc {
    font-size: 12px;
    color: var(--text-secondary);
}

/* FAQ preview */
.preview-faq-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.preview-faq-q {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 6px;
}

.preview-faq-q::before {
    content: 'Q';
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    background: var(--accent-primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
}

.preview-faq-a {
    font-size: 13px;
    color: var(--text-secondary);
    padding-left: 28px;
}

/* Save bar */
.save-bar {
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

.save-bar-info {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-secondary);
    font-size: 13px;
}

.save-bar-actions { display: flex; gap: 12px; }

/* Toast */
.toast-message {
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
    animation: slideIn 0.3s ease;
}

.toast-message.success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid #10b981;
    color: #10b981;
}

.toast-message.error {
    background: rgba(225, 29, 72, 0.15);
    border: 1px solid #e11d48;
    color: #e11d48;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state svg {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 13px;
    margin-bottom: 16px;
    transition: color 0.15s;
}

.back-link:hover { color: var(--text-primary); }
</style>

<?php if ($message): ?>
<div class="toast-message success">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="20 6 9 17 4 12"/>
    </svg>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="toast-message error">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="15" y1="9" x2="9" y2="15"/>
        <line x1="9" y1="9" x2="15" y2="15"/>
    </svg>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if (!$current_component): ?>
<!-- Component Selection View -->
<div class="page-header">
    <div>
        <h1 class="page-title">Site Components</h1>
        <p class="page-subtitle">
            Manage data-driven sections and components across your site
        </p>
    </div>
</div>

<?php if (empty($component_registry)): ?>
<div class="card">
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M12 8v8"/>
            <path d="M8 12h8"/>
        </svg>
        <h3>No components configured</h3>
        <p>Create <code>admin/custom/components_registry.php</code> to define your site's components.</p>
    </div>
</div>
<?php else: ?>
<div class="components-grid">
    <?php foreach ($component_registry as $comp_id => $comp): ?>
    <a href="?component=<?= $comp_id ?>" class="component-card" style="--component-color: <?= $comp['color'] ?>">
        <div class="component-card-header">
            <div class="component-icon" style="background: <?= $comp['color'] ?>">
                <?= get_component_icon($comp['icon']) ?>
            </div>
            <span class="component-name"><?= htmlspecialchars($comp['name']) ?></span>
        </div>
        <p class="component-desc"><?= htmlspecialchars($comp['description']) ?></p>
        <div class="component-meta">
            <span class="component-badge"><?= $comp['file'] ?></span>
            <?php if ($comp['supports_translations']): ?>
            <span class="component-badge translatable">🌐 Translatable</span>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<?php $comp = $component_registry[$current_component]; ?>
<!-- Component Editor View -->
<a href="components.php" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 12H5"/>
        <polyline points="12 19 5 12 12 5"/>
    </svg>
    Back to Components
</a>

<div class="page-header">
    <div>
        <h1 class="page-title" style="display: flex; align-items: center; gap: 12px;">
            <span style="width: 36px; height: 36px; border-radius: 8px; background: <?= $comp['color'] ?>; display: flex; align-items: center; justify-content: center;">
                <?= get_component_icon($comp['icon']) ?>
            </span>
            <?= htmlspecialchars($comp['name']) ?>
        </h1>
        <p class="page-subtitle"><?= htmlspecialchars($comp['description']) ?></p>
    </div>
</div>

<form method="POST" id="componentForm">
    <div class="editor-container" style="--component-color: <?= $comp['color'] ?>">
        <!-- Editor Panel -->
        <div class="editor-card">
            <div class="editor-card-header">
                <div class="editor-card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit Items
                </div>
                <span style="font-size: 12px; color: var(--text-muted);"><?= $comp['file'] ?></span>
            </div>
            
            <div class="editor-card-body">
                <?php if ($comp['supports_translations']): ?>
                <!-- Language Tabs -->
                <div class="comp-lang-tabs">
                    <?php foreach ($languages as $lang_code => $lang_info): ?>
                    <a href="?component=<?= $current_component ?>&lang=<?= $lang_code ?>" 
                       class="comp-lang-tab <?= $current_lang === $lang_code ? 'active' : '' ?>">
                        <span class="flag"><?= $lang_info['flag'] ?></span>
                        <?= $lang_info['name'] ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Items -->
                <div class="component-items" id="componentItems">
                    <?php if (empty($component_data)): ?>
                    <div class="empty-state" id="emptyState">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M12 8v8"/>
                            <path d="M8 12h8"/>
                        </svg>
                        <p>No items yet. Add your first item below.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($component_data as $index => $item): ?>
                        <div class="component-item" data-index="<?= $index ?>">
                            <div class="item-header">
                                <span class="item-number"><?= $index + 1 ?></span>
                                <button type="button" class="item-delete" onclick="deleteItem(this)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 6h18"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="item-fields">
                                <?php foreach ($comp['fields'] as $field_name => $field_config): ?>
                                <div class="item-field <?= $field_config['type'] === 'checkbox' ? 'checkbox-field' : '' ?>">
                                    <?php if ($field_config['type'] === 'checkbox'): ?>
                                    <input type="checkbox" 
                                           name="items[<?= $index ?>][<?= $field_name ?>]" 
                                           value="1"
                                           id="<?= $field_name ?>_<?= $index ?>"
                                           <?= !empty($item[$field_name]) && $item[$field_name] !== 'false' ? 'checked' : '' ?>
                                           onchange="updatePreview()">
                                    <label for="<?= $field_name ?>_<?= $index ?>"><?= htmlspecialchars($field_config['label']) ?></label>
                                    <?php else: ?>
                                    <label><?= htmlspecialchars($field_config['label']) ?></label>
                                    <?php if ($field_config['type'] === 'select'): ?>
                                    <select name="items[<?= $index ?>][<?= $field_name ?>]" onchange="updatePreview()">
                                        <?php foreach ($field_config['options'] as $opt_val => $opt_label): ?>
                                        <option value="<?= $opt_val ?>" <?= ($item[$field_name] ?? '') === $opt_val ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($opt_label) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php elseif ($field_config['type'] === 'textarea'): ?>
                                    <textarea name="items[<?= $index ?>][<?= $field_name ?>]" 
                                              placeholder="<?= htmlspecialchars($field_config['placeholder'] ?? '') ?>"
                                              oninput="updatePreview()"><?= htmlspecialchars($item[$field_name] ?? '') ?></textarea>
                                    <?php else: ?>
                                    <input type="text" 
                                           name="items[<?= $index ?>][<?= $field_name ?>]" 
                                           value="<?= htmlspecialchars($item[$field_name] ?? '') ?>"
                                           placeholder="<?= htmlspecialchars($field_config['placeholder'] ?? '') ?>"
                                           oninput="updatePreview()">
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="add-item-btn" onclick="addNewItem()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    Add Item
                </button>
            </div>
        </div>
        
        <!-- Preview Panel -->
        <div class="editor-card preview-panel">
            <div class="editor-card-header">
                <div class="editor-card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    Preview
                    <span style="background: var(--accent-green); color: white; font-size: 10px; padding: 3px 8px; border-radius: 10px;">LIVE</span>
                </div>
            </div>
            
            <div class="editor-card-body">
                <div id="previewContent"></div>
            </div>
        </div>
    </div>
    
    <!-- Save Bar -->
    <div class="save-bar">
        <div class="save-bar-info">
            <span>Editing: <strong><?= htmlspecialchars($comp['name']) ?></strong></span>
            <?php if ($comp['supports_translations']): ?>
            <span>•</span>
            <span><?= $languages[$current_lang]['flag'] ?> <?= $languages[$current_lang]['name'] ?></span>
            <?php endif; ?>
            <span>•</span>
            <span id="itemCount"><?= count($component_data) ?> items</span>
        </div>
        <div class="save-bar-actions">
            <a href="components.php" class="btn btn-secondary">Cancel</a>
            <button type="button" class="btn btn-secondary" onclick="rebuildSite()" id="rebuildBtn" title="Rebuild Hugo site">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 4v6h-6"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                <span id="rebuildText">Rebuild</span>
            </button>
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Save Changes
            </button>
        </div>
    </div>
</form>

<script>
const componentFields = <?= json_encode($comp['fields']) ?>;
const previewTemplate = <?= json_encode($comp['preview_template'] ?? 'default') ?>;
let itemCounter = <?= count($component_data) ?>;

function addNewItem() {
    const container = document.getElementById('componentItems');
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.remove();
    
    const index = itemCounter++;
    const item = document.createElement('div');
    item.className = 'component-item';
    item.dataset.index = index;
    
    let fieldsHtml = '';
    for (const [fieldName, fieldConfig] of Object.entries(componentFields)) {
        if (fieldConfig.type === 'checkbox') {
            fieldsHtml += `<div class="item-field checkbox-field">`;
            fieldsHtml += `<input type="checkbox" name="items[${index}][${fieldName}]" value="1" id="${fieldName}_${index}" onchange="updatePreview()">`;
            fieldsHtml += `<label for="${fieldName}_${index}">${fieldConfig.label}</label>`;
            fieldsHtml += '</div>';
        } else {
            fieldsHtml += `<div class="item-field"><label>${fieldConfig.label}</label>`;
            
            if (fieldConfig.type === 'select') {
                fieldsHtml += `<select name="items[${index}][${fieldName}]" onchange="updatePreview()">`;
                for (const [optVal, optLabel] of Object.entries(fieldConfig.options || {})) {
                    fieldsHtml += `<option value="${optVal}">${optLabel}</option>`;
                }
                fieldsHtml += '</select>';
            } else if (fieldConfig.type === 'textarea') {
                fieldsHtml += `<textarea name="items[${index}][${fieldName}]" placeholder="${fieldConfig.placeholder || ''}" oninput="updatePreview()"></textarea>`;
            } else {
                fieldsHtml += `<input type="text" name="items[${index}][${fieldName}]" placeholder="${fieldConfig.placeholder || ''}" oninput="updatePreview()">`;
            }
            
            fieldsHtml += '</div>';
        }
    }
    
    item.innerHTML = `
        <div class="item-header">
            <span class="item-number">${index + 1}</span>
            <button type="button" class="item-delete" onclick="deleteItem(this)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </button>
        </div>
        <div class="item-fields">${fieldsHtml}</div>
    `;
    
    container.appendChild(item);
    item.querySelector('input, textarea')?.focus();
    updateNumbers();
    updatePreview();
}

function deleteItem(button) {
    if (confirm('Delete this item?')) {
        button.closest('.component-item').remove();
        updateNumbers();
        updatePreview();
    }
}

function updateNumbers() {
    document.querySelectorAll('.component-item').forEach((item, i) => {
        item.querySelector('.item-number').textContent = i + 1;
    });
    document.getElementById('itemCount').textContent = 
        document.querySelectorAll('.component-item').length + ' items';
}

function updatePreview() {
    const container = document.getElementById('previewContent');
    const items = document.querySelectorAll('.component-item');
    
    if (items.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>Add items to see preview</p></div>';
        return;
    }
    
    let html = '';
    
    if (previewTemplate === 'quickaccess') {
        html = '<div class="preview-quickaccess">';
        items.forEach(item => {
            const title = item.querySelector('[name*="[title]"]')?.value || '';
            html += `<div class="preview-qa-btn"><div class="preview-qa-icon"></div><div class="preview-qa-label">${escapeHtml(title)}</div></div>`;
        });
        html += '</div>';
    } else if (previewTemplate === 'features') {
        items.forEach(item => {
            const title = item.querySelector('[name*="[title]"]')?.value || '';
            const desc = item.querySelector('[name*="[description]"]')?.value || '';
            html += `<div class="preview-feature-card"><div class="preview-feature-title">${escapeHtml(title)}</div><div class="preview-feature-desc">${escapeHtml(desc)}</div></div>`;
        });
    } else if (previewTemplate === 'faq') {
        items.forEach(item => {
            const q = item.querySelector('[name*="[question]"]')?.value || '';
            const a = item.querySelector('[name*="[answer]"]')?.value || '';
            html += `<div class="preview-faq-item"><div class="preview-faq-q">${escapeHtml(q)}</div><div class="preview-faq-a">${escapeHtml(a)}</div></div>`;
        });
    } else {
        items.forEach(item => {
            const firstInput = item.querySelector('input, textarea');
            html += `<div class="preview-item">${escapeHtml(firstInput?.value || '(empty)')}</div>`;
        });
    }
    
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
updatePreview();

// Toast auto-hide
document.querySelectorAll('.toast-message').forEach(toast => {
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
});

// Ctrl+S
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('componentForm').submit();
    }
});

// Rebuild site
function rebuildSite() {
    const btn = document.getElementById('rebuildBtn');
    const text = document.getElementById('rebuildText');
    
    btn.disabled = true;
    text.textContent = 'Building...';
    
    fetch('api.php?action=build', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            text.textContent = 'Done!';
            showToast('Site rebuilt successfully!', 'success');
            setTimeout(() => {
                text.textContent = 'Rebuild';
                btn.disabled = false;
            }, 2000);
        } else {
            text.textContent = 'Failed';
            showToast('Build failed: ' + (data.error || 'Unknown error'), 'error');
            setTimeout(() => {
                text.textContent = 'Rebuild';
                btn.disabled = false;
            }, 2000);
        }
    })
    .catch(error => {
        text.textContent = 'Error';
        showToast('Request failed: ' + error.message, 'error');
        setTimeout(() => {
            text.textContent = 'Rebuild';
            btn.disabled = false;
        }, 2000);
    });
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-message ${type}`;
    toast.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            ${type === 'success' 
                ? '<polyline points="20 6 9 17 4 12"/>' 
                : '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'}
        </svg>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
</script>
<?php endif; ?>

<?php
function get_component_icon($icon) {
    $icons = [
        'zap' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'help-circle' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'star' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'building' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>',
        'video' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>',
    ];
    return $icons[$icon] ?? $icons['help-circle'];
}

require __DIR__ . '/../includes/footer.php';
?>

