<?php
/**
 * Pugo Admin - Template Manager
 * 
 * View, edit, and create Hugo templates from the admin panel.
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$layouts_dir = HUGO_ROOT . '/layouts';

// Get template structure
function get_template_tree($dir, $base_path = '') {
    $items = [];
    
    if (!is_dir($dir)) {
        return $items;
    }
    
    foreach (scandir($dir) as $item) {
        if ($item[0] === '.') continue;
        
        $path = $dir . '/' . $item;
        $relative_path = $base_path ? $base_path . '/' . $item : $item;
        
        if (is_dir($path)) {
            $items[] = [
                'type' => 'folder',
                'name' => $item,
                'path' => $relative_path,
                'children' => get_template_tree($path, $relative_path)
            ];
        } else {
            $ext = pathinfo($item, PATHINFO_EXTENSION);
            if (in_array($ext, ['html', 'xml', 'json', 'txt'])) {
                $items[] = [
                    'type' => 'file',
                    'name' => $item,
                    'path' => $relative_path,
                    'extension' => $ext,
                    'size' => filesize($path),
                    'modified' => filemtime($path)
                ];
            }
        }
    }
    
    // Sort: folders first, then files
    usort($items, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'folder' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $items;
}

$template_tree = get_template_tree($layouts_dir);

// Count templates
function count_templates($tree) {
    $count = 0;
    foreach ($tree as $item) {
        if ($item['type'] === 'file') {
            $count++;
        } elseif (isset($item['children'])) {
            $count += count_templates($item['children']);
        }
    }
    return $count;
}

$template_count = count_templates($template_tree);

$page_title = 'Templates';
require __DIR__ . '/../includes/header.php';
?>

<style>
/* Template Grid */
.template-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 24px;
    min-height: calc(100vh - 200px);
}

@media (max-width: 900px) {
    .template-layout {
        grid-template-columns: 1fr;
    }
}

/* Sidebar Tree */
.template-tree {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.template-tree-header {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-tertiary);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.template-tree-header h3 {
    font-size: 14px;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.template-tree-header h3 svg {
    width: 18px;
    height: 18px;
    opacity: 0.7;
}

.template-tree-content {
    max-height: calc(100vh - 300px);
    overflow-y: auto;
    padding: 8px;
}

/* Tree Items */
.tree-folder {
    margin-bottom: 2px;
}

.tree-folder-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s;
}

.tree-folder-header:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.tree-folder-header svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    transition: transform 0.15s;
}

.tree-folder-header.collapsed svg.folder-arrow {
    transform: rotate(-90deg);
}

.tree-folder-content {
    padding-left: 16px;
    display: block;
}

.tree-folder-header.collapsed + .tree-folder-content {
    display: none;
}

.tree-file {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 12px;
    font-family: 'JetBrains Mono', monospace;
    transition: all 0.15s;
    text-decoration: none;
}

.tree-file:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}

.tree-file.active {
    background: var(--accent-primary);
    color: white;
}

.tree-file svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
    opacity: 0.7;
}

.tree-file-ext {
    margin-left: auto;
    font-size: 10px;
    padding: 1px 5px;
    background: var(--bg-tertiary);
    border-radius: 3px;
    color: var(--text-muted);
}

.tree-file.active .tree-file-ext {
    background: rgba(255,255,255,0.2);
    color: white;
}

/* Main Content */
.template-main {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.template-welcome {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 48px;
    text-align: center;
}

.template-welcome svg {
    width: 64px;
    height: 64px;
    color: var(--text-muted);
    margin-bottom: 16px;
}

.template-welcome h2 {
    font-size: 20px;
    margin-bottom: 8px;
}

.template-welcome p {
    color: var(--text-secondary);
    margin-bottom: 24px;
}

.template-stats {
    display: flex;
    gap: 24px;
    justify-content: center;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.template-stat {
    text-align: center;
}

.template-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--accent-primary);
}

.template-stat-label {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 24px;
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.15s;
}

.quick-action:hover {
    border-color: var(--accent-primary);
    background: var(--bg-hover);
}

.quick-action svg {
    width: 24px;
    height: 24px;
    color: var(--accent-primary);
}

.quick-action-text {
    display: flex;
    flex-direction: column;
}

.quick-action-title {
    font-weight: 600;
    font-size: 14px;
}

.quick-action-desc {
    font-size: 12px;
    color: var(--text-muted);
}

/* Template Types */
.template-types {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    margin-top: 16px;
}

.template-type {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 16px;
    text-align: center;
}

.template-type-icon {
    font-size: 24px;
    margin-bottom: 8px;
}

.template-type-name {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-primary);
}

.template-type-count {
    font-size: 11px;
    color: var(--text-muted);
}
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <span>Templates</span>
</div>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Template Editor</h1>
        <p class="page-subtitle">View and edit Hugo layout templates</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <button type="button" class="btn btn-secondary" onclick="openModal('newTemplateModal')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            New Template
        </button>
    </div>
</div>

