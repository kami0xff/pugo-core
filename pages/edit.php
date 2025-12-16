<?php
/**
 * Hugo Admin - Edit Article
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$current_lang = $_GET['lang'] ?? 'en';
$file = $_GET['file'] ?? '';

if (!$file) {
    header('Location: articles.php');
    exit;
}

// Construct full path
$content_dir = $current_lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . $config['languages'][$current_lang]['content_dir'];
$file_path = $content_dir . '/' . $file;

// Check if file exists
if (!file_exists($file_path)) {
    $_SESSION['error'] = 'Article not found';
    header('Location: articles.php?lang=' . $current_lang);
    exit;
}

// Parse the file
$content = file_get_contents($file_path);
$parsed = parse_frontmatter($content);
$frontmatter = $parsed['frontmatter'];
$body = $parsed['body'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Build new frontmatter
    $new_frontmatter = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'author' => $_POST['author'] ?? 'XloveCam Team',
        'date' => $_POST['date'] ?? date('Y-m-d'),
        'lastmod' => date('Y-m-d'),
    ];
    
    // Optional fields
    if (!empty($_POST['image'])) {
        $new_frontmatter['image'] = $_POST['image'];
    }
    
    if (!empty($_POST['keywords'])) {
        $keywords = json_decode($_POST['keywords'], true);
        if ($keywords) $new_frontmatter['keywords'] = $keywords;
    }
    
    if (!empty($_POST['tags'])) {
        $tags = json_decode($_POST['tags'], true);
        if ($tags) $new_frontmatter['tags'] = $tags;
    }
    
    if (!empty($_POST['translationKey'])) {
        $new_frontmatter['translationKey'] = $_POST['translationKey'];
    }
    
    if (!empty($_POST['related'])) {
        $related = json_decode($_POST['related'], true);
        if ($related && is_array($related) && count($related) > 0) {
            $new_frontmatter['related'] = $related;
        }
    }
    
    if (!empty($_POST['weight']) && is_numeric($_POST['weight'])) {
        $new_frontmatter['weight'] = (int)$_POST['weight'];
    }
    
    if (isset($_POST['draft']) && $_POST['draft']) {
        $new_frontmatter['draft'] = true;
    }
    
    $new_body = $_POST['body'] ?? '';
    
    if (save_article($file_path, $new_frontmatter, $new_body)) {
        $_SESSION['success'] = 'Article saved successfully!';
        
        // Trigger Hugo build if requested
        if (isset($_POST['build']) && $_POST['build']) {
            $result = build_hugo();
            if ($result['success']) {
                $_SESSION['success'] .= ' Site rebuilt.';
            } else {
                $_SESSION['warning'] = 'Article saved, but Hugo build failed.';
            }
        }
        
        header('Location: edit.php?file=' . urlencode($file) . '&lang=' . $current_lang);
        exit;
    } else {
        $_SESSION['error'] = 'Failed to save article';
    }
}

$page_title = 'Edit: ' . ($frontmatter['title'] ?? basename($file, '.md'));

// Get translation status
$translation_key = $frontmatter['translationKey'] ?? null;
$translations = $translation_key ? get_translation_status($translation_key) : [];

// Parse path for section info
$path_parts = explode('/', $file);
$section = $path_parts[0] ?? '';
$category = count($path_parts) > 2 ? $path_parts[1] : null;

// Get section color
$sections_list = get_sections_with_counts($current_lang);
$section_color = $sections_list[$section]['color'] ?? '#666';
$section_name = $sections_list[$section]['name'] ?? ucfirst($section);

require __DIR__ . '/../includes/header.php';
?>

<style>
/* Editor Toolbar */
.editor-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-color);
    flex-wrap: wrap;
}

.toolbar-group {
    display: flex;
    align-items: center;
    gap: 4px;
}

.toolbar-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-right: 6px;
    font-weight: 600;
}

.toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 6px 8px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 4px;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.15s ease;
    font-family: inherit;
}

.toolbar-btn svg {
    width: 16px;
    height: 16px;
}

.toolbar-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
    border-color: var(--border-color);
}

.toolbar-btn:active {
    background: var(--bg-secondary);
}

.toolbar-divider {
    width: 1px;
    height: 24px;
    background: var(--border-color);
    margin: 0 4px;
}

.toolbar-spacer {
    flex: 1;
}

.toolbar-hugo-btn {
    background: rgba(225, 29, 72, 0.1);
    color: var(--accent-primary);
    border-color: rgba(225, 29, 72, 0.2);
}

.toolbar-hugo-btn:hover {
    background: rgba(225, 29, 72, 0.2);
    color: var(--accent-primary);
    border-color: rgba(225, 29, 72, 0.3);
}

.toolbar-help {
    text-decoration: none;
    color: var(--text-muted);
}

/* Editor with side-by-side preview */
.editor-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    min-height: 600px;
}

.editor-pane, .preview-pane {
    display: flex;
    flex-direction: column;
}

.editor-pane {
    border-right: 1px solid var(--border-color);
}

.editor-pane textarea {
    flex: 1;
    min-height: 550px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    line-height: 1.6;
    resize: none;
    border: none;
    border-radius: 0;
    padding: 20px;
    background: var(--bg-secondary);
}

.preview-pane {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    overflow: hidden;
}

.preview-header {
    background: var(--bg-secondary);
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
}

.preview-content {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
    color: var(--text-primary);
    line-height: 1.8;
}

.preview-content h1 { font-size: 28px; margin: 0 0 16px; }
.preview-content h2 { font-size: 22px; margin: 24px 0 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px; }
.preview-content h3 { font-size: 18px; margin: 20px 0 10px; }
.preview-content p { margin: 0 0 16px; }
.preview-content ul, .preview-content ol { margin: 0 0 16px; padding-left: 24px; }
.preview-content li { margin-bottom: 8px; }
.preview-content code { background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-size: 0.9em; }
.preview-content pre { background: var(--bg-secondary); padding: 16px; border-radius: 8px; overflow-x: auto; margin: 0 0 16px; }
.preview-content pre code { background: none; padding: 0; }
.preview-content blockquote { border-left: 4px solid var(--accent-primary); padding-left: 16px; margin: 0 0 16px; color: var(--text-secondary); }
.preview-content a { color: var(--accent-primary); }
.preview-content img { max-width: 100%; border-radius: 8px; margin: 16px 0; }
.preview-content hr { border: none; border-top: 1px solid var(--border-color); margin: 24px 0; }
.preview-content strong { color: var(--text-primary); }

