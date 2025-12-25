<?php
/**
 * Pugo Admin - Pages Manager
 * 
 * Manages standalone pages like Contact, Terms, About, etc.
 * These are single _index.md files in their own folders, not part of article sections.
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Pages';
$current_lang = $_GET['lang'] ?? 'en';

if (!isset($config['languages'][$current_lang])) {
    $current_lang = 'en';
}

/**
 * Discover standalone pages (not article sections)
 * A page is a folder with only _index.md and no child articles
 */
function discover_pages($lang = 'en', $config = []) {
    $pages = [];
    $content_dir = $lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . ($config['languages'][$lang]['content_dir'] ?? 'content');
    
    if (!is_dir($content_dir)) {
        return $pages;
    }
    
    // Known article sections to exclude
    $article_sections = array_keys($config['sections'] ?? []);
    
    foreach (scandir($content_dir) as $item) {
        if ($item[0] === '.' || $item === '_index.md') continue;
        
        $path = $content_dir . '/' . $item;
        
        if (!is_dir($path)) continue;
        
        // Skip known article sections
        if (in_array($item, $article_sections)) continue;
        
        $index_file = $path . '/_index.md';
        if (!file_exists($index_file)) continue;
        
        // Check if this is a "page" (only _index.md, no other articles or subdirs with content)
        $is_page = true;
        $has_children = false;
        
        foreach (scandir($path) as $child) {
            if ($child[0] === '.') continue;
            
            $child_path = $path . '/' . $child;
            
            // If there are other .md files, it's likely a section
            if (is_file($child_path) && $child !== '_index.md' && pathinfo($child, PATHINFO_EXTENSION) === 'md') {
                $has_children = true;
                break;
            }
            
            // If there are subdirectories with .md files, it's a section
            if (is_dir($child_path)) {
                $subfiles = glob($child_path . '/*.md');
                if (!empty($subfiles)) {
                    $has_children = true;
                    break;
                }
            }
        }
        
        if ($has_children) continue;
        
        // Parse the page
        $content = file_get_contents($index_file);
        $parsed = parse_frontmatter($content);
        
        $pages[$item] = [
            'slug' => $item,
            'path' => $index_file,
            'relative_path' => $item . '/_index.md',
            'frontmatter' => $parsed['frontmatter'],
            'title' => $parsed['frontmatter']['title'] ?? ucfirst(str_replace('-', ' ', $item)),
            'description' => $parsed['frontmatter']['description'] ?? '',
            'layout' => $parsed['frontmatter']['layout'] ?? 'default',
            'draft' => !empty($parsed['frontmatter']['draft']),
            'modified' => filemtime($index_file),
        ];
    }
    
    // Sort by title
    uasort($pages, fn($a, $b) => strcasecmp($a['title'], $b['title']));
    
    return $pages;
}

/**
 * Get page icon based on slug
 */
function get_page_icon($slug) {
    $icons = [
        'contact' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'about' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'privacy' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'terms' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'guidelines' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>',
        'cookies' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'status' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'compliance' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'faq' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    ];
    
    return $icons[$slug] ?? '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>';
}

/**
 * Get page color based on slug
 */
function get_page_color($slug) {
    $colors = [
        'contact' => '#06b6d4',     // Cyan
        'about' => '#3b82f6',       // Blue
        'privacy' => '#6366f1',     // Indigo
        'terms' => '#6366f1',       // Indigo
        'guidelines' => '#14b8a6',  // Teal
        'cookies' => '#6366f1',     // Indigo
        'status' => '#22c55e',      // Green
        'compliance' => '#6366f1',  // Indigo
        'faq' => '#8b5cf6',         // Purple
    ];
    
    return $colors[$slug] ?? '#6b7280';
}

