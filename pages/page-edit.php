<?php
/**
 * Pugo Admin - Page Editor
 * 
 * Create and edit standalone pages (Contact, Terms, About, etc.)
 * Uses the same editor interface as the article editor for consistency.
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/content-types.php';
require __DIR__ . '/../includes/dynamic-fields.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$current_lang = $_GET['lang'] ?? 'en';
$is_new = isset($_GET['new']);
$page_slug = $_GET['page'] ?? '';

// Translation variables (used when creating translations of existing pages)
$translate_from = '';
$source_lang = 'en';
$target_lang = $current_lang;

if (!isset($config['languages'][$current_lang])) {
    $current_lang = 'en';
}

$content_dir = get_content_dir_for_lang($current_lang);

// Initialize page data
$page_data = [
    'title' => '',
    'description' => '',
    'layout' => 'default',
    'draft' => false,
    'body' => '',
];

// For new pages
if ($is_new) {
    $page_title = 'Create New Page';
    $page_data['title'] = $_GET['title'] ?? '';
    $page_data['layout'] = $_GET['layout'] ?? 'default';
    $page_slug = $_GET['slug'] ?? '';
    
    // Check if this is a translation request
    $translate_from = $_GET['translate_from'] ?? '';
    $source_lang = $_GET['source_lang'] ?? 'en';
    $target_lang = $_GET['target_lang'] ?? $current_lang;
    
    if ($translate_from) {
        // Load source page content
        $source_content_dir = get_content_dir_for_lang($source_lang);
        $source_path = $source_content_dir . '/' . $translate_from . '/_index.md';
        
        if (file_exists($source_path)) {
            $source_content = file_get_contents($source_path);
            $source_parsed = parse_frontmatter($source_content);
            
            // Pre-fill with source content for translation
            $page_data['title'] = $source_parsed['frontmatter']['title'] ?? '';
            $page_data['description'] = $source_parsed['frontmatter']['description'] ?? '';
            if (is_array($page_data['description'])) {
                $page_data['description'] = implode(' ', $page_data['description']);
            }
            $page_data['layout'] = $source_parsed['frontmatter']['layout'] ?? 'default';
            $page_data['body'] = $source_parsed['body'] ?? '';
            $page_data['frontmatter'] = $source_parsed['frontmatter'];
            $page_slug = $translate_from;
            $current_lang = $target_lang;
            $content_dir = get_content_dir_for_lang($target_lang);
            
            $page_title = 'Create ' . ($config['languages'][$target_lang]['name'] ?? $target_lang) . ' Translation';
        }
    }
    
    // Auto-generate slug from title if not provided
    if (!$page_slug && $page_data['title']) {
        $page_slug = generate_slug($page_data['title']);
    }
} else {
    // Editing existing page
    if (!$page_slug) {
        header('Location: pages.php');
        exit;
    }
    
    $page_path = $content_dir . '/' . $page_slug . '/_index.md';
    
    if (!file_exists($page_path)) {
        $_SESSION['error'] = "Page not found: {$page_slug}";
        header('Location: pages.php?lang=' . $current_lang);
        exit;
    }
    
    $content = file_get_contents($page_path);
    $parsed = parse_frontmatter($content);
    
    // Ensure description is always a string (YAML might parse it as array)
    $description = $parsed['frontmatter']['description'] ?? '';
    if (is_array($description)) {
        $description = implode(' ', $description);
    }
    
    $page_data = array_merge($page_data, [
        'title' => $parsed['frontmatter']['title'] ?? '',
        'description' => $description,
        'layout' => $parsed['frontmatter']['layout'] ?? 'default',
        'draft' => !empty($parsed['frontmatter']['draft']),
        'body' => $parsed['body'] ?? '',
        'frontmatter' => $parsed['frontmatter'],
    ]);
    
    $page_title = 'Edit: ' . $page_data['title'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    $new_title = trim($_POST['title'] ?? '');
    $new_description = trim($_POST['description'] ?? '');
    $new_layout = $_POST['layout'] ?? 'default';
    $new_draft = isset($_POST['draft']);
    $new_body = $_POST['body'] ?? '';
    $new_slug = trim($_POST['slug'] ?? '') ?: generate_slug($new_title);
    
    if (empty($new_title)) {
        $_SESSION['error'] = 'Title is required';
    } elseif (empty($new_slug)) {
        $_SESSION['error'] = 'Slug is required';
    } elseif (!preg_match('/^[a-z0-9-]+$/', $new_slug)) {
        $_SESSION['error'] = 'Slug can only contain lowercase letters, numbers, and hyphens';
    } else {
        $target_dir = $content_dir . '/' . $new_slug;
        $target_file = $target_dir . '/_index.md';
        $error = null;
        
        // Check for conflicts
        if (!$is_new && $new_slug !== $page_slug && is_dir($target_dir)) {
            $error = "A page with slug '{$new_slug}' already exists";
        }
        if ($is_new && is_dir($target_dir)) {
            $error = "A page with slug '{$new_slug}' already exists";
        }
        
        if (!$error) {
            // Build frontmatter
            $frontmatter = [
                'title' => $new_title,
                'description' => $new_description,
                'date' => date('Y-m-d'),
                'lastmod' => date('Y-m-d'),
            ];
            
            if ($new_layout && $new_layout !== 'default') {
                $frontmatter['layout'] = $new_layout;
            }
            
            if ($new_draft) {
                $frontmatter['draft'] = true;
            }
            
            // Preserve other frontmatter fields when editing
            if (!$is_new && isset($page_data['frontmatter'])) {
                $preserve_keys = ['date', 'author', 'aliases', 'url', 'menu', 'weight'];
                foreach ($preserve_keys as $key) {
                    if (isset($page_data['frontmatter'][$key]) && !isset($frontmatter[$key])) {
                        $frontmatter[$key] = $page_data['frontmatter'][$key];
                    }
                }
            }
            
            // Create directory if needed
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            // If slug changed, remove old directory
            if (!$is_new && $new_slug !== $page_slug) {
                $old_dir = $content_dir . '/' . $page_slug;
                if (is_dir($old_dir)) {
                    $old_files = glob($old_dir . '/*');
                    foreach ($old_files as $file) {
                        if (is_file($file)) unlink($file);
                    }
                    rmdir($old_dir);
                }
            }
            
            // Save the file
            if (save_article($target_file, $frontmatter, $new_body)) {
                // Auto-rebuild Hugo
                $build_result = build_hugo();
                if ($build_result['success']) {
                    $_SESSION['success'] = ($is_new ? 'Page created' : 'Page saved') . ' and site rebuilt successfully!';
                } else {
                    $_SESSION['success'] = ($is_new ? 'Page created' : 'Page saved') . ' successfully!';
                    $_SESSION['warning'] = 'Hugo rebuild had warnings.';
                }
                header('Location: page-edit.php?page=' . urlencode($new_slug) . '&lang=' . $current_lang);
                exit;
            } else {
                $_SESSION['error'] = 'Failed to save page. Check file permissions.';
            }
        } else {
            $_SESSION['error'] = $error;
        }
    }
    
    // Keep submitted data on error
    $page_data['title'] = $new_title;
    $page_data['description'] = $new_description;
    $page_data['layout'] = $new_layout;
    $page_data['draft'] = $new_draft;
    $page_data['body'] = $new_body;
    $page_slug = $new_slug;
}

// Get available layouts from Hugo
$available_layouts = ['default' => 'Default'];
$layouts_dir = HUGO_ROOT . '/layouts/page';
if (is_dir($layouts_dir)) {
    foreach (scandir($layouts_dir) as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $available_layouts[$name] = ucwords(str_replace(['-', '_'], ' ', $name));
        }
    }
}
// Also check _default folder
$default_layouts_dir = HUGO_ROOT . '/layouts/_default';
if (is_dir($default_layouts_dir)) {
    foreach (scandir($default_layouts_dir) as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'html' && !isset($available_layouts[pathinfo($file, PATHINFO_FILENAME)])) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!in_array($name, ['baseof', 'list'])) { // Skip base templates
                $available_layouts[$name] = ucwords(str_replace(['-', '_'], ' ', $name));
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';

// Include shared editor styles
require __DIR__ . '/../includes/editor-styles.php';
?>

<style>
/* Page-specific styles */
.page-info-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    padding: 16px 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    margin-bottom: 24px;
}