/* Article info bar */
.article-info-bar {
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

.article-info-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.article-info-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
}

.article-info-value {
    font-size: 13px;
    font-weight: 500;
}

.article-info-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.article-info-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.article-info-tags .tag-pill {
    padding: 2px 8px;
    background: var(--bg-tertiary);
    border-radius: 12px;
    font-size: 11px;
    color: var(--text-secondary);
}

@media (max-width: 1200px) {
    .editor-container {
        grid-template-columns: 1fr;
    }
    .preview-pane {
        display: none;
    }
}

/* Related Articles */
.related-articles-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.related-article-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    font-size: 13px;
}

.related-article-path {
    font-family: 'JetBrains Mono', monospace;
    color: var(--text-secondary);
}

.related-remove {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 18px;
    padding: 0 4px;
    line-height: 1;
}

.related-remove:hover {
    color: #e11d48;
}

/* Shortcode Modal Styles */
.shortcode-modal .modal {
    max-width: 600px;
}

.shortcode-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.shortcode-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.shortcode-field label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
}

.shortcode-field label .required {
    color: var(--accent-primary);
}

.shortcode-field .field-hint {
    font-size: 11px;
    color: var(--text-muted);
}

.shortcode-field input,
.shortcode-field select,
.shortcode-field textarea {
    padding: 10px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: var(--bg-secondary);
    color: var(--text-primary);
    font-size: 14px;
}

.shortcode-field input:focus,
.shortcode-field select:focus,
.shortcode-field textarea:focus {
    outline: none;
    border-color: var(--accent-primary);
}

.shortcode-preview {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 12px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: var(--accent-primary);
    word-break: break-all;
    margin-top: 16px;
}

.shortcode-preview-label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 8px;
    font-weight: 600;
}

.shortcode-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--border-color);
}

.media-picker-btn {
    padding: 8px 12px;
    background: var(--bg-tertiary);
    border: 1px dashed var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}

.media-picker-btn:hover {
    border-color: var(--accent-primary);
    color: var(--accent-primary);
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-weight: normal;
}

.checkbox-group input[type="checkbox"] {
    width: 16px;
    height: 16px;
}

@media (max-width: 900px) {
    .editor-toolbar {
        gap: 4px;
        padding: 8px 12px;
    }
    .toolbar-label {
        display: none;
    }
    .toolbar-hugo-btn span:last-child {
        display: none;
    }
}
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="articles.php?lang=<?= $current_lang ?>">Articles</a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <a href="articles.php?section=<?= $section ?>&lang=<?= $current_lang ?>"><?= ucfirst($section) ?></a>
    <?php if ($category): ?>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span><?= ucwords(str_replace('-', ' ', $category)) ?></span>
    <?php endif; ?>
</div>

<!-- Article Info Bar -->
<div class="article-info-bar">
    <div class="article-info-item">
        <span class="article-info-label">Section</span>
        <span class="article-info-badge" style="background: <?= $section_color ?>20; color: <?= $section_color ?>;">
            <?= $section_name ?>
        </span>
    </div>
    
    <?php if ($category): ?>
    <div class="article-info-item">
        <span class="article-info-label">Category</span>
        <span class="article-info-value"><?= ucwords(str_replace('-', ' ', $category)) ?></span>
    </div>
    <?php endif; ?>
    
    <div class="article-info-item">
        <span class="article-info-label">Language</span>
        <span class="article-info-value">
            <?= $config['languages'][$current_lang]['flag'] ?> 
            <?= $config['languages'][$current_lang]['name'] ?>
        </span>
    </div>
    
    <div class="article-info-item">
        <span class="article-info-label">File</span>
        <span class="article-info-value" style="font-family: monospace; font-size: 12px; color: var(--text-secondary);">
            <?= htmlspecialchars($file) ?>
        </span>
    </div>
    
    <?php $tags = $frontmatter['tags'] ?? []; if (!empty($tags)): ?>
    <div class="article-info-item" style="margin-left: auto;">
        <span class="article-info-label">Tags</span>
        <div class="article-info-tags">
            <?php foreach ($tags as $tag): ?>
            <span class="tag-pill"><?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($frontmatter['draft'])): ?>
    <div class="article-info-item">
        <span class="article-info-badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">
            📝 Draft
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($frontmatter['title'] ?? 'Untitled') ?></h1>
        <p class="page-subtitle">
            Last modified: <?= time_ago(filemtime($file_path)) ?>
            <?php if (!empty($frontmatter['author'])): ?>
                · By <?= htmlspecialchars($frontmatter['author']) ?>
            <?php endif; ?>
            <?php if (!empty($frontmatter['translationKey'])): ?>
                · Key: <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px; font-size: 11px;"><?= htmlspecialchars($frontmatter['translationKey']) ?></code>
            <?php endif; ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px;">
        <button type="button" class="btn btn-primary" onclick="publishChanges()" id="publishBtn" title="Commit & push to trigger CI/CD build">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M22 2L11 13"/>
                <path d="M22 2l-7 20-4-9-9-4 20-7z"/>
            </svg>
            <span id="publishText">Publish</span>
        </button>
        <button type="button" class="btn btn-secondary" onclick="rebuildSite()" id="rebuildBtn" title="Local rebuild (preview)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M23 4v6h-6"/>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
            <span id="rebuildText">Rebuild</span>
        </button>
        <a href="<?= $config['site_url'] ?>/<?= $current_lang !== 'en' ? $current_lang . '/' : '' ?><?= str_replace('.md', '/', $file) ?>" 
           target="_blank" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                <polyline points="15 3 21 3 21 9"/>
                <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            View
        </a>
    </div>
</div>

