<?php
/**
 * Pugo Admin - Template Editor
 * 
 * Edit individual Hugo templates with CodeMirror syntax highlighting and live preview.
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$layouts_dir = HUGO_ROOT . '/layouts';
$file = $_GET['file'] ?? '';
$is_new = isset($_GET['new']);
$template_type = $_GET['type'] ?? 'page';
$template_name = $_GET['name'] ?? '';

// For new templates
if ($is_new) {
    if (empty($template_name)) {
        header('Location: templates.php');
        exit;
    }
    
    // Determine file path based on type
    switch ($template_type) {
        case 'partial':
            $file = 'partials/' . $template_name . '.html';
            break;
        case 'shortcode':
            $file = 'shortcodes/' . $template_name . '.html';
            break;
        case 'section':
            $file = $template_name . '/single.html';
            break;
        case 'page':
        default:
            $file = 'page/' . $template_name . '.html';
            break;
    }
    
    $page_title = 'New Template: ' . $template_name;
    $template_content = '';
    
    // Default content based on type
    switch ($template_type) {
        case 'partial':
            $template_content = '{{/* Partial: ' . $template_name . ' */}}
<div class="' . $template_name . '">
    {{ .Content }}
</div>';
            break;
        case 'shortcode':
            $template_content = '{{/* Shortcode: ' . $template_name . ' */}}
{{/* Usage: {{< ' . $template_name . ' param="value" >}} */}}

{{ $param := .Get "param" | default "" }}

<div class="' . $template_name . '">
    {{ if $param }}
        <p>{{ $param }}</p>
    {{ end }}
    {{ .Inner }}
</div>';
            break;
        case 'section':
            $template_content = '{{ define "main" }}
<article class="' . $template_name . '">
    <header>
        <h1>{{ .Title }}</h1>
        {{ with .Description }}
        <p class="description">{{ . }}</p>
        {{ end }}
    </header>
    
    <div class="content">
        {{ .Content }}
    </div>
</article>
{{ end }}';
            break;
        case 'page':
        default:
            $template_content = '{{ define "main" }}
<article class="page-' . $template_name . '">
    <h1>{{ .Title }}</h1>
    
    {{ if .Params.description }}
    <p class="lead">{{ .Params.description }}</p>
    {{ end }}
    
    <div class="content">
        {{ .Content }}
    </div>
</article>
{{ end }}';
            break;
    }
} else {
    // Editing existing template
    if (empty($file)) {
        header('Location: templates.php');
        exit;
    }
    
    $file_path = $layouts_dir . '/' . $file;
    
    // Security check
    $real_path = realpath($file_path);
    $real_layouts = realpath($layouts_dir);
    
    if (!$real_path || !str_starts_with($real_path, $real_layouts)) {
        $_SESSION['error'] = 'Invalid template path';
        header('Location: templates.php');
        exit;
    }
    
    if (!file_exists($file_path)) {
        $_SESSION['error'] = 'Template not found: ' . $file;
        header('Location: templates.php');
        exit;
    }
    
    $template_content = file_get_contents($file_path);
    $page_title = 'Edit: ' . basename($file);
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    $new_content = $_POST['content'] ?? '';
    $target_path = $layouts_dir . '/' . $file;
    
    // Security check
    $target_dir = dirname($target_path);
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    if (file_put_contents($target_path, $new_content) !== false) {
        // Rebuild Hugo
        $build_result = build_hugo();
        if ($build_result['success']) {
            $_SESSION['success'] = 'Template saved and site rebuilt!';
        } else {
            $_SESSION['success'] = 'Template saved!';
            $_SESSION['warning'] = 'Hugo rebuild had warnings.';
        }
        header('Location: template-edit.php?file=' . urlencode($file));
        exit;
    } else {
        $_SESSION['error'] = 'Failed to save template. Check permissions.';
    }
    
    $template_content = $new_content;
}

// Get file info
$file_extension = pathinfo($file, PATHINFO_EXTENSION);
$file_name = basename($file);
$file_dir = dirname($file);

// Determine template category
$template_category = 'Template';
if (strpos($file, 'partials/') === 0) {
    $template_category = 'Partial';
} elseif (strpos($file, 'shortcodes/') === 0) {
    $template_category = 'Shortcode';
} elseif (strpos($file, '_default/') === 0) {
    $template_category = 'Default Template';
} elseif ($file === 'index.html') {
    $template_category = 'Homepage';
}

require __DIR__ . '/../includes/header.php';
?>

<!-- CodeMirror 5 CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">