.page-info-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.page-info-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
}

.page-info-value {
    font-size: 13px;
    font-weight: 500;
}

.page-info-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

/* Language tabs */
.lang-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.lang-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.15s ease;
}

.lang-tab:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.lang-tab.active {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

.lang-tab .flag {
    font-size: 16px;
}

.translation-notice {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: var(--radius-sm);
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.translation-notice svg {
    width: 24px;
    height: 24px;
    color: var(--accent-primary);
    flex-shrink: 0;
}

.translation-notice-text {
    font-size: 14px;
    color: var(--text-secondary);
}

.translation-notice-text strong {
    color: var(--text-primary);
}

/* Sidebar cards */
.editor-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.sidebar-card-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-tertiary);
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sidebar-card-header svg {
    width: 16px;
    height: 16px;
    opacity: 0.7;
}

.sidebar-card-body {
    padding: 16px;
}

.slug-input-group {
    display: flex;
    align-items: center;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    overflow: hidden;
}

.slug-prefix {
    padding: 10px 12px;
    background: var(--bg-primary);
    color: var(--text-muted);
    font-size: 13px;
    font-family: 'JetBrains Mono', monospace;
    border-right: 1px solid var(--border-color);
}

.slug-input-group input {
    flex: 1;
    padding: 10px 12px;
    background: transparent;
    border: none;
    color: var(--text-primary);
    font-size: 13px;
    font-family: 'JetBrains Mono', monospace;
    outline: none;
}

.checkbox-field {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-field input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent-primary);
}