<!-- Language Tabs -->
<div class="lang-tabs" style="margin-bottom: 24px;">
    <?php foreach ($config['languages'] as $lang => $lang_config): 
        $lang_exists = $lang === $current_lang || (isset($translations[$lang]) && $translations[$lang]['exists']);
        $lang_path = isset($translations[$lang]['path']) ? str_replace(HUGO_ROOT . '/', '', $translations[$lang]['path']) : null;
    ?>
    <?php if ($lang === $current_lang): ?>
    <span class="lang-tab active">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
    </span>
    <?php elseif ($lang_exists && $lang_path): ?>
    <a href="edit.php?file=<?= urlencode(basename($lang_path)) ?>&lang=<?= $lang ?>" class="lang-tab">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
    </a>
    <?php else: ?>
    <a href="new.php?translate_from=<?= urlencode($file) ?>&source_lang=<?= $current_lang ?>&target_lang=<?= $lang ?>" 
       class="lang-tab" style="opacity: 0.5;">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
        <span style="font-size: 10px;">+ Create</span>
    </a>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

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
<form method="POST" id="editForm">
    <!-- Title & Description -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="form-group">
            <label class="form-label">Title *</label>
            <input type="text" name="title" id="titleInput" class="form-input" 
                   value="<?= htmlspecialchars($frontmatter['title'] ?? '') ?>" required
                   style="font-size: 18px; font-weight: 600;">
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Description * <span style="font-weight: normal; color: var(--text-muted);">(max 160 chars for SEO)</span></label>
            <textarea name="description" id="descInput" class="form-input" rows="2" required maxlength="200"><?= htmlspecialchars($frontmatter['description'] ?? '') ?></textarea>
            <div style="text-align: right; font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                <span id="descCount"><?= strlen($frontmatter['description'] ?? '') ?></span>/160
            </div>
        </div>
    </div>
    
    <!-- Editor with Preview -->
    <div class="card" style="margin-bottom: 24px; padding: 0; overflow: hidden;">
        <!-- Editor Toolbar -->
        <div class="editor-toolbar">
            <div class="toolbar-group">
                <span class="toolbar-label">Format</span>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('bold')" title="Bold (Ctrl+B)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('italic')" title="Italic (Ctrl+I)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('strikethrough')" title="Strikethrough">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4H9a3 3 0 0 0 0 6h6"/><line x1="4" y1="12" x2="20" y2="12"/><path d="M8 20h7a3 3 0 0 0 0-6H6"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('code')" title="Inline Code">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                </button>
            </div>
            
            <div class="toolbar-divider"></div>
            
            <div class="toolbar-group">
                <span class="toolbar-label">Headings</span>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('h1')" title="Heading 1">H1</button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('h2')" title="Heading 2">H2</button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('h3')" title="Heading 3">H3</button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('h4')" title="Heading 4">H4</button>
            </div>
            
            <div class="toolbar-divider"></div>
            
            <div class="toolbar-group">
                <span class="toolbar-label">Lists</span>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('ul')" title="Bullet List">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1" fill="currentColor"/><circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="4" cy="18" r="1" fill="currentColor"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('ol')" title="Numbered List">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><text x="3" y="8" font-size="8" fill="currentColor">1</text><text x="3" y="14" font-size="8" fill="currentColor">2</text><text x="3" y="20" font-size="8" fill="currentColor">3</text></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('checklist')" title="Checklist">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="4" height="4" rx="1"/><path d="M4 9l1.5 1.5L8 7"/><line x1="12" y1="7" x2="21" y2="7"/><rect x="3" y="15" width="4" height="4" rx="1"/><line x1="12" y1="17" x2="21" y2="17"/></svg>
                </button>
            </div>
            
            <div class="toolbar-divider"></div>
            
            <div class="toolbar-group">
                <span class="toolbar-label">Insert</span>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('link')" title="Insert Link (Ctrl+K)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('blockquote')" title="Blockquote">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V21"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('codeblock')" title="Code Block">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="9 11 7 13 9 15"/><polyline points="15 11 17 13 15 15"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('table')" title="Table">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                </button>
                <button type="button" class="toolbar-btn" onclick="insertMarkdown('hr')" title="Horizontal Rule">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" stroke-width="3"/></svg>
                </button>
            </div>
            
            <div class="toolbar-divider"></div>
            
            <div class="toolbar-group toolbar-hugo">
                <span class="toolbar-label">🎨 Hugo Shortcodes</span>
                <button type="button" class="toolbar-btn toolbar-hugo-btn" onclick="openShortcodeModal('screenshot')" title="Insert Screenshot">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    Screenshot
                </button>
                <button type="button" class="toolbar-btn toolbar-hugo-btn" onclick="openShortcodeModal('img')" title="Insert Image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    Image
                </button>
                <button type="button" class="toolbar-btn toolbar-hugo-btn" onclick="openShortcodeModal('video')" title="Insert Video">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Video
                </button>
                <button type="button" class="toolbar-btn toolbar-hugo-btn" onclick="openShortcodeModal('tutorial')" title="Embed Tutorial">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                    Tutorial
                </button>
            </div>
            
            <div class="toolbar-spacer"></div>
            
            <a href="https://www.markdownguide.org/cheat-sheet/" target="_blank" class="toolbar-btn toolbar-help" title="Markdown Help">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </a>
        </div>
        
        <div class="editor-container">
            <!-- Editor Pane -->
            <div class="editor-pane">
                <textarea name="body" id="editor" class="form-input" 
                          placeholder="Write your article content here..."><?= htmlspecialchars($body) ?></textarea>
            </div>
            
            <!-- Preview Pane -->
            <div class="preview-pane">
                <div class="preview-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
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
    
    <!-- Sidebar Settings -->
    <div class="grid grid-3" style="gap: 24px;">
        <!-- Publishing -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">📅 Publishing</h3>
            
            <div class="form-group">
                <label class="form-label">Author</label>
                <input type="text" name="author" class="form-input" 
                       value="<?= htmlspecialchars($frontmatter['author'] ?? 'XloveCam Team') ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Publish Date</label>
                <input type="date" name="date" class="form-input" 
                       value="<?= $frontmatter['date'] ?? date('Y-m-d') ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Weight (Sort Order)</label>
                <input type="number" name="weight" class="form-input" 
                       value="<?= htmlspecialchars($frontmatter['weight'] ?? '') ?>"
                       placeholder="Lower = higher position">
                <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">
                    Lower numbers appear first in lists
                </small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="draft" value="1" <?= !empty($frontmatter['draft']) ? 'checked' : '' ?>
                           style="width: 18px; height: 18px;">
                    <span>Save as draft</span>
                </label>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="build" value="1"
                           style="width: 18px; height: 18px;">
                    <span>Rebuild site after save</span>
                </label>
            </div>
        </div>
        
        <!-- Featured Image -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">🖼️ Featured Image</h3>
            
            <div class="form-group">
                <input type="text" name="image" id="imageInput" class="form-input" 
                       value="<?= htmlspecialchars($frontmatter['image'] ?? '') ?>"
                       placeholder="/images/articles/...">
            </div>
            
            <div id="imagePreview" style="margin-top: 12px; display: <?= !empty($frontmatter['image']) ? 'block' : 'none' ?>;">
                <img src="<?= htmlspecialchars($frontmatter['image'] ?? '') ?>" 
                     style="max-width: 100%; border-radius: 8px; border: 1px solid var(--border-color);"
                     onerror="this.style.display='none'">
            </div>
            
            <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 12px; width: 100%;"
                    onclick="openMediaBrowser()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                Browse Media
            </button>
        </div>
        
        <!-- SEO & Meta -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">🔍 SEO & Meta</h3>
            
            <div class="form-group">
                <label class="form-label">Translation Key</label>
                <input type="text" name="translationKey" class="form-input" 
                       value="<?= htmlspecialchars($frontmatter['translationKey'] ?? '') ?>"
                       placeholder="unique-article-key">
                <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">
                    Links translations across languages
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Keywords</label>
                <div class="tags-container" id="keywordsContainer">
                    <?php 
                    $keywords = $frontmatter['keywords'] ?? [];
                    foreach ($keywords as $keyword): 
                    ?>
                    <span class="tag" data-value="<?= htmlspecialchars($keyword) ?>">
                        <?= htmlspecialchars($keyword) ?>
                        <button type="button" onclick="removeTag(this)">&times;</button>
                    </span>
                    <?php endforeach; ?>
                    <input type="text" class="tags-input" placeholder="Add keyword...">
                    <input type="hidden" name="keywords" value='<?= json_encode($keywords) ?>'>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Tags</label>
                <div class="tags-container" id="tagsContainer">
                    <?php 
                    $tags = $frontmatter['tags'] ?? [];
                    foreach ($tags as $tag): 
                    ?>
                    <span class="tag" data-value="<?= htmlspecialchars($tag) ?>">
                        <?= htmlspecialchars($tag) ?>
                        <button type="button" onclick="removeTag(this)">&times;</button>
                    </span>
                    <?php endforeach; ?>
                    <input type="text" class="tags-input" placeholder="Add tag...">
                    <input type="hidden" name="tags" value='<?= json_encode($tags) ?>'>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Articles -->
    <div class="card" style="margin-top: 24px;">
        <h3 class="card-title" style="margin-bottom: 16px;">🔗 Related Articles</h3>
        <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">
            Link to related articles that readers might find helpful. These will appear at the end of the article.
        </p>
        
        <div class="related-articles-container" id="relatedContainer">
            <?php 
            $related = $frontmatter['related'] ?? [];
            foreach ($related as $rel): 
            ?>
            <div class="related-article-item" data-slug="<?= htmlspecialchars($rel) ?>">
                <span class="related-article-path"><?= htmlspecialchars($rel) ?></span>
                <button type="button" class="related-remove" onclick="removeRelated(this)">×</button>
            </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="related" id="relatedInput" value='<?= json_encode($related) ?>'>
        
        <div class="form-group" style="margin-top: 16px; margin-bottom: 0;">
            <label class="form-label">Add Related Article</label>
            <select id="relatedSelect" class="form-input" onchange="addRelatedArticle(this)">
                <option value="">-- Select an article --</option>
                <?php 
                $all_articles = get_all_articles_for_selection($current_lang);
                $current_sections = [];
                foreach ($all_articles as $art): 
                    // Skip current article
                    if ($art['relative_path'] === $file) continue;
                    
                    // Group by section
                    if (!isset($current_sections[$art['section']])) {
                        if (!empty($current_sections)) {
                            echo '</optgroup>';
                        }
                        echo '<optgroup label="' . ucfirst($art['section']) . '">';
                        $current_sections[$art['section']] = true;
                    }
                ?>
                <option value="<?= htmlspecialchars($art['slug']) ?>"><?= htmlspecialchars($art['title']) ?></option>
                <?php endforeach; ?>
                <?php if (!empty($current_sections)): ?>
                </optgroup>
                <?php endif; ?>
            </select>
        </div>
    </div>
    
    <!-- Save Button (Sticky) -->
    <div style="position: sticky; bottom: 24px; margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end; background: var(--bg-primary); padding: 16px 0;">
        <a href="articles.php?lang=<?= $current_lang ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
            </svg>
            Save Article
        </button>
    </div>