// Get pages
$pages = discover_pages($current_lang, $config);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    csrf_check(); // Validate CSRF token
    
    $slug = $_POST['slug'] ?? '';
    if ($slug && isset($pages[$slug])) {
        $dir_path = dirname($pages[$slug]['path']);
        $page_title = $pages[$slug]['title'];
        
        // Delete all files in the directory
        $files = glob($dir_path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Delete the directory
        if (rmdir($dir_path)) {
            // Auto-rebuild Hugo
            $build_result = build_hugo();
            if ($build_result['success']) {
                $_SESSION['success'] = "Page '{$page_title}' deleted and site rebuilt successfully.";
            } else {
                $_SESSION['success'] = "Page '{$page_title}' deleted successfully.";
                $_SESSION['warning'] = 'Hugo rebuild had warnings.';
            }
        } else {
            $_SESSION['error'] = "Failed to delete page directory.";
        }
        
        header('Location: pages.php?lang=' . $current_lang);
        exit;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<style>
/* Pages Grid */
.pages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.page-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    text-decoration: none;
    transition: all 0.15s ease;
    position: relative;
}

.page-card:hover {
    border-color: var(--page-color, var(--accent-primary));
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

.page-card-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.page-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.page-icon svg {
    width: 24px;
    height: 24px;
    color: white;
}

.page-info {
    flex: 1;
    min-width: 0;
}

.page-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.page-slug {
    font-size: 12px;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
}

.page-description {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.page-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border-color);
    font-size: 12px;
    color: var(--text-muted);
}

.page-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}

.page-badge.draft {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.page-badge.layout {
    background: var(--bg-tertiary);
    color: var(--text-secondary);
}

.page-actions {
    position: absolute;
    top: 16px;
    right: 16px;
    display: flex;
    gap: 8px;
    opacity: 0;
    transition: opacity 0.15s;
}

.page-card:hover .page-actions {
    opacity: 1;
}

.page-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}

.page-action-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.page-action-btn.delete:hover {
    background: rgba(225, 29, 72, 0.1);
    color: #e11d48;
}

/* Homepage card special styling */
.page-card.homepage {
    border-color: var(--accent-primary);
    background: linear-gradient(135deg, rgba(225, 29, 72, 0.05), transparent);
}

.page-card.homepage .page-icon {
    background: var(--accent-primary);
}

/* Empty state */
.pages-empty {
    text-align: center;
    padding: 80px 20px;
    background: var(--bg-secondary);
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-md);
}

.pages-empty svg {
    width: 64px;
    height: 64px;
    color: var(--text-muted);
    opacity: 0.5;
    margin-bottom: 16px;
}

.pages-empty h3 {
    font-size: 18px;
    margin-bottom: 8px;
}

.pages-empty p {
    color: var(--text-secondary);
    margin-bottom: 24px;
}

/* New page modal */
.new-page-form .form-group {
    margin-bottom: 20px;
}