.checkbox-field span {
    font-size: 14px;
    color: var(--text-secondary);
}

/* Two column layout */
.editor-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1100px) {
    .editor-layout {
        grid-template-columns: 1fr;
    }
    .editor-sidebar {
        order: -1;
    }
}
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="pages.php?lang=<?= $current_lang ?>">Pages</a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span><?= $is_new ? 'New Page' : htmlspecialchars($page_data['title']) ?></span>
</div>

<?php if (!$is_new): ?>
<!-- Page Info Bar -->
<div class="page-info-bar">
    <div class="page-info-item">
        <span class="page-info-label">Type</span>
        <span class="page-info-badge" style="background: rgba(59, 130, 246, 0.2); color: #3b82f6;">
            📄 Standalone Page
        </span>
    </div>
    
    <div class="page-info-item">
        <span class="page-info-label">URL</span>
        <span class="page-info-value" style="font-family: monospace; font-size: 12px; color: var(--text-secondary);">
            /<?= htmlspecialchars($page_slug) ?>/
        </span>
    </div>
    
    <div class="page-info-item">
        <span class="page-info-label">Language</span>
        <span class="page-info-value">
            <?= $config['languages'][$current_lang]['flag'] ?? '🌐' ?> 
            <?= $config['languages'][$current_lang]['name'] ?? $current_lang ?>
        </span>
    </div>
    
    <div class="page-info-item">
        <span class="page-info-label">Template</span>
        <span class="page-info-value"><?= htmlspecialchars($available_layouts[$page_data['layout']] ?? $page_data['layout']) ?></span>
    </div>
    
    <?php if (!empty($page_data['draft'])): ?>
    <div class="page-info-item">
        <span class="page-info-badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">
            📝 Draft
        </span>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- Language Tabs - Show when editing OR when creating a translation -->
<?php if (count($config['languages']) > 1 && (!$is_new || (isset($translate_from) && $translate_from))): ?>
<div class="lang-tabs" style="margin-bottom: 24px;">
    <?php foreach ($config['languages'] as $lang => $lang_config): 
        // Check if this language version exists
        $lang_content_dir = get_content_dir_for_lang($lang);
        $lang_page_path = $lang_content_dir . '/' . $page_slug . '/_index.md';
        $lang_exists = file_exists($lang_page_path);
        $is_current = $lang === $current_lang;
    ?>
    <?php if ($is_current): ?>
    <span class="lang-tab active">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
        <?php if ($is_new): ?><span style="font-size: 10px;">(Creating)</span><?php endif; ?>
    </span>
    <?php elseif ($lang_exists): ?>
    <a href="page-edit.php?page=<?= urlencode($page_slug) ?>&lang=<?= $lang ?>" class="lang-tab">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
    </a>
    <?php else: ?>
    <a href="page-edit.php?new=1&translate_from=<?= urlencode($page_slug) ?>&source_lang=<?= $current_lang ?>&target_lang=<?= $lang ?>" 
       class="lang-tab" style="opacity: 0.5;">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
        <span style="font-size: 10px;">+ Create</span>
    </a>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($is_new && isset($translate_from) && $translate_from): ?>