</form>

<!-- Media Browser Modal -->
<div id="mediaBrowserModal" class="modal-overlay">
    <div class="modal" style="max-width: 900px;">
        <div class="modal-header">
            <h2 class="modal-title">Select Image</h2>
            <button type="button" class="modal-close" onclick="closeModal('mediaBrowserModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div id="mediaBrowserContent" style="min-height: 300px;">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<!-- Screenshot Shortcode Modal -->
<div id="screenshotModal" class="modal-overlay shortcode-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">📸 Insert Screenshot</h2>
            <button type="button" class="modal-close" onclick="closeModal('screenshotModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="shortcode-form">
            <div class="shortcode-field">
                <label>Image Filename <span class="required">*</span></label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="screenshot_src" placeholder="step-1-signup.png" style="flex: 1;">
                    <button type="button" class="media-picker-btn" onclick="openShortcodeMediaPicker('screenshot_src')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Browse
                    </button>
                </div>
                <span class="field-hint">Image is loaded from: /images/articles/<?= htmlspecialchars($section) ?>/<?= htmlspecialchars($category ?? '') ?><?= $category ? '/' : '' ?><?= basename($file, '.md') ?>/</span>
            </div>
            <div class="shortcode-field">
                <label>Alt Text <span class="required">*</span></label>
                <input type="text" id="screenshot_alt" placeholder="Describe what the screenshot shows">
            </div>
            <div class="shortcode-field">
                <label>Step Number</label>
                <input type="number" id="screenshot_step" placeholder="1, 2, 3..." min="1">
                <span class="field-hint">Shows a step badge on the screenshot</span>
            </div>
            <div class="shortcode-field">
                <label>Caption</label>
                <input type="text" id="screenshot_caption" placeholder="Optional caption below image">
            </div>
            <div class="shortcode-field">
                <label>Highlight Position</label>
                <select id="screenshot_highlight">
                    <option value="">None</option>
                    <option value="top-left">Top Left</option>
                    <option value="top-right">Top Right</option>
                    <option value="center">Center</option>
                    <option value="bottom-left">Bottom Left</option>
                    <option value="bottom-right">Bottom Right</option>
                </select>
            </div>
            <div class="shortcode-preview">
                <div class="shortcode-preview-label">Preview</div>
                <code id="screenshot_preview">{{&lt; screenshot src="" alt="" &gt;}}</code>
            </div>
            <div class="shortcode-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('screenshotModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="insertShortcode('screenshot')">Insert</button>
            </div>
        </div>
    </div>