<div class="template-layout">
    <!-- Sidebar: Template Tree -->
    <div class="template-tree">
        <div class="template-tree-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
                layouts/
            </h3>
            <span style="font-size: 12px; color: var(--text-muted);"><?= $template_count ?> files</span>
        </div>
        <div class="template-tree-content">
            <?php 
            function render_tree($items, $depth = 0) {
                foreach ($items as $item):
                    if ($item['type'] === 'folder'):
            ?>
            <div class="tree-folder">
                <div class="tree-folder-header" onclick="this.classList.toggle('collapsed')">
                    <svg class="folder-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    <?= htmlspecialchars($item['name']) ?>
                </div>
                <div class="tree-folder-content">
                    <?php if (!empty($item['children'])): ?>
                    <?php render_tree($item['children'], $depth + 1); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <a href="template-edit.php?file=<?= urlencode($item['path']) ?>" class="tree-file">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <?= htmlspecialchars($item['name']) ?>
                <span class="tree-file-ext">.<?= $item['extension'] ?></span>
            </a>
            <?php 
                    endif;
                endforeach;
            }
            render_tree($template_tree);
            ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="template-main">
        <div class="template-welcome">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <line x1="3" y1="9" x2="21" y2="9"/>
                <line x1="9" y1="21" x2="9" y2="9"/>
            </svg>
            <h2>Hugo Template Editor</h2>
            <p>Select a template from the sidebar to view and edit, or create a new one.</p>
            
            <div class="quick-actions">
                <a href="template-edit.php?file=_default/baseof.html" class="quick-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <path d="M3 9h18"/>
                        <path d="M9 21V9"/>
                    </svg>
                    <div class="quick-action-text">
                        <span class="quick-action-title">Base Template</span>
                        <span class="quick-action-desc">Edit site wrapper</span>
                    </div>
                </a>
                <a href="template-edit.php?file=_default/single.html" class="quick-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <div class="quick-action-text">
                        <span class="quick-action-title">Single Page</span>
                        <span class="quick-action-desc">Default page template</span>
                    </div>
                </a>
                <a href="template-edit.php?file=_default/list.html" class="quick-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/>
                        <line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    <div class="quick-action-text">
                        <span class="quick-action-title">List Page</span>
                        <span class="quick-action-desc">Section listings</span>
                    </div>
                </a>
                <a href="template-edit.php?file=index.html" class="quick-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <div class="quick-action-text">
                        <span class="quick-action-title">Homepage</span>
                        <span class="quick-action-desc">Main index page</span>
                    </div>
                </a>
            </div>
            
            <div class="template-stats">
                <div class="template-stat">
                    <div class="template-stat-value"><?= $template_count ?></div>
                    <div class="template-stat-label">Templates</div>
                </div>
                <div class="template-stat">
                    <div class="template-stat-value"><?= count($template_tree) ?></div>
                    <div class="template-stat-label">Folders</div>
                </div>
            </div>
        </div>
        
        <!-- Template Types Reference -->
        <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">📚 Template Types</h3>
            <div class="template-types">
                <div class="template-type">
                    <div class="template-type-icon">🏠</div>
                    <div class="template-type-name">baseof.html</div>
                    <div class="template-type-count">Site wrapper</div>
                </div>
                <div class="template-type">
                    <div class="template-type-icon">📄</div>
                    <div class="template-type-name">single.html</div>
                    <div class="template-type-count">Individual pages</div>
                </div>
                <div class="template-type">
                    <div class="template-type-icon">📋</div>
                    <div class="template-type-name">list.html</div>
                    <div class="template-type-count">Section lists</div>
                </div>
                <div class="template-type">
                    <div class="template-type-icon">🧩</div>
                    <div class="template-type-name">partials/</div>
                    <div class="template-type-count">Reusable parts</div>
                </div>
                <div class="template-type">
                    <div class="template-type-icon">⚡</div>
                    <div class="template-type-name">shortcodes/</div>
                    <div class="template-type-count">Content helpers</div>
                </div>
                <div class="template-type">
                    <div class="template-type-icon">🎨</div>
                    <div class="template-type-name">_default/</div>
                    <div class="template-type-count">Fallback templates</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Template Modal -->
<div id="newTemplateModal" class="modal-overlay">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h2 class="modal-title">Create New Template</h2>
            <button type="button" class="modal-close" onclick="closeModal('newTemplateModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <form action="template-edit.php" method="GET">
            <input type="hidden" name="new" value="1">
            
            <div class="form-group">
                <label class="form-label">Template Type</label>
                <select name="type" class="form-input" id="newTemplateType" onchange="updateTemplatePath()">
                    <option value="page">Page Template</option>
                    <option value="partial">Partial</option>
                    <option value="shortcode">Shortcode</option>
                    <option value="section">Section Template</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Template Name</label>
                <input type="text" name="name" class="form-input" id="newTemplateName" 
                       placeholder="my-template" pattern="[a-z0-9-]+" required
                       oninput="updateTemplatePath()">
                <small style="color: var(--text-muted); font-size: 11px;">
                    Lowercase letters, numbers, and hyphens only
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">File Path</label>
                <input type="text" class="form-input" id="newTemplatePath" readonly
                       style="font-family: 'JetBrains Mono', monospace; font-size: 12px; background: var(--bg-tertiary);">
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newTemplateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Template</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateTemplatePath() {
    const type = document.getElementById('newTemplateType').value;
    const name = document.getElementById('newTemplateName').value || 'my-template';
    
    let path = '';
    switch (type) {
        case 'page':
            path = `page/${name}.html`;
            break;
        case 'partial':
            path = `partials/${name}.html`;
            break;
        case 'shortcode':
            path = `shortcodes/${name}.html`;
            break;
        case 'section':
            path = `${name}/single.html`;
            break;
    }
    
    document.getElementById('newTemplatePath').value = 'layouts/' + path;
}

// Initialize
updateTemplatePath();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

