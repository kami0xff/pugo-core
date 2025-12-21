<?php
/**
 * Pugo Admin - Page Editor
 * 
 * Create and edit standalone pages (Contact, Terms, About, etc.)
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$current_lang = $_GET['lang'] ?? 'en';
$is_new = isset($_GET['new']);
$page_slug = $_GET['page'] ?? '';

if (!isset($config['languages'][$current_lang])) {
    $current_lang = 'en';
}

$content_dir = $current_lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . ($config['languages'][$current_lang]['content_dir'] ?? 'content');

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
    
    $page_data = array_merge($page_data, [
        'title' => $parsed['frontmatter']['title'] ?? '',
        'description' => $parsed['frontmatter']['description'] ?? '',
        'layout' => $parsed['frontmatter']['layout'] ?? 'default',
        'draft' => !empty($parsed['frontmatter']['draft']),
        'body' => $parsed['body'] ?? '',
        'frontmatter' => $parsed['frontmatter'],
    ]);
    
    $page_title = 'Edit: ' . $page_data['title'];
}

// Handle form submission
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); // Validate CSRF token
    
    $new_title = trim($_POST['title'] ?? '');
    $new_description = trim($_POST['description'] ?? '');
    $new_layout = $_POST['layout'] ?? 'default';
    $new_draft = isset($_POST['draft']);
    $new_body = $_POST['body'] ?? '';
    $new_slug = trim($_POST['slug'] ?? '') ?: generate_slug($new_title);
    
    if (empty($new_title)) {
        $error = 'Title is required';
    } elseif (empty($new_slug)) {
        $error = 'Slug is required';
    } else {
        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $new_slug)) {
            $error = 'Slug can only contain lowercase letters, numbers, and hyphens';
        } else {
            $target_dir = $content_dir . '/' . $new_slug;
            $target_file = $target_dir . '/_index.md';
            
            // Check for conflicts (different slug for existing page)
            if (!$is_new && $new_slug !== $page_slug) {
                if (is_dir($target_dir)) {
                    $error = "A page with slug '{$new_slug}' already exists";
                }
            }
            
            // Check for conflicts (new page)
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
                        // Remove old files
                        $old_files = glob($old_dir . '/*');
                        foreach ($old_files as $file) {
                            if (is_file($file)) {
                                unlink($file);
                            }
                        }
                        rmdir($old_dir);
                    }
                }
                
                // Save the file
                if (save_article($target_file, $frontmatter, $new_body)) {
                    $_SESSION['success'] = $is_new ? 'Page created successfully!' : 'Page saved successfully!';
                    header('Location: page-edit.php?page=' . urlencode($new_slug) . '&lang=' . $current_lang);
                    exit;
                } else {
                    $error = 'Failed to save page. Check file permissions.';
                }
            }
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

// Check for session messages
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

require __DIR__ . '/../includes/header.php';
?>

<style>
.page-editor {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1100px) {
    .page-editor {
        grid-template-columns: 1fr;
    }
}

.editor-main {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.editor-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: sticky;
    top: 32px;
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
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.editor-card-header svg {
    width: 18px;
    height: 18px;
    opacity: 0.7;
}

.editor-card-body {
    padding: 20px;
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
    padding: 12px 16px;
    background: var(--bg-primary);
    color: var(--text-muted);
    font-size: 14px;
    font-family: 'JetBrains Mono', monospace;
    border-right: 1px solid var(--border-color);
    white-space: nowrap;
}

.slug-input-group input {
    flex: 1;
    padding: 12px 16px;
    background: transparent;
    border: none;
    color: var(--text-primary);
    font-size: 14px;
    font-family: 'JetBrains Mono', monospace;
    outline: none;
}

.title-input {
    font-size: 24px !important;
    font-weight: 600 !important;
    padding: 16px 20px !important;
}

.preview-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-secondary);
    text-decoration: none;
    padding: 10px 16px;
    background: var(--bg-tertiary);
    border-radius: var(--radius-sm);
    transition: all 0.15s;
}

.preview-link:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.preview-link svg {
    width: 14px;
    height: 14px;
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
    gap: 16px;
    color: var(--text-secondary);
    font-size: 13px;
}

.save-bar-actions {
    display: flex;
    gap: 12px;
}

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
    animation: toastIn 0.3s ease;
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

@keyframes toastIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
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

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="pages.php?lang=<?= $current_lang ?>">Pages</a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span><?= $is_new ? 'New Page' : htmlspecialchars($page_data['title']) ?></span>
</div>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $is_new ? 'Create New Page' : 'Edit Page' ?></h1>
        <p class="page-subtitle">
            <?php if ($is_new): ?>
                Fill in the details to create a new standalone page
            <?php else: ?>
                Editing: /<?= htmlspecialchars($page_slug) ?>/
            <?php endif; ?>
        </p>
    </div>
    
    <?php if (!$is_new): ?>
    <a href="<?= htmlspecialchars($config['site_url'] ?? '') ?>/<?= htmlspecialchars($page_slug) ?>/" 
       target="_blank" class="preview-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            <polyline points="15 3 21 3 21 9"/>
            <line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
        View Page
    </a>
    <?php endif; ?>
</div>

<form method="POST" id="pageForm">
    <?= csrf_field() ?>
    <div class="page-editor">
        <!-- Main Content -->
        <div class="editor-main">
            <!-- Title -->
            <div class="editor-card">
                <div class="editor-card-body" style="padding: 0;">
                    <input type="text" name="title" id="titleInput"
                           class="form-input title-input" 
                           placeholder="Page Title"
                           value="<?= htmlspecialchars($page_data['title']) ?>"
                           required
                           oninput="updateSlugFromTitle()">
                </div>
            </div>
            
            <!-- Content -->
            <div class="editor-card">
                <div class="editor-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    Content
                </div>
                <div class="editor-card-body">
                    <textarea name="body" id="editor" class="markdown-editor"><?= htmlspecialchars($page_data['body']) ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="editor-sidebar">
            <!-- URL / Slug -->
            <div class="editor-card">
                <div class="editor-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                    </svg>
                    URL
                </div>
                <div class="editor-card-body">
                    <div class="slug-input-group">
                        <span class="slug-prefix">/</span>
                        <input type="text" name="slug" id="slugInput"
                               value="<?= htmlspecialchars($page_slug) ?>"
                               placeholder="page-slug"
                               pattern="[a-z0-9-]+"
                               <?= $is_new ? '' : '' ?>>
                        <span class="slug-prefix">/</span>
                    </div>
                    <small style="color: var(--text-muted); font-size: 11px; display: block; margin-top: 8px;">
                        Lowercase letters, numbers, and hyphens only
                    </small>
                </div>
            </div>
            
            <!-- SEO -->
            <div class="editor-card">
                <div class="editor-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    SEO
                </div>
                <div class="editor-card-body">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Meta Description</label>
                        <textarea name="description" class="form-input" rows="3"
                                  placeholder="Brief description for search engines (max 160 chars)"
                                  maxlength="160"><?= htmlspecialchars($page_data['description']) ?></textarea>
                        <small style="color: var(--text-muted); font-size: 11px;">
                            <span id="descCount"><?= strlen($page_data['description']) ?></span>/160 characters
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Settings -->
            <div class="editor-card">
                <div class="editor-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    Settings
                </div>
                <div class="editor-card-body">
                    <div class="form-group">
                        <label class="form-label">Template</label>
                        <select name="layout" class="form-input">
                            <option value="default" <?= $page_data['layout'] === 'default' ? 'selected' : '' ?>>Default</option>
                            <option value="contact" <?= $page_data['layout'] === 'contact' ? 'selected' : '' ?>>Contact Page</option>
                            <option value="full-width" <?= $page_data['layout'] === 'full-width' ? 'selected' : '' ?>>Full Width</option>
                            <option value="narrow" <?= $page_data['layout'] === 'narrow' ? 'selected' : '' ?>>Narrow Content</option>
                        </select>
                    </div>
                    
                    <label class="checkbox-field">
                        <input type="checkbox" name="draft" <?= $page_data['draft'] ? 'checked' : '' ?>>
                        <span>Save as draft (unpublished)</span>
                    </label>
                </div>
            </div>
            
            <?php if (!$is_new && count($config['languages']) > 1): ?>
            <!-- Translations -->
            <div class="editor-card">
                <div class="editor-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    Translations
                </div>
                <div class="editor-card-body">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php foreach ($config['languages'] as $lang => $lang_config): 
                            $lang_content_dir = $lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . ($lang_config['content_dir'] ?? 'content');
                            $lang_page_exists = file_exists($lang_content_dir . '/' . $page_slug . '/_index.md');
                            $is_current = $lang === $current_lang;
                        ?>
                        <a href="page-edit.php?page=<?= urlencode($page_slug) ?>&lang=<?= $lang ?>"
                           class="translation-item <?= $is_current ? 'active' : '' ?> <?= $lang_page_exists ? 'exists' : 'missing' ?>"
                           style="<?= $is_current ? 'border-color: var(--accent-primary);' : '' ?>">
                            <span class="flag" style="font-size: 20px;"><?= $lang_config['flag'] ?></span>
                            <span class="lang-name" style="font-size: 10px;"><?= $lang_config['name'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Save Bar -->
    <div class="save-bar">
        <div class="save-bar-info">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <span>
                <?php if ($is_new): ?>
                    Creating new page
                <?php else: ?>
                    Editing: <strong><?= htmlspecialchars($page_data['title']) ?></strong>
                <?php endif; ?>
            </span>
            <span>•</span>
            <span><?= $config['languages'][$current_lang]['flag'] ?> <?= $config['languages'][$current_lang]['name'] ?></span>
        </div>
        <div class="save-bar-actions">
            <a href="pages.php?lang=<?= $current_lang ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                <?= $is_new ? 'Create Page' : 'Save Changes' ?>
            </button>
        </div>
    </div>
</form>

<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
<script>
// Initialize EasyMDE
const easyMDE = new EasyMDE({
    element: document.getElementById('editor'),
    spellChecker: false,
    autosave: {
        enabled: <?= $is_new ? 'false' : 'true' ?>,
        uniqueId: 'page-<?= $page_slug ?: 'new' ?>-<?= $current_lang ?>',
        delay: 5000,
    },
    toolbar: [
        'bold', 'italic', 'heading', '|',
        'quote', 'unordered-list', 'ordered-list', '|',
        'link', 'image', '|',
        'preview', 'side-by-side', 'fullscreen', '|',
        'guide'
    ],
    status: ['lines', 'words'],
    minHeight: '400px',
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

// Update slug when title changes (only for new pages or empty slug)
function updateSlugFromTitle() {
    const titleInput = document.getElementById('titleInput');
    const slugInput = document.getElementById('slugInput');
    
    <?php if ($is_new): ?>
    // For new pages, always update slug from title
    slugInput.value = generateSlug(titleInput.value);
    <?php else: ?>
    // For existing pages, only update if slug is empty
    if (!slugInput.value) {
        slugInput.value = generateSlug(titleInput.value);
    }
    <?php endif; ?>
}

// Validate slug on input
document.getElementById('slugInput').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
});

// Description character counter
document.querySelector('textarea[name="description"]').addEventListener('input', function() {
    document.getElementById('descCount').textContent = this.value.length;
});

// Auto-hide toast messages
document.querySelectorAll('.toast-message').forEach(toast => {
    setTimeout(() => {
        toast.style.animation = 'toastIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
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