<style>
/* Top Info Bar */
.template-info-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.info-bar-section {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-right: 16px;
    border-right: 1px solid var(--border-color);
}

.info-bar-section:last-child {
    border-right: none;
    padding-right: 0;
}

.info-bar-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
}

.info-bar-value {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-primary);
}

.info-bar-value.mono {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
}

/* Syntax snippets bar */
.syntax-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    flex: 1;
}

.syntax-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: var(--accent-primary);
    cursor: pointer;
    transition: all 0.15s;
}

.syntax-chip:hover {
    background: var(--bg-hover);
    border-color: var(--accent-primary);
}

.syntax-chip-label {
    color: var(--text-muted);
    font-family: 'DM Sans', sans-serif;
}

/* Keyboard shortcuts in bar */
.shortcuts-bar {
    display: flex;
    align-items: center;
    gap: 12px;
}

.shortcut-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
    color: var(--text-muted);
}

.shortcut-badge kbd {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: 3px;
    padding: 1px 4px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
}

/* Template Usage Bar */
.template-usage-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    margin-bottom: 16px;
}

.usage-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
}

.usage-items {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    flex: 1;
}

.usage-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 12px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.15s;
}

.usage-item:hover {
    border-color: var(--accent-primary);
    background: var(--bg-hover);
}

.usage-section {
    font-size: 10px;
    color: var(--text-muted);
    padding: 1px 5px;
    background: var(--bg-primary);
    border-radius: 3px;
}

.usage-more {
    font-size: 11px;
    color: var(--text-muted);
    padding: 4px 8px;
}

.usage-count {
    font-size: 11px;
    color: var(--text-muted);
    white-space: nowrap;
    margin-left: auto;
}

/* Template Editor Layout */
.template-editor-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    min-height: calc(100vh - 320px);
    transition: grid-template-columns 0.3s ease;
}

.template-editor-layout.preview-hidden {
    grid-template-columns: 1fr;
}

.template-editor-layout.preview-hidden .template-preview-panel {
    display: none;
}

/* Editor Container */
.template-editor {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.editor-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-color);
}

.editor-file-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.editor-file-icon {
    width: 28px;
    height: 28px;
    background: var(--accent-primary);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
}

.editor-file-icon svg {
    width: 14px;
    height: 14px;
    color: white;
}

.editor-file-name {
    font-weight: 600;
    font-size: 13px;
}

.editor-file-path {
    font-size: 11px;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
}

.editor-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.editor-status {
    font-size: 11px;
    color: var(--text-muted);
    margin-right: 8px;
}

.editor-status.modified {
    color: var(--accent-yellow);
}

/* Toggle Preview Button */
.toggle-preview-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    color: var(--text-secondary);
    font-size: 11px;
    cursor: pointer;
    transition: all 0.15s;
}