</div>

<!-- Image Shortcode Modal -->
<div id="imgModal" class="modal-overlay shortcode-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">🖼️ Insert Image</h2>
            <button type="button" class="modal-close" onclick="closeModal('imgModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="shortcode-form">
            <div class="shortcode-field">
                <label>Image Filename <span class="required">*</span></label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="img_src" placeholder="diagram.png" style="flex: 1;">
                    <button type="button" class="media-picker-btn" onclick="openShortcodeMediaPicker('img_src')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        Browse
                    </button>
                </div>
                <span class="field-hint">Image is loaded from: /images/articles/<?= htmlspecialchars($section) ?>/<?= htmlspecialchars($category ?? '') ?><?= $category ? '/' : '' ?><?= basename($file, '.md') ?>/</span>
            </div>
            <div class="shortcode-field">
                <label>Alt Text <span class="required">*</span></label>
                <input type="text" id="img_alt" placeholder="Describe the image">
            </div>
            <div class="shortcode-field">
                <label>Caption</label>
                <input type="text" id="img_caption" placeholder="Optional caption below image">
            </div>
            <div class="shortcode-field">
                <label>Width (px)</label>
                <input type="number" id="img_width" placeholder="e.g. 600">
                <span class="field-hint">Leave empty for full width</span>
            </div>
            <div class="shortcode-preview">
                <div class="shortcode-preview-label">Preview</div>
                <code id="img_preview">{{&lt; img src="" alt="" &gt;}}</code>
            </div>
            <div class="shortcode-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('imgModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="insertShortcode('img')">Insert</button>
            </div>
        </div>
    </div>
</div>

<!-- Video Shortcode Modal -->
<div id="videoModal" class="modal-overlay shortcode-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">🎬 Insert Video</h2>
            <button type="button" class="modal-close" onclick="closeModal('videoModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="shortcode-form">
            <div class="shortcode-field">
                <label>Video Filename or URL <span class="required">*</span></label>
                <input type="text" id="video_src" placeholder="tutorial.mp4 or https://...">
                <span class="field-hint">Local videos from: /videos/articles/<?= htmlspecialchars($section) ?>/<?= htmlspecialchars($category ?? '') ?><?= $category ? '/' : '' ?><?= basename($file, '.md') ?>/</span>
            </div>
            <div class="shortcode-field">
                <label>
                    <input type="checkbox" id="video_external" onchange="updateVideoPreview()">
                    External URL (not hosted locally)
                </label>
            </div>
            <div class="shortcode-field">
                <label>Caption</label>
                <input type="text" id="video_caption" placeholder="Optional caption below video">
            </div>
            <div class="shortcode-field">
                <label>Poster Image</label>
                <input type="text" id="video_poster" placeholder="thumbnail.png">
                <span class="field-hint">Thumbnail shown before video plays</span>
            </div>
            <div class="shortcode-field">
                <label>Playback Options</label>
                <div class="checkbox-group">
                    <label><input type="checkbox" id="video_autoplay" onchange="updateVideoPreview()"> Autoplay</label>
                    <label><input type="checkbox" id="video_loop" onchange="updateVideoPreview()"> Loop</label>
                    <label><input type="checkbox" id="video_muted" onchange="updateVideoPreview()"> Muted</label>
                </div>
            </div>
            <div class="shortcode-preview">
                <div class="shortcode-preview-label">Preview</div>
                <code id="video_preview">{{&lt; video src="" &gt;}}</code>
            </div>
            <div class="shortcode-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('videoModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="insertShortcode('video')">Insert</button>
            </div>
        </div>
    </div>
</div>

<!-- Tutorial Shortcode Modal -->
<div id="tutorialModal" class="modal-overlay shortcode-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">📺 Embed Tutorial</h2>
            <button type="button" class="modal-close" onclick="closeModal('tutorialModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="shortcode-form">
            <div class="shortcode-field">
                <label>Tutorial Title <span class="required">*</span></label>
                <input type="text" id="tutorial_title" placeholder="Getting Started for New Users">
                <span class="field-hint">Must match exactly a tutorial title from data/all_tutorials.yaml</span>
            </div>
            <div class="shortcode-preview">
                <div class="shortcode-preview-label">Preview</div>
                <code id="tutorial_preview">{{&lt; tutorial title="" &gt;}}</code>
            </div>
            <div class="shortcode-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('tutorialModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="insertShortcode('tutorial')">Insert</button>
            </div>
        </div>
    </div>
</div>

<!-- Shortcode Media Picker Modal -->
<div id="shortcodeMediaModal" class="modal-overlay">
    <div class="modal" style="max-width: 900px;">
        <div class="modal-header">
            <h2 class="modal-title">Select Media File</h2>
            <button type="button" class="modal-close" onclick="closeModal('shortcodeMediaModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div id="shortcodeMediaContent" style="min-height: 300px;">
            <div class="loading"><div class="spinner"></div></div>
        </div>
    </div>
</div>

<!-- Include marked.js for Markdown parsing -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<script>
// Description character counter
document.getElementById('descInput').addEventListener('input', function() {
    document.getElementById('descCount').textContent = this.value.length;
});

// Live Markdown Preview
const editor = document.getElementById('editor');
const preview = document.getElementById('previewContent');
const titleInput = document.getElementById('titleInput');