<!-- Translation Notice -->
<div class="translation-notice">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
        <path d="M2 12h20"/>
        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
    </svg>
    <div class="translation-notice-text">
        <strong>Creating Translation:</strong> 
        You are creating a <strong><?= $config['languages'][$target_lang]['name'] ?? $target_lang ?></strong> version of the 
        <strong><?= $config['languages'][$source_lang]['name'] ?? $source_lang ?></strong> page "<?= htmlspecialchars($page_data['title']) ?>".
        The content has been pre-filled for you to translate.
    </div>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $is_new ? 'Create New Page' : htmlspecialchars($page_data['title']) ?></h1>
        <p class="page-subtitle">
            <?php if ($is_new): ?>
                Create a new standalone page for your site
            <?php else: ?>
                Last modified: <?= isset($page_path) ? time_ago(filemtime($page_path)) : 'unknown' ?>
            <?php endif; ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px;">
        <?php if (!$is_new): ?>
        <a href="<?= htmlspecialchars($config['site_url'] ?? '') ?>/<?= htmlspecialchars($page_slug) ?>/" 
           target="_blank" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                <polyline points="15 3 21 3 21 9"/>
                <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            View Page
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$is_new): 
    // Detect which Hugo template is used
    $template_info = detect_hugo_template($page_slug, $page_data, true);
?>
<!-- Page Info Bar -->
<div class="article-info-bar" style="margin-bottom: 16px;">
    <div class="article-info-item">
        <span class="article-info-label">Slug</span>
        <span class="article-info-value" style="font-family: monospace; font-size: 12px;">
            <?= htmlspecialchars($page_slug) ?>
        </span>
    </div>
    
    <div class="article-info-item">
        <span class="article-info-label">Language</span>
        <span class="article-info-value">
            <?= $config['languages'][$current_lang]['flag'] ?? '🌐' ?> 
            <?= $config['languages'][$current_lang]['name'] ?? $current_lang ?>
        </span>
    </div>
    
    <div class="article-info-item">
        <span class="article-info-label">Template</span>
        <?php if ($template_info['exists']): ?>
        <a href="template-edit.php?file=<?= urlencode($template_info['path']) ?>" 
           class="article-info-value" 
           style="font-family: monospace; font-size: 12px; color: var(--accent-blue); text-decoration: none;"
           title="Click to edit this template">
            <?= pugo_icon('code', 12) ?>
            <?= htmlspecialchars($template_info['path']) ?>
        </a>
        <?php else: ?>
        <span class="article-info-value" style="font-family: monospace; font-size: 12px; color: var(--text-muted);" title="Using Hugo default or theme template">
            <?= htmlspecialchars($template_info['path']) ?> (theme/default)
        </span>
        <?php endif; ?>
    </div>
    
    <div class="article-info-item">
        <span class="article-info-label">File</span>
        <span class="article-info-value" style="font-family: monospace; font-size: 12px; color: var(--text-secondary);">
            <?= htmlspecialchars($page_slug) ?>/_index.md
        </span>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div style="background: rgba(225, 29, 72, 0.1); border: 1px solid #e11d48; color: #e11d48; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<!-- Edit Form -->