.toggle-preview-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.toggle-preview-btn.active {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

.toggle-preview-btn svg {
    width: 14px;
    height: 14px;
}

/* CodeMirror Container */
.code-editor-wrapper {
    flex: 1;
    min-height: 500px;
    overflow: hidden;
}

/* CodeMirror Theme Customization */
.CodeMirror {
    height: 100% !important;
    font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace !important;
    font-size: 13px !important;
    line-height: 1.5 !important;
    background: #1e1e1e !important;
}

.CodeMirror-gutters {
    background: #1a1a1a !important;
    border-right: 1px solid #333 !important;
}

.CodeMirror-linenumber {
    color: #555 !important;
    font-size: 11px !important;
}

.CodeMirror-activeline-background {
    background: rgba(255, 255, 255, 0.03) !important;
}

.CodeMirror-cursor {
    border-left: 2px solid var(--accent-primary) !important;
}

.CodeMirror-selected {
    background: rgba(225, 29, 72, 0.25) !important;
}

.CodeMirror-matchingbracket {
    background: rgba(225, 29, 72, 0.3) !important;
    color: inherit !important;
}

/* Hugo template syntax highlighting */
.cm-hugo-bracket { color: #e11d48 !important; font-weight: bold; }
.cm-hugo-keyword { color: #c678dd !important; }
.cm-hugo-variable { color: #61afef !important; }
.cm-hugo-dot { color: #98c379 !important; }
.cm-hugo-function { color: #e5c07b !important; }
.cm-hugo-comment { color: #5c6370 !important; font-style: italic; }

/* Search dialog */
.CodeMirror-dialog {
    background: var(--bg-tertiary) !important;
    border-bottom: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
    padding: 6px 10px !important;
}

.CodeMirror-dialog input {
    background: var(--bg-secondary) !important;
    border: 1px solid var(--border-color) !important;
    color: var(--text-primary) !important;
    border-radius: 4px !important;
    padding: 4px 8px !important;
}

/* Preview Panel */
.template-preview-panel {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-color);
}

.preview-header-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
}

.preview-header svg {
    width: 14px;
    height: 14px;
}

.preview-tabs {
    display: flex;
    gap: 4px;
}

.preview-tab {
    padding: 4px 10px;
    font-size: 11px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 4px;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.15s;
}

.preview-tab:hover {
    color: var(--text-secondary);
}

.preview-tab.active {
    background: var(--bg-secondary);
    border-color: var(--border-color);
    color: var(--text-primary);
}

.preview-content {
    flex: 1;
    overflow: hidden;
    position: relative;
}

.preview-frame-wrapper {
    position: absolute;
    inset: 0;
    overflow: auto;
    background: white;
}

.preview-frame {
    width: 100%;
    min-height: 100%;
    padding: 20px;
    color: #333;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
    line-height: 1.6;
}

.preview-frame h1 { font-size: 28px; margin: 0 0 16px; color: #111; }
.preview-frame h2 { font-size: 22px; margin: 24px 0 12px; color: #222; }
.preview-frame h3 { font-size: 18px; margin: 20px 0 10px; color: #333; }
.preview-frame p { margin: 0 0 12px; }
.preview-frame a { color: #e11d48; }
.preview-frame ul, .preview-frame ol { margin: 0 0 12px; padding-left: 24px; }
.preview-frame li { margin-bottom: 4px; }
.preview-frame img { max-width: 100%; height: auto; border-radius: 4px; }
.preview-frame .lead { font-size: 18px; color: #666; margin-bottom: 20px; }
.preview-frame .description { color: #666; font-style: italic; }
.preview-frame article { max-width: 100%; }
.preview-frame .content { line-height: 1.7; }

/* Preview placeholder styles */
.preview-placeholder {
    background: #f3f4f6;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 16px;
    margin: 12px 0;
    text-align: center;
    color: #6b7280;
    font-size: 12px;
}

.preview-placeholder-inline {
    background: #fef3c7;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 11px;
    color: #92400e;
}

/* Category badge */
.category-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.category-badge.partial { background: rgba(168, 85, 247, 0.2); color: #a855f7; }
.category-badge.shortcode { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.category-badge.default-template { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
.category-badge.template, .category-badge.homepage { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }

@media (max-width: 1000px) {
    .template-editor-layout {
        grid-template-columns: 1fr;
    }
    .template-preview-panel {
        display: none;
    }
    .toggle-preview-btn {
        display: none;
    }
}

@media (max-width: 800px) {
    .template-info-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    .info-bar-section {
        border-right: none;
        padding-right: 0;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
        width: 100%;
    }
    .info-bar-section:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .shortcuts-bar {
        display: none;
    }
}
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="templates.php">Templates</a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <?php if ($file_dir !== '.'): ?>
    <a href="templates.php"><?= htmlspecialchars($file_dir) ?></a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <?php endif; ?>
    <span><?= htmlspecialchars($file_name) ?></span>
</div>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title" style="display: flex; align-items: center; gap: 12px;">
            <?= htmlspecialchars($file_name) ?>
            <span class="category-badge <?= strtolower(str_replace(' ', '-', $template_category)) ?>">
                <?= $template_category ?>
            </span>
        </h1>
        <p class="page-subtitle">
            layouts/<?= htmlspecialchars($file) ?>
        </p>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div style="background: rgba(225, 29, 72, 0.1); border: 1px solid #e11d48; color: #e11d48; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<!-- Top Info Bar -->
<div class="template-info-bar">
    <div class="info-bar-section">
        <span class="info-bar-label">Type</span>
        <span class="info-bar-value"><?= $template_category ?></span>
    </div>
    
    <?php if (!$is_new && isset($file_path)): ?>
    <div class="info-bar-section">
        <span class="info-bar-label">Modified</span>
        <span class="info-bar-value"><?= date('M j, H:i', filemtime($file_path)) ?></span>
    </div>
    <?php endif; ?>
    
    <div class="info-bar-section syntax-bar">
        <span class="info-bar-label">Insert:</span>
        <span class="syntax-chip" onclick="insertSnippet('{{ .Title }}')">{{ .Title }}</span>
        <span class="syntax-chip" onclick="insertSnippet('{{ .Content }}')">{{ .Content }}</span>
        <span class="syntax-chip" onclick="insertSnippet('{{ .Params.x }}')">{{ .Params.x }}</span>
        <span class="syntax-chip" onclick="insertSnippet('{{ range .Pages }}\n  {{ .Title }}\n{{ end }}')">{{ range }}</span>
        <span class="syntax-chip" onclick="insertSnippet('{{ if .Params.x }}\n  ...\n{{ end }}')">{{ if }}</span>
        <span class="syntax-chip" onclick="insertSnippet('{{ partial &quot;name.html&quot; . }}')">{{ partial }}</span>
        <span class="syntax-chip" onclick="insertSnippet('{{ with .Params.x }}\n  {{ . }}\n{{ end }}')">{{ with }}</span>
    </div>
    
    <div class="info-bar-section shortcuts-bar">
        <span class="shortcut-badge"><kbd>Ctrl</kbd>+<kbd>S</kbd> Save</span>
        <span class="shortcut-badge"><kbd>Ctrl</kbd>+<kbd>F</kbd> Find</span>
        <span class="shortcut-badge"><kbd>Ctrl</kbd>+<kbd>/</kbd> Comment</span>
    </div>
</div>

<?php
// Get content files that use this template
if (!$is_new) {
    $using_content = get_content_using_template($file);
}
if (!empty($using_content)): 
?>
<div class="template-usage-bar">
    <span class="usage-label"><?= pugo_icon('file-text', 14) ?> Used by:</span>
    <div class="usage-items">
        <?php foreach (array_slice($using_content, 0, 8) as $content_item): ?>
        <a href="edit.php?file=<?= urlencode($content_item['path']) ?>" class="usage-item" title="<?= htmlspecialchars($content_item['path']) ?>">
            <?= htmlspecialchars($content_item['title']) ?>
            <span class="usage-section"><?= htmlspecialchars($content_item['section']) ?></span>
        </a>
        <?php endforeach; ?>
        <?php if (count($using_content) > 8): ?>
        <span class="usage-more">+<?= count($using_content) - 8 ?> more</span>
        <?php endif; ?>
    </div>
    <span class="usage-count"><?= count($using_content) ?> file<?= count($using_content) !== 1 ? 's' : '' ?></span>
</div>
<?php endif; ?>

<form method="POST" id="templateForm">
    <?= csrf_field() ?>
    
    <div class="template-editor-layout preview-hidden" id="editorLayout">
        <!-- Code Editor -->
        <div class="template-editor">
            <div class="editor-header">
                <div class="editor-file-info">
                    <div class="editor-file-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="16 18 22 12 16 6"/>
                            <polyline points="8 6 2 12 8 18"/>
                        </svg>
                    </div>
                    <div>
                        <div class="editor-file-name"><?= htmlspecialchars($file_name) ?></div>
                        <div class="editor-file-path"><?= htmlspecialchars($file) ?></div>
                    </div>
                </div>
                <div class="editor-actions">
                    <span class="editor-status" id="editorStatus">Ready</span>
                    
                    <button type="button" class="toggle-preview-btn" id="togglePreview" onclick="togglePreview()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Preview
                    </button>
                    
                    <a href="templates.php" class="btn btn-secondary btn-sm">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save
                    </button>
                </div>
            </div>
            <div class="code-editor-wrapper">
                <textarea id="codeEditor" name="content"><?= htmlspecialchars($template_content) ?></textarea>
            </div>
        </div>
        
        <!-- Live Preview Panel -->
        <div class="template-preview-panel" id="previewPanel">
            <div class="preview-header">
                <div class="preview-header-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    Live Preview
                </div>
                <div class="preview-tabs">
                    <button type="button" class="preview-tab active" data-view="desktop">Desktop</button>
                    <button type="button" class="preview-tab" data-view="mobile">Mobile</button>
                </div>
            </div>
            <div class="preview-content">
                <div class="preview-frame-wrapper" id="previewWrapper">
                    <div class="preview-frame" id="previewFrame">
                        <!-- Preview content will be rendered here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- CodeMirror 5 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closetag.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/xml-fold.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/selection/active-line.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>

<script>
// Sample data for preview
const sampleData = {
    title: "Sample Page Title",
    description: "This is a sample description for preview purposes.",
    content: `<p>This is sample content that would normally come from your markdown file.</p>
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt.</p>
<h2>A Subheading</h2>
<p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
<ul><li>First item</li><li>Second item</li><li>Third item</li></ul>`,
    date: "January 15, 2025",
    pages: [
        { title: "First Page", url: "/first/" },
        { title: "Second Page", url: "/second/" },
        { title: "Third Page", url: "/third/" }
    ]
};

// Hugo template to HTML preview converter
function hugoToPreview(template) {
    let html = template;
    
    // Remove define/block wrappers
    html = html.replace(/\{\{\s*define\s+"[^"]+"\s*\}\}/g, '');
    html = html.replace(/\{\{\s*block\s+"[^"]+"\s*\.\s*\}\}\s*\{\{\s*end\s*\}\}/g, '');
    html = html.replace(/\{\{\s*end\s*\}\}/g, '');
    
    // Remove comments
    html = html.replace(/\{\{\/\*[\s\S]*?\*\/\}\}/g, '');
    
    // Replace common Hugo variables
    html = html.replace(/\{\{\s*\.Title\s*\}\}/g, sampleData.title);
    html = html.replace(/\{\{\s*\.Description\s*\}\}/g, sampleData.description);
    html = html.replace(/\{\{\s*\.Content\s*\}\}/g, sampleData.content);
    html = html.replace(/\{\{\s*\.Date\.Format\s*"[^"]+"\s*\}\}/g, sampleData.date);
    html = html.replace(/\{\{\s*\.Date\s*\}\}/g, sampleData.date);
    html = html.replace(/\{\{\s*\.Permalink\s*\}\}/g, '/sample-page/');
    html = html.replace(/\{\{\s*\.RelPermalink\s*\}\}/g, '/sample-page/');
    html = html.replace(/\{\{\s*\.Site\.Title\s*\}\}/g, 'My Site');
    
    // Replace .Params
    html = html.replace(/\{\{\s*\.Params\.description\s*\}\}/g, sampleData.description);
    html = html.replace(/\{\{\s*\.Params\.image\s*\}\}/g, 'https://picsum.photos/800/400');
    html = html.replace(/\{\{\s*\.Params\.(\w+)\s*\}\}/g, '<span class="preview-placeholder-inline">$1</span>');
    
    // Handle with blocks
    html = html.replace(/\{\{\s*with\s+\.Params\.image\s*\}\}([\s\S]*?)\{\{/g, (m, inner) => inner.replace(/\{\{\s*\.\s*\}\}/g, 'https://picsum.photos/800/400') + '{{');
    html = html.replace(/\{\{\s*with\s+\.Description\s*\}\}([\s\S]*?)\{\{/g, (m, inner) => inner.replace(/\{\{\s*\.\s*\}\}/g, sampleData.description) + '{{');
    html = html.replace(/\{\{\s*with\s+[^\}]+\s*\}\}/g, '');
    
    // Handle if/else
    html = html.replace(/\{\{\s*if\s+[^\}]+\s*\}\}/g, '');
    html = html.replace(/\{\{\s*else\s*\}\}/g, '');
    
    // Handle range .Pages
    html = html.replace(/\{\{\s*range\s+\.Pages\s*\}\}([\s\S]*?)\{\{\s*end\s*\}\}/g, (m, inner) => {
        return sampleData.pages.map(page => inner.replace(/\{\{\s*\.Title\s*\}\}/g, page.title).replace(/\{\{\s*\.Permalink\s*\}\}/g, page.url)).join('');
    });
    
    // Other range blocks
    html = html.replace(/\{\{\s*range\s+[^\}]+\s*\}\}([\s\S]*?)\{\{\s*end\s*\}\}/g, '<div class="preview-placeholder">{{ range }} loop</div>');
    
    // Replace partial/template calls
    html = html.replace(/\{\{\s*partial\s+"([^"]+)"\s*[^\}]*\}\}/g, '<div class="preview-placeholder">partial: $1</div>');
    html = html.replace(/\{\{\s*template\s+"([^"]+)"\s*[^\}]*\}\}/g, '<div class="preview-placeholder">template: $1</div>');
    
    // Remaining tags
    html = html.replace(/\{\{\s*([^}]+)\s*\}\}/g, '<span class="preview-placeholder-inline">{{ $1 }}</span>');
    
    return html;
}

// Update preview
function updatePreview() {
    const code = editor.getValue();
    document.getElementById('previewFrame').innerHTML = hugoToPreview(code);
}

// Preview toggle state (hidden by default)
let previewVisible = false;

// Define Hugo template mode
CodeMirror.defineMode("hugo-html", function(config) {
    const htmlMode = CodeMirror.getMode(config, "htmlmixed");
    return {
        startState: () => ({ htmlState: CodeMirror.startState(htmlMode), inHugo: false, hugoComment: false }),
        copyState: (s) => ({ htmlState: CodeMirror.copyState(htmlMode, s.htmlState), inHugo: s.inHugo, hugoComment: s.hugoComment }),
        token: function(stream, state) {
            if (stream.match("{{/*")) { state.hugoComment = true; return "cm-hugo-comment"; }
            if (state.hugoComment) { if (stream.match("*/}}")) { state.hugoComment = false; } else { stream.next(); } return "cm-hugo-comment"; }
            if (stream.match("{{")) { state.inHugo = true; return "cm-hugo-bracket"; }
            if (state.inHugo) {
                if (stream.match("}}")) { state.inHugo = false; return "cm-hugo-bracket"; }
                if (stream.match(/\b(if|else|end|range|with|define|block|partial|template|return)\b/)) return "cm-hugo-keyword";
                if (stream.match(/\.\w+/)) return "cm-hugo-variable";
                if (stream.match(/\$\w*/)) return "cm-hugo-variable";
                if (stream.match(/\b(len|index|first|last|after|sort|where|default|isset|printf|safeHTML|markdownify)\b/)) return "cm-hugo-function";
                if (stream.match(/\||\:=|=/)) return "cm-hugo-keyword";
                stream.next(); return "cm-hugo-dot";
            }
            return htmlMode.token(stream, state.htmlState);
        }
    };
});

// Initialize editor
const textarea = document.getElementById('codeEditor');
const statusEl = document.getElementById('editorStatus');
let isModified = false;

const editor = CodeMirror.fromTextArea(textarea, {
    mode: "hugo-html",
    theme: "material-darker",
    lineNumbers: true,
    lineWrapping: true,
    matchBrackets: true,
    autoCloseBrackets: true,
    autoCloseTags: true,
    foldGutter: true,
    gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
    styleActiveLine: true,
    indentUnit: 2,
    tabSize: 2,
    extraKeys: {
        "Ctrl-S": () => document.getElementById('templateForm').submit(),
        "Cmd-S": () => document.getElementById('templateForm').submit(),
        "Ctrl-/": "toggleComment",
        "Cmd-/": "toggleComment",
        "Tab": (cm) => cm.somethingSelected() ? cm.indentSelection("add") : cm.replaceSelection("  ", "end")
    }
});

// Track changes
let previewTimeout;
editor.on('change', function() {
    if (!isModified) { isModified = true; statusEl.textContent = '● Modified'; statusEl.classList.add('modified'); }
    clearTimeout(previewTimeout);
    previewTimeout = setTimeout(updatePreview, 300);
});

// Insert snippet
function insertSnippet(code) {
    editor.replaceRange(code, editor.getCursor());
    editor.focus();
}

// Preview tabs
document.querySelectorAll('.preview-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.preview-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const wrapper = document.getElementById('previewWrapper');
        if (this.dataset.view === 'mobile') {
            wrapper.style.maxWidth = '375px';
            wrapper.style.margin = '0 auto';
            wrapper.style.borderLeft = '1px solid #ddd';
            wrapper.style.borderRight = '1px solid #ddd';
        } else {
            wrapper.style.cssText = '';
        }
    });
});

// Warn before leaving
window.addEventListener('beforeunload', (e) => { if (isModified) { e.preventDefault(); e.returnValue = ''; } });

// Toggle preview function
function togglePreview() {
    previewVisible = !previewVisible;
    const layout = document.getElementById('editorLayout');
    const btn = document.getElementById('togglePreview');
    
    if (previewVisible) {
        layout.classList.remove('preview-hidden');
        btn.classList.add('active');
    } else {
        layout.classList.add('preview-hidden');
        btn.classList.remove('active');
    }
    
    // Save preference
    localStorage.setItem('templatePreviewVisible', previewVisible ? 'true' : 'false');
    
    // Refresh editor after layout change
    setTimeout(function() { editor.refresh(); }, 150);
}

// Restore preview preference (show only if user previously enabled it)
if (localStorage.getItem('templatePreviewVisible') === 'true') {
    previewVisible = true;
    document.getElementById('editorLayout').classList.remove('preview-hidden');
    document.getElementById('togglePreview').classList.add('active');
}

// Initial setup
editor.focus();
updatePreview();

function resizeEditor() {
    editor.setSize(null, Math.max(500, window.innerHeight - 380));
}
resizeEditor();
window.addEventListener('resize', resizeEditor);
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