function updatePreview() {
    const title = titleInput.value;
    let content = editor.value;
    
    // Configure marked
    marked.setOptions({
        breaks: true,
        gfm: true,
        headerIds: false
    });
    
    // Replace Hugo shortcodes with placeholder HTML for preview
    content = content.replace(/\{\{<\s*screenshot\s+([^>]+)\s*>\}\}/g, (match, attrs) => {
        const src = attrs.match(/src="([^"]+)"/)?.[1] || '';
        const alt = attrs.match(/alt="([^"]+)"/)?.[1] || '';
        const step = attrs.match(/step="([^"]+)"/)?.[1] || '';
        return `<div style="background: #1a1a1a; border: 1px dashed #e11d48; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
            <span style="background: #e11d48; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-bottom: 8px; display: inline-block;">📸 Screenshot${step ? ' - Step ' + step : ''}</span>
            <div style="font-family: monospace; font-size: 12px; color: #888; margin-top: 8px;">${src}</div>
            <div style="font-size: 13px; color: #aaa; margin-top: 4px;">${alt}</div>
        </div>`;
    });
    
    content = content.replace(/\{\{<\s*img\s+([^>]+)\s*>\}\}/g, (match, attrs) => {
        const src = attrs.match(/src="([^"]+)"/)?.[1] || '';
        const alt = attrs.match(/alt="([^"]+)"/)?.[1] || '';
        return `<div style="background: #1a1a1a; border: 1px dashed #3b82f6; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
            <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">🖼️ Image</span>
            <div style="font-family: monospace; font-size: 12px; color: #888; margin-top: 8px;">${src}</div>
            <div style="font-size: 13px; color: #aaa; margin-top: 4px;">${alt}</div>
        </div>`;
    });
    
    content = content.replace(/\{\{<\s*video\s+([^>]+)\s*>\}\}/g, (match, attrs) => {
        const src = attrs.match(/src="([^"]+)"/)?.[1] || '';
        return `<div style="background: #1a1a1a; border: 1px dashed #10b981; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
            <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">🎬 Video</span>
            <div style="font-family: monospace; font-size: 12px; color: #888; margin-top: 8px;">${src}</div>
        </div>`;
    });
    
    content = content.replace(/\{\{<\s*tutorial\s+([^>]+)\s*>\}\}/g, (match, attrs) => {
        const title = attrs.match(/title="([^"]+)"/)?.[1] || '';
        return `<div style="background: #1a1a1a; border: 1px dashed #f59e0b; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
            <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">📺 Tutorial Embed</span>
            <div style="font-size: 14px; color: #fff; margin-top: 8px;">${title}</div>
        </div>`;
    });
    
    let html = '';
    if (title) {
        html += '<h1>' + escapeHtml(title) + '</h1>';
    }
    if (content) {
        html += marked.parse(content);
    }
    
    preview.innerHTML = html || '<p style="color: var(--text-muted);">Start typing to see preview...</p>';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Debounce function for better performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

const debouncedPreview = debounce(updatePreview, 150);

editor.addEventListener('input', debouncedPreview);
titleInput.addEventListener('input', debouncedPreview);

// Initial preview
updatePreview();

// ============================================
// MARKDOWN TOOLBAR FUNCTIONS
// ============================================

function insertMarkdown(type) {
    const textarea = editor;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    let insertion = '';
    let cursorOffset = 0;
    
    switch(type) {
        case 'bold':
            insertion = `**${selectedText || 'bold text'}**`;
            cursorOffset = selectedText ? insertion.length : 2;
            break;
        case 'italic':
            insertion = `*${selectedText || 'italic text'}*`;
            cursorOffset = selectedText ? insertion.length : 1;
            break;
        case 'strikethrough':
            insertion = `~~${selectedText || 'strikethrough'}~~`;
            cursorOffset = selectedText ? insertion.length : 2;
            break;
        case 'code':
            insertion = `\`${selectedText || 'code'}\``;
            cursorOffset = selectedText ? insertion.length : 1;
            break;
        case 'h1':
            insertion = `# ${selectedText || 'Heading 1'}`;
            cursorOffset = 2;
            break;
        case 'h2':
            insertion = `## ${selectedText || 'Heading 2'}`;
            cursorOffset = 3;
            break;
        case 'h3':
            insertion = `### ${selectedText || 'Heading 3'}`;
            cursorOffset = 4;
            break;
        case 'h4':
            insertion = `#### ${selectedText || 'Heading 4'}`;
            cursorOffset = 5;
            break;
        case 'ul':
            insertion = selectedText 
                ? selectedText.split('\n').map(line => `- ${line}`).join('\n')
                : '- List item\n- Another item\n- Third item';
            cursorOffset = 2;
            break;
        case 'ol':
            insertion = selectedText 
                ? selectedText.split('\n').map((line, i) => `${i+1}. ${line}`).join('\n')
                : '1. First item\n2. Second item\n3. Third item';
            cursorOffset = 3;
            break;
        case 'checklist':
            insertion = selectedText 
                ? selectedText.split('\n').map(line => `- [ ] ${line}`).join('\n')
                : '- [ ] Todo item\n- [ ] Another task\n- [x] Completed task';
            cursorOffset = 6;
            break;
        case 'link':
            if (selectedText) {
                insertion = `[${selectedText}](url)`;
                cursorOffset = insertion.length - 1;
            } else {
                insertion = '[link text](https://example.com)';
                cursorOffset = 1;
            }
            break;
        case 'blockquote':
            insertion = selectedText 
                ? selectedText.split('\n').map(line => `> ${line}`).join('\n')
                : '> Quote text here';
            cursorOffset = 2;
            break;
        case 'codeblock':
            insertion = '```\n' + (selectedText || 'code here') + '\n```';
            cursorOffset = 4;
            break;
        case 'table':
            insertion = '| Header 1 | Header 2 | Header 3 |\n|----------|----------|----------|\n| Cell 1   | Cell 2   | Cell 3   |\n| Cell 4   | Cell 5   | Cell 6   |';
            cursorOffset = 2;
            break;
        case 'hr':
            insertion = '\n---\n';
            cursorOffset = insertion.length;
            break;
    }
    
    // Insert the text
    textarea.value = textarea.value.substring(0, start) + insertion + textarea.value.substring(end);
    
    // Set cursor position
    if (selectedText) {
        textarea.selectionStart = start;
        textarea.selectionEnd = start + insertion.length;
    } else {
        textarea.selectionStart = textarea.selectionEnd = start + cursorOffset;
    }
    
    textarea.focus();
    updatePreview();
}

// Keyboard shortcuts
editor.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key.toLowerCase()) {
            case 'b':
                e.preventDefault();
                insertMarkdown('bold');
                break;
            case 'i':
                e.preventDefault();
                insertMarkdown('italic');
                break;
            case 'k':
                e.preventDefault();
                insertMarkdown('link');
                break;
        }
    }
    
    // Tab key support
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 4;
        updatePreview();
    }
});

// ============================================
// HUGO SHORTCODE FUNCTIONS
// ============================================