<form method="POST" id="pageForm">
    <?= csrf_field() ?>
    
    <div class="editor-layout">
        <!-- Main Content -->
        <div>
            <!-- Title & Description -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="titleInput" class="form-input" 
                           value="<?= htmlspecialchars($page_data['title']) ?>" required
                           style="font-size: 18px; font-weight: 600;"
                           oninput="updateSlugFromTitle()">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Description <span style="font-weight: normal; color: var(--text-muted);">(max 160 chars for SEO)</span></label>
                    <textarea name="description" id="descInput" class="form-input" rows="2" maxlength="200"><?= htmlspecialchars($page_data['description']) ?></textarea>
                    <div style="text-align: right; font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        <span id="descCount"><?= strlen($page_data['description']) ?></span>/160
                    </div>
                </div>
            </div>
            
            <!-- Editor with Preview -->
            <div class="card" style="margin-bottom: 24px; padding: 0; overflow: hidden;">
                <?php 
                $show_shortcodes = true;
                require __DIR__ . '/../includes/editor-toolbar.php'; 
                ?>
                
                <div class="editor-container">
                    <!-- Editor Pane -->
                    <div class="editor-pane">
                        <textarea name="body" id="editor" class="form-input" 
                                  placeholder="Write your page content here using Markdown..."><?= htmlspecialchars($page_data['body']) ?></textarea>
                    </div>
                    
                    <!-- Preview Pane -->
                    <div class="preview-pane">
                        <div class="preview-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            Live Preview
                        </div>
                        <div class="preview-content" id="previewContent">
                            <p style="color: var(--text-muted);">Start typing to see preview...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Save Button -->
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="pages.php?lang=<?= $current_lang ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    <?= $is_new ? 'Create Page' : 'Save Changes' ?>
                </button>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="editor-sidebar">
            <!-- URL / Slug -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                    </svg>
                    URL Slug
                </div>
                <div class="sidebar-card-body">
                    <div class="slug-input-group">
                        <span class="slug-prefix">/</span>
                        <input type="text" name="slug" id="slugInput"
                               value="<?= htmlspecialchars($page_slug) ?>"
                               placeholder="page-slug"
                               pattern="[a-z0-9-]+">
                        <span class="slug-prefix">/</span>
                    </div>
                    <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 8px;">
                        Lowercase letters, numbers, and hyphens only
                    </small>
                </div>
            </div>
            
            <!-- Template -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <line x1="3" y1="9" x2="21" y2="9"/>
                        <line x1="9" y1="21" x2="9" y2="9"/>
                    </svg>
                    Template
                </div>
                <div class="sidebar-card-body">
                    <select name="layout" class="form-input">
                        <?php foreach ($available_layouts as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $page_data['layout'] === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 8px;">
                        Choose how this page is rendered
                    </small>
                </div>
            </div>
            
            <!-- Publishing -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    Settings
                </div>
                <div class="sidebar-card-body">
                    <label class="checkbox-field">
                        <input type="checkbox" name="draft" <?= $page_data['draft'] ? 'checked' : '' ?>>
                        <span>Save as draft (unpublished)</span>
                    </label>
                </div>
            </div>
            
            <?php if (!$is_new && count($config['languages']) > 1): ?>
            <!-- Translations -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    Languages
                </div>
                <div class="sidebar-card-body">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php foreach ($config['languages'] as $lang => $lang_config): 
                            $lang_content_dir = get_content_dir_for_lang($lang);
                            $lang_page_exists = file_exists($lang_content_dir . '/' . $page_slug . '/_index.md');
                            $is_current = $lang === $current_lang;
                        ?>
                        <a href="page-edit.php?page=<?= urlencode($page_slug) ?>&lang=<?= $lang ?>"
                           style="display: flex; flex-direction: column; align-items: center; padding: 8px 12px; 
                                  background: <?= $is_current ? 'var(--accent-primary)' : 'var(--bg-tertiary)' ?>; 
                                  border-radius: var(--radius-sm); text-decoration: none;
                                  color: <?= $is_current ? 'white' : 'var(--text-secondary)' ?>;
                                  opacity: <?= $lang_page_exists || $is_current ? '1' : '0.5' ?>;">
                            <span style="font-size: 20px;"><?= $lang_config['flag'] ?></span>
                            <span style="font-size: 10px; margin-top: 2px;"><?= strtoupper($lang) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php 
// Include shortcode modals
$media_hint = 'pages/' . $page_slug;
require __DIR__ . '/../includes/editor-modals.php';

// Include shared editor scripts
$media_path = 'pages/' . $page_slug;
require __DIR__ . '/../includes/editor-scripts.php';
?>

<script>
// Description character counter
document.getElementById('descInput').addEventListener('input', function() {
    document.getElementById('descCount').textContent = this.value.length;
});

// Generate slug from title
function generateSlug(text) {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// Update slug when title changes (only for new pages)
function updateSlugFromTitle() {
    const titleInput = document.getElementById('titleInput');
    const slugInput = document.getElementById('slugInput');
    
    <?php if ($is_new): ?>
    slugInput.value = generateSlug(titleInput.value);
    <?php endif; ?>
}

// Validate slug on input
document.getElementById('slugInput').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
});

// Ctrl+S to save
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('pageForm').submit();
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