.slug-preview {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 6px;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Pages</h1>
        <p class="page-subtitle">
            Manage standalone pages like Contact, Terms, About, etc.
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('newPageModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Page
    </button>
</div>

<!-- Language Tabs -->
<?php if (count($config['languages']) > 1): ?>
<div class="lang-tabs">
    <?php foreach ($config['languages'] as $lang => $lang_config): ?>
    <a href="?lang=<?= $lang ?>" class="lang-tab <?= $current_lang === $lang ? 'active' : '' ?>">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($pages)): ?>
<!-- Empty State -->
<div class="pages-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="12" y1="18" x2="12" y2="12"/>
        <line x1="9" y1="15" x2="15" y2="15"/>
    </svg>
    <h3>No standalone pages yet</h3>
    <p>Create pages like Contact, About, Terms, Privacy, etc.</p>
    <button type="button" class="btn btn-primary" onclick="openModal('newPageModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Create First Page
    </button>
</div>
<?php else: ?>
<!-- Pages Grid -->
<div class="pages-grid">
    <?php foreach ($pages as $slug => $page): 
        $color = get_page_color($slug);
        $icon = get_page_icon($slug);
    ?>
    <a href="page-edit.php?page=<?= urlencode($slug) ?>&lang=<?= $current_lang ?>" 
       class="page-card" 
       style="--page-color: <?= $color ?>">
        
        <div class="page-card-header">
            <div class="page-icon" style="background: <?= $color ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <?= $icon ?>
                </svg>
            </div>
            <div class="page-info">
                <div class="page-title"><?= htmlspecialchars($page['title']) ?></div>
                <div class="page-slug">/<?= $slug ?>/</div>
            </div>
        </div>
        
        <?php if ($page['description']): ?>
        <div class="page-description"><?= htmlspecialchars($page['description']) ?></div>
        <?php endif; ?>
        
        <div class="page-meta">
            <?php if ($page['draft']): ?>
            <span class="page-badge draft">Draft</span>
            <?php endif; ?>
            
            <?php if ($page['layout'] !== 'default'): ?>
            <span class="page-badge layout"><?= htmlspecialchars($page['layout']) ?></span>
            <?php endif; ?>
            
            <span style="margin-left: auto;">Updated <?= time_ago($page['modified']) ?></span>
        </div>
        
        <div class="page-actions">
            <button type="button" class="page-action-btn delete" 
                    onclick="event.preventDefault(); confirmDelete('<?= $slug ?>', '<?= htmlspecialchars($page['title']) ?>')"
                    title="Delete page">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </button>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- New Page Modal -->
<div id="newPageModal" class="modal-overlay">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h2 class="modal-title">Create New Page</h2>
            <button type="button" class="modal-close" onclick="closeModal('newPageModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <form action="page-edit.php" method="GET" class="new-page-form">
            <input type="hidden" name="new" value="1">
            <input type="hidden" name="lang" value="<?= $current_lang ?>">
            
            <div class="form-group">
                <label class="form-label">Page Title *</label>
                <input type="text" name="title" id="newPageTitle" class="form-input" 
                       placeholder="e.g., Contact Us, About, Privacy Policy..."
                       required oninput="updateSlugPreview()">
                <div class="slug-preview">
                    URL: /<span id="slugPreview">page-name</span>/
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">URL Slug</label>
                <input type="text" name="slug" id="newPageSlug" class="form-input" 
                       placeholder="auto-generated from title"
                       pattern="[a-z0-9-]+"
                       oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '')">
                <small style="color: var(--text-muted); font-size: 12px;">
                    Leave empty to auto-generate from title. Use only lowercase letters, numbers, and hyphens.
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Template</label>
                <select name="layout" class="form-input">
                    <option value="default">Default</option>
                    <option value="contact">Contact Page</option>
                    <option value="full-width">Full Width</option>
                    <option value="narrow">Narrow Content</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newPageModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Create Page
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h2 class="modal-title">Delete Page</h2>
            <button type="button" class="modal-close" onclick="closeModal('deleteModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <p style="margin-bottom: 24px; color: var(--text-secondary);">
            Are you sure you want to delete "<strong id="deletePageTitle"></strong>"? This action cannot be undone.
        </p>
        
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="slug" id="deletePageSlug">
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #e11d48;">
                    Delete Page
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Click outside to close
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Escape key to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
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

function updateSlugPreview() {
    const title = document.getElementById('newPageTitle').value;
    const slugInput = document.getElementById('newPageSlug');
    const preview = document.getElementById('slugPreview');
    
    // Only auto-update if slug field is empty
    if (!slugInput.value) {
        preview.textContent = generateSlug(title) || 'page-name';
    }
}

// Update preview when slug is manually entered
document.getElementById('newPageSlug')?.addEventListener('input', function() {
    document.getElementById('slugPreview').textContent = this.value || generateSlug(document.getElementById('newPageTitle').value) || 'page-name';
});

// Delete confirmation
function confirmDelete(slug, title) {
    document.getElementById('deletePageSlug').value = slug;
    document.getElementById('deletePageTitle').textContent = title;
    openModal('deleteModal');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