let currentShortcodeTarget = null;

function openShortcodeModal(type) {
    // Clear previous values
    document.querySelectorAll(`#${type}Modal input, #${type}Modal select, #${type}Modal textarea`).forEach(el => {
        if (el.type === 'checkbox') el.checked = false;
        else el.value = '';
    });
    
    // Update preview
    updateShortcodePreview(type);
    
    // Add live preview updates
    document.querySelectorAll(`#${type}Modal input, #${type}Modal select`).forEach(el => {
        el.addEventListener('input', () => updateShortcodePreview(type));
    });
    
    openModal(`${type}Modal`);
}

function updateShortcodePreview(type) {
    let preview = '';
    
    switch(type) {
        case 'screenshot':
            preview = buildScreenshotShortcode();
            break;
        case 'img':
            preview = buildImgShortcode();
            break;
        case 'video':
            preview = buildVideoShortcode();
            break;
        case 'tutorial':
            preview = buildTutorialShortcode();
            break;
    }
    
    document.getElementById(`${type}_preview`).textContent = preview;
}

function buildScreenshotShortcode() {
    const src = document.getElementById('screenshot_src').value;
    const alt = document.getElementById('screenshot_alt').value;
    const step = document.getElementById('screenshot_step').value;
    const caption = document.getElementById('screenshot_caption').value;
    const highlight = document.getElementById('screenshot_highlight').value;
    
    let code = `{{< screenshot src="${src}" alt="${alt}"`;
    if (step) code += ` step="${step}"`;
    if (caption) code += ` caption="${caption}"`;
    if (highlight) code += ` highlight="${highlight}"`;
    code += ` >}}`;
    
    return code;
}

function buildImgShortcode() {
    const src = document.getElementById('img_src').value;
    const alt = document.getElementById('img_alt').value;
    const caption = document.getElementById('img_caption').value;
    const width = document.getElementById('img_width').value;
    
    let code = `{{< img src="${src}" alt="${alt}"`;
    if (caption) code += ` caption="${caption}"`;
    if (width) code += ` width="${width}"`;
    code += ` >}}`;
    
    return code;
}

function buildVideoShortcode() {
    const src = document.getElementById('video_src').value;
    const external = document.getElementById('video_external').checked;
    const caption = document.getElementById('video_caption').value;
    const poster = document.getElementById('video_poster').value;
    const autoplay = document.getElementById('video_autoplay').checked;
    const loop = document.getElementById('video_loop').checked;
    const muted = document.getElementById('video_muted').checked;
    
    let code = `{{< video src="${src}"`;
    if (external) code += ` external="true"`;
    if (caption) code += ` caption="${caption}"`;
    if (poster) code += ` poster="${poster}"`;
    if (autoplay) code += ` autoplay="true"`;
    if (loop) code += ` loop="true"`;
    if (muted) code += ` muted="true"`;
    code += ` >}}`;
    
    return code;
}

function buildTutorialShortcode() {
    const title = document.getElementById('tutorial_title').value;
    return `{{< tutorial title="${title}" >}}`;
}

function updateVideoPreview() {
    updateShortcodePreview('video');
}

function insertShortcode(type) {
    let code = '';
    
    switch(type) {
        case 'screenshot':
            code = buildScreenshotShortcode();
            break;
        case 'img':
            code = buildImgShortcode();
            break;
        case 'video':
            code = buildVideoShortcode();
            break;
        case 'tutorial':
            code = buildTutorialShortcode();
            break;
    }
    
    // Insert at cursor position
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    editor.value = editor.value.substring(0, start) + code + editor.value.substring(end);
    editor.selectionStart = editor.selectionEnd = start + code.length;
    
    closeModal(`${type}Modal`);
    editor.focus();
    updatePreview();
}

// Media picker for shortcodes
function openShortcodeMediaPicker(targetInput) {
    currentShortcodeTarget = targetInput;
    openModal('shortcodeMediaModal');
    loadShortcodeMedia('articles/<?= htmlspecialchars($section) ?>/<?= htmlspecialchars($category ?? '') ?><?= $category ? '/' : '' ?><?= basename($file, '.md') ?>');
}

function loadShortcodeMedia(path) {
    const content = document.getElementById('shortcodeMediaContent');
    content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    
    fetch('api.php?action=media&path=' + encodeURIComponent(path))
        .then(r => r.json())
        .then(data => {
            let html = '<div class="breadcrumb" style="margin-bottom: 16px;">';
            html += '<a href="#" onclick="loadShortcodeMedia(\'\'); return false;">images</a>';
            
            if (path) {
                const parts = path.split('/');
                let currentPath = '';
                parts.forEach((part, i) => {
                    if (!part) return;
                    currentPath += (currentPath ? '/' : '') + part;
                    html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; opacity: 0.5;"><polyline points="9 18 15 12 9 6"/></svg>';
                    html += '<a href="#" onclick="loadShortcodeMedia(\'' + currentPath + '\'); return false;">' + part + '</a>';
                });
            }
            html += '</div>';
            
            html += '<div class="media-grid">';
            
            // Directories
            data.directories.forEach(dir => {
                html += `
                    <div class="media-item media-folder" onclick="loadShortcodeMedia('${dir.path}')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>${dir.name}</span>
                    </div>
                `;
            });
            
            // Files
            data.files.forEach(file => {
                // Extract just the filename for shortcodes
                const filename = file.name;
                html += `
                    <div class="media-item" onclick="selectShortcodeMedia('${filename}')">
                        <img src="${file.path}" alt="${file.name}">
                        <div class="media-item-name">${file.name}</div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            if (data.files.length === 0 && data.directories.length === 0) {
                html += '<div class="empty-state"><p>No media in this folder</p></div>';
            }
            
            content.innerHTML = html;
        });
}

function selectShortcodeMedia(filename) {
    if (currentShortcodeTarget) {
        document.getElementById(currentShortcodeTarget).value = filename;
        // Trigger input event for preview update
        document.getElementById(currentShortcodeTarget).dispatchEvent(new Event('input'));
    }
    closeModal('shortcodeMediaModal');
}

// Image preview update
document.getElementById('imageInput').addEventListener('input', function() {
    const preview = document.getElementById('imagePreview');
    const img = preview.querySelector('img');
    if (this.value) {
        img.src = this.value;
        img.style.display = 'block';
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

// Media browser
function openMediaBrowser() {
    openModal('mediaBrowserModal');
    loadMedia('articles');
}

function loadMedia(path) {
    const content = document.getElementById('mediaBrowserContent');
    content.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    
    fetch('api.php?action=media&path=' + encodeURIComponent(path))
        .then(r => r.json())
        .then(data => {
            let html = '<div class="breadcrumb" style="margin-bottom: 16px;">';
            html += '<a href="#" onclick="loadMedia(\'\'); return false;">images</a>';
            
            if (path) {
                const parts = path.split('/');
                let currentPath = '';
                parts.forEach((part, i) => {
                    currentPath += (i > 0 ? '/' : '') + part;
                    html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; opacity: 0.5;"><polyline points="9 18 15 12 9 6"/></svg>';
                    html += '<a href="#" onclick="loadMedia(\'' + currentPath + '\'); return false;">' + part + '</a>';
                });
            }
            html += '</div>';
            
            html += '<div class="media-grid">';
            
            // Directories
            data.directories.forEach(dir => {
                html += `
                    <div class="media-item media-folder" onclick="loadMedia('${dir.path}')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>${dir.name}</span>
                    </div>
                `;
            });
            
            // Files
            data.files.forEach(file => {
                html += `
                    <div class="media-item" onclick="selectMedia('${file.path}')">
                        <img src="${file.path}" alt="${file.name}">
                        <div class="media-item-name">${file.name}</div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            if (data.files.length === 0 && data.directories.length === 0) {
                html += '<div class="empty-state"><p>No images in this folder</p></div>';
            }
            
            content.innerHTML = html;
        });
}

function selectMedia(path) {
    document.getElementById('imageInput').value = path;
    document.getElementById('imageInput').dispatchEvent(new Event('input'));
    closeModal('mediaBrowserModal');
}

// Keyboard shortcut for save (Ctrl+S)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('editForm').submit();
    }
});

// Publish changes (commit & push to trigger CI/CD)
function publishChanges() {
    const btn = document.getElementById('publishBtn');
    const text = document.getElementById('publishText');
    
    // Prompt for commit message
    const message = prompt('Enter commit message:', 'Content update');
    if (!message) return;
    
    btn.disabled = true;
    text.textContent = 'Publishing...';
    btn.style.opacity = '0.7';
    
    const formData = new FormData();
    formData.append('message', message);
    
    fetch('api.php?action=publish', {
        method: 'POST',
        body: formData
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                text.textContent = '✓ Published!';
                btn.style.background = 'rgba(16, 185, 129, 0.8)';
                showNotification(data.message || 'Changes published! CI/CD pipeline triggered.', 'success');
            } else {
                text.textContent = '✗ Failed';
                btn.style.background = 'rgba(225, 29, 72, 0.8)';
                showNotification('Publish failed: ' + (data.error || data.output || 'Unknown error'), 'error');
            }
            
            // Reset after 3 seconds
            setTimeout(() => {
                btn.disabled = false;
                text.textContent = 'Publish';
                btn.style.opacity = '1';
                btn.style.background = '';
            }, 3000);
        })
        .catch(err => {
            text.textContent = '✗ Error';
            showNotification('Network error: ' + err.message, 'error');
            
            setTimeout(() => {
                btn.disabled = false;
                text.textContent = 'Publish';
                btn.style.opacity = '1';
            }, 3000);
        });
}

// Rebuild site function (local)
function rebuildSite() {
    const btn = document.getElementById('rebuildBtn');
    const text = document.getElementById('rebuildText');
    
    btn.disabled = true;
    text.textContent = 'Building...';
    btn.style.opacity = '0.7';
    
    fetch('api.php?action=build')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                text.textContent = '✓ Built!';
                btn.style.background = 'rgba(16, 185, 129, 0.2)';
                btn.style.borderColor = '#10b981';
                btn.style.color = '#10b981';
                
                // Show notification
                showNotification('Site rebuilt successfully!', 'success');
            } else {
                text.textContent = '✗ Failed';
                btn.style.background = 'rgba(225, 29, 72, 0.2)';
                btn.style.borderColor = '#e11d48';
                btn.style.color = '#e11d48';
                
                // Show error
                showNotification('Build failed: ' + (data.output || 'Unknown error'), 'error');
            }
            
            // Reset after 3 seconds
            setTimeout(() => {
                btn.disabled = false;
                text.textContent = 'Rebuild Site';
                btn.style.opacity = '1';
                btn.style.background = '';
                btn.style.borderColor = '';
                btn.style.color = '';
            }, 3000);
        })
        .catch(err => {
            text.textContent = '✗ Error';
            showNotification('Network error: ' + err.message, 'error');
            
            setTimeout(() => {
                btn.disabled = false;
                text.textContent = 'Rebuild Site';
                btn.style.opacity = '1';
            }, 3000);
        });
}

function showNotification(message, type) {
    // Remove existing notification
    const existing = document.querySelector('.build-notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'build-notification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 8px;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        max-width: 400px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;
    
    if (type === 'success') {
        notification.style.background = 'rgba(16, 185, 129, 0.95)';
        notification.style.color = 'white';
    } else {
        notification.style.background = 'rgba(225, 29, 72, 0.95)';
        notification.style.color = 'white';
    }
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Related Articles handling
function addRelatedArticle(select) {
    const slug = select.value;
    if (!slug) return;
    
    const container = document.getElementById('relatedContainer');
    const input = document.getElementById('relatedInput');
    
    // Get current related articles
    let related = JSON.parse(input.value || '[]');
    
    // Check if already added
    if (related.includes(slug)) {
        select.value = '';
        return;
    }
    
    // Add to array
    related.push(slug);
    input.value = JSON.stringify(related);
    
    // Add visual item
    const item = document.createElement('div');
    item.className = 'related-article-item';
    item.dataset.slug = slug;
    item.innerHTML = `
        <span class="related-article-path">${slug}</span>
        <button type="button" class="related-remove" onclick="removeRelated(this)">×</button>
    `;
    container.appendChild(item);
    
    // Reset select
    select.value = '';
}

function removeRelated(btn) {
    const item = btn.parentElement;
    const slug = item.dataset.slug;
    const input = document.getElementById('relatedInput');
    
    // Remove from array
    let related = JSON.parse(input.value || '[]');
    related = related.filter(r => r !== slug);
    input.value = JSON.stringify(related);
    
    // Remove visual item
    item.remove();
}

</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
