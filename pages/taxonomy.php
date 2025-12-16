<?php
/**
 * Pugo - Taxonomy Management (Categories, Tags, Keywords)
 * 
 * Uses the Action pattern for all tag operations.
 */

define('HUGO_ADMIN', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/Actions/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$current_lang = $_GET['lang'] ?? 'en';
$view = $_GET['view'] ?? 'tags';

// Handle tag operations via POST using Actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $actionType = $_POST['action'];
    
    if ($actionType === 'rename_tag') {
        $result = Actions::renameTag($current_lang)->handle(
            $_POST['old_tag'] ?? '',
            $_POST['new_tag'] ?? ''
        );
        $message = $result->message;
        $message_type = $result->success ? 'success' : 'error';
        
    } elseif ($actionType === 'merge_tags') {
        $result = Actions::mergeTags($current_lang)->handle(
            $_POST['source_tag'] ?? '',
            $_POST['target_tag'] ?? ''
        );
        $message = $result->message;
        $message_type = $result->success ? 'success' : 'error';
        
    } elseif ($actionType === 'delete_tag') {
        $result = Actions::deleteTag($current_lang)->handle(
            $_POST['tag'] ?? ''
        );
        $message = $result->message;
        $message_type = $result->success ? 'success' : 'error';
    }
}

// Get taxonomy data using Actions
$tagsResult = Actions::listTags($current_lang)->handle();
$tags = $tagsResult->success ? $tagsResult->data['tags'] : [];

// Legacy functions for sections/categories (not yet migrated to Actions)
$taxonomy = get_article_taxonomy($current_lang);
$keywords = get_all_keywords($current_lang);

$page_title = 'Taxonomy';
include __DIR__ . '/includes/header.php';
?>

<style>
/* Taxonomy Navigation */
.taxonomy-nav {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
}
.taxonomy-nav a {
    padding: 0.5rem 1rem;
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
    text-decoration: none;
    color: var(--text-secondary);
    font-weight: 500;
    transition: all 0.2s;
}
.taxonomy-nav a:hover {
    color: var(--text-primary);
    background: var(--bg-hover);
}
.taxonomy-nav a.active {
    color: var(--accent-primary);
    background: rgba(225, 29, 72, 0.1);
    border-bottom: 2px solid var(--accent-primary);
}

/* Message Alerts */
.alert {
    padding: 1rem 1.25rem;
    border-radius: var(--radius-sm);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}
.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Section Cards */
.section-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-bottom: 1rem;
    overflow: hidden;
}
.section-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: background 0.2s;
}
.section-header:hover {
    background: var(--bg-hover);
}
.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.section-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}
.section-name {
    font-weight: 600;
    font-size: 1.1rem;
    color: var(--text-primary);
}
.section-count {
    background: var(--bg-tertiary);
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    color: var(--text-secondary);
}
.section-body {
    padding: 0;
    display: none;
}
.section-body.open {
    display: block;
}

/* Category Rows */
.category-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-tertiary);
    cursor: pointer;
    transition: background 0.15s;
}
.category-row:hover {
    background: var(--bg-hover);
}
.category-row:last-child {
    border-bottom: none;
}
.category-name {
    font-weight: 500;
    color: var(--text-primary);
}
.category-count {
    font-size: 0.875rem;
    color: var(--text-muted);
}
.category-articles {
    background: var(--bg-primary);
}

/* Article Items */
.article-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.625rem 1.5rem 0.625rem 3rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.9rem;
    transition: background 0.15s;
}
.article-item:hover {
    background: var(--bg-hover);
}
.article-item:last-child {
    border-bottom: none;
}
.article-title {
    color: var(--text-primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.article-title:hover {
    color: var(--accent-primary);
}
.article-tags {
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
}
.article-tag {
    background: var(--bg-tertiary);
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.15s;
}
.article-tag:hover {
    background: var(--accent-primary);
    color: white;
}
.draft-badge {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Enhanced Tags View */
.tags-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
    flex-wrap: wrap;
}
.tags-search {
    flex: 1;
    max-width: 300px;
}
.tags-search input {
    width: 100%;
    padding: 0.625rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 0.9rem;
}
.tags-search input:focus {
    outline: none;
    border-color: var(--accent-primary);
}
.tags-search input::placeholder {
    color: var(--text-muted);
}

.tags-table-container {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
}
.tags-table {
    width: 100%;
    border-collapse: collapse;
}
.tags-table th,
.tags-table td {
    padding: 1rem 1.25rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.tags-table th {
    background: var(--bg-tertiary);
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.tags-table tr:last-child td {
    border-bottom: none;
}
.tags-table tr:hover td {
    background: var(--bg-hover);
}

.tag-name-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.tag-icon {
    width: 32px;
    height: 32px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ef4444;
}
.tag-name {
    font-weight: 600;
    color: var(--text-primary);
}
.tag-slug {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-family: monospace;
}
.tag-count-badge {
    background: var(--accent-primary);
    color: white;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
}
.tag-articles-preview {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    max-width: 300px;
}
.tag-article-preview {
    font-size: 0.85rem;
    color: var(--text-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tag-article-preview a {
    color: var(--text-secondary);
    text-decoration: none;
}
.tag-article-preview a:hover {
    color: var(--accent-primary);
}
.tag-more-link {
    font-size: 0.8rem;
    color: var(--text-muted);
}
.tag-actions {
    display: flex;
    gap: 0.5rem;
}
.tag-action-btn {
    padding: 0.375rem 0.75rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-color);
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}
.tag-action-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
    border-color: var(--text-muted);
}
.tag-action-btn.danger:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.5);
    color: #ef4444;
}
.tag-action-btn svg {
    width: 14px;
    height: 14px;
}

/* View on Site Link */
.tag-view-link {
    color: var(--text-muted);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
}
.tag-view-link:hover {
    color: var(--accent-primary);
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-overlay.active {
    display: flex;
}
.modal-content {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    width: 100%;
    max-width: 450px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
}
.modal-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
}
.modal-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-close:hover {
    color: var(--text-primary);
}
.modal-body {
    margin-bottom: 1.25rem;
}
.modal-body p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 1rem;
}
.modal-body .form-group {
    margin-bottom: 1rem;
}
.modal-body .form-group:last-child {
    margin-bottom: 0;
}
.modal-body label {
    display: block;
    margin-bottom: 0.375rem;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.875rem;
}
.modal-body input,
.modal-body select {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 0.9rem;
}
.modal-body input:focus,
.modal-body select:focus {
    outline: none;
    border-color: var(--accent-primary);
}
.modal-footer {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}
.modal-btn {
    padding: 0.625rem 1.25rem;
    border-radius: var(--radius-sm);
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.15s;
}
.modal-btn-cancel {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
}
.modal-btn-cancel:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}
.modal-btn-primary {
    background: var(--accent-primary);
    border: 1px solid var(--accent-primary);
    color: white;
}
.modal-btn-primary:hover {
    background: #be123c;
}
.modal-btn-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.5);
    color: #ef4444;
}
.modal-btn-danger:hover {
    background: #ef4444;
    color: white;
}

/* Tags Grid View (for keywords) */
.tags-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}
.tag-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 1rem;
    transition: border-color 0.15s;
}
.tag-card:hover {
    border-color: var(--accent-primary);
}
.tag-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}
.tag-card-name {
    font-weight: 600;
    color: var(--text-primary);
}
.tag-card-count {
    background: var(--accent-primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.tag-card-articles {
    font-size: 0.85rem;
}
.tag-card-article-link {
    color: var(--text-secondary);
    text-decoration: none;
    display: block;
    padding: 0.25rem 0;
    transition: color 0.15s;
}
.tag-card-article-link:hover {
    color: var(--accent-primary);
}

/* Summary Stats */
.stats-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.stat-item {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    padding: 1rem 1.5rem;
    border-radius: var(--radius-sm);
    min-width: 120px;
    flex: 1;
}
.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}
.stat-label {
    color: var(--text-muted);
    font-size: 0.875rem;
}

/* Page Header */
.content-header {
    margin-bottom: 1.5rem;
}
.content-header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}
.content-header p {
    color: var(--text-secondary);
}

/* Language Selector */
.language-selector {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.lang-btn {
    padding: 0.5rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.15s;
}
.lang-btn:hover {
    background: var(--bg-hover);
    color: var(--text-primary);
}
.lang-btn.active {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-muted);
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
}
.empty-state code {
    background: var(--bg-tertiary);
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
}

/* Responsive */
@media (max-width: 768px) {
    .tags-table th:nth-child(3),
    .tags-table td:nth-child(3) {
        display: none;
    }
}
</style>

<div class="content-wrapper">
    <div class="content-header">
        <h1>📊 Taxonomy Management</h1>
        <p>View and manage sections, categories, tags, and keywords</p>
    </div>
    
    <!-- Language Selector -->
    <div class="language-selector" style="margin-bottom: 1rem;">
        <?php foreach ($config['languages'] as $code => $lang): ?>
            <a href="?view=<?= $view ?>&lang=<?= $code ?>" 
               class="lang-btn <?= $code === $current_lang ? 'active' : '' ?>">
                <?= $lang['flag'] ?> <?= $lang['name'] ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= $message_type === 'success' ? '✓' : '✕' ?>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-value"><?= count($taxonomy) ?></div>
            <div class="stat-label">Sections</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= array_sum(array_map(fn($s) => count($s['categories']), $taxonomy)) ?></div>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= array_sum(array_map(fn($s) => $s['count'], $taxonomy)) ?></div>
            <div class="stat-label">Articles</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= count($tags) ?></div>
            <div class="stat-label">Tags</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= count($keywords) ?></div>
            <div class="stat-label">Keywords</div>
        </div>
    </div>
    
    <!-- Navigation Tabs -->
    <div class="taxonomy-nav">
        <a href="?view=tags&lang=<?= $current_lang ?>" class="<?= $view === 'tags' ? 'active' : '' ?>">
            🏷️ Tags
        </a>
        <a href="?view=sections&lang=<?= $current_lang ?>" class="<?= $view === 'sections' ? 'active' : '' ?>">
            📁 Sections & Categories
        </a>
        <a href="?view=keywords&lang=<?= $current_lang ?>" class="<?= $view === 'keywords' ? 'active' : '' ?>">
            🔑 Keywords
        </a>
    </div>
    
    <?php if ($view === 'tags'): ?>
        <!-- Enhanced Tags View -->
        <div class="tags-header-bar">
            <div class="tags-search">
                <input type="text" id="tagSearch" placeholder="Search tags..." onkeyup="filterTags()">
            </div>
        </div>
        
        <?php if (!empty($tags)): ?>
        <div class="tags-table-container">
            <table class="tags-table" id="tagsTable">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Articles</th>
                        <th>Used In</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag_name => $tag_data): ?>
                    <tr data-tag="<?= htmlspecialchars(strtolower($tag_name)) ?>">
                        <td>
                            <div class="tag-name-cell">
                                <div class="tag-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/>
                                        <path d="M7 7h.01"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="tag-name"><?= htmlspecialchars($tag_name) ?></div>
                                    <a href="/tags/<?= urlencode($tag_name) ?>/" target="_blank" class="tag-view-link">
                                        /tags/<?= urlencode($tag_name) ?>/
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                            <polyline points="15 3 21 3 21 9"/>
                                            <line x1="10" y1="14" x2="21" y2="3"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="tag-count-badge"><?= $tag_data['count'] ?></span>
                        </td>
                        <td>
                            <div class="tag-articles-preview">
                                <?php 
                                $shown = 0;
                                foreach ($tag_data['articles'] as $article): 
                                    if ($shown >= 2) break;
                                    $relative = str_replace(CONTENT_DIR . '/', '', $article['path']);
                                ?>
                                <div class="tag-article-preview">
                                    <a href="edit.php?file=<?= urlencode($relative) ?>&lang=<?= $current_lang ?>">
                                        <?= htmlspecialchars($article['title']) ?>
                                    </a>
                                </div>
                                <?php 
                                    $shown++;
                                endforeach; 
                                if ($tag_data['count'] > 2):
                                ?>
                                <span class="tag-more-link">+<?= $tag_data['count'] - 2 ?> more</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="tag-actions">
                                <button class="tag-action-btn" onclick="openRenameModal('<?= htmlspecialchars(addslashes($tag_name)) ?>')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    Rename
                                </button>
                                <button class="tag-action-btn" onclick="openMergeModal('<?= htmlspecialchars(addslashes($tag_name)) ?>')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="16 16 12 12 8 16"/>
                                        <line x1="12" y1="12" x2="12" y2="21"/>
                                        <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                                    </svg>
                                    Merge
                                </button>
                                <button class="tag-action-btn danger" onclick="openDeleteModal('<?= htmlspecialchars(addslashes($tag_name)) ?>', <?= $tag_data['count'] ?>)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <p>No tags found. Add tags to your articles using the <code>tags:</code> frontmatter field.</p>
        </div>
        <?php endif; ?>
        
    <?php elseif ($view === 'sections'): ?>
        <!-- Sections & Categories View -->
        <?php foreach ($taxonomy as $section_slug => $section): ?>
            <?php 
                $discovered = discover_sections();
                $section_color = $discovered[$section_slug]['color'] ?? '#6b7280';
            ?>
            <div class="section-card">
                <div class="section-header" onclick="toggleSection(this)">
                    <div class="section-title">
                        <span class="section-color" style="background: <?= $section_color ?>"></span>
                        <span class="section-name"><?= ucfirst($section_slug) ?></span>
                    </div>
                    <span class="section-count"><?= $section['count'] ?> article<?= $section['count'] !== 1 ? 's' : '' ?></span>
                </div>
                <div class="section-body">
                    <!-- Categories in this section -->
                    <?php foreach ($section['categories'] as $cat_slug => $category): ?>
                        <div class="category-row" onclick="toggleCategory(this)">
                            <span class="category-name">📂 <?= ucfirst(str_replace('-', ' ', $cat_slug)) ?></span>
                            <span class="category-count"><?= $category['count'] ?> article<?= $category['count'] !== 1 ? 's' : '' ?></span>
                        </div>
                        <div class="category-articles" style="display: none;">
                            <?php foreach ($category['articles'] as $article): ?>
                                <div class="article-item">
                                    <a href="edit.php?file=<?= urlencode($article['relative_path']) ?>&lang=<?= $current_lang ?>" 
                                       class="article-title">
                                        <?php if ($article['draft']): ?>
                                            <span class="draft-badge">DRAFT</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($article['title']) ?>
                                    </a>
                                    <div class="article-tags">
                                        <?php foreach ($article['tags'] as $tag): ?>
                                            <a href="?view=tags&lang=<?= $current_lang ?>#tag-<?= urlencode($tag) ?>" 
                                               class="article-tag"><?= htmlspecialchars($tag) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Root articles (no category) -->
                    <?php foreach ($section['articles'] as $article): ?>
                        <div class="article-item">
                            <a href="edit.php?file=<?= urlencode($article['relative_path']) ?>&lang=<?= $current_lang ?>" 
                               class="article-title">
                                <?php if ($article['draft']): ?>
                                    <span class="draft-badge">DRAFT</span>
                                <?php endif; ?>
                                <?= htmlspecialchars($article['title']) ?>
                            </a>
                            <div class="article-tags">
                                <?php foreach ($article['tags'] as $tag): ?>
                                    <a href="?view=tags&lang=<?= $current_lang ?>#tag-<?= urlencode($tag) ?>" 
                                       class="article-tag"><?= htmlspecialchars($tag) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($taxonomy)): ?>
            <div class="empty-state">
                <p>No sections found in this language.</p>
            </div>
        <?php endif; ?>
        
    <?php elseif ($view === 'keywords'): ?>
        <!-- Keywords View -->
        <div class="tags-grid">
            <?php foreach ($keywords as $keyword_name => $keyword_data): ?>
                <div class="tag-card">
                    <div class="tag-card-header">
                        <span class="tag-card-name">🔑 <?= htmlspecialchars($keyword_name) ?></span>
                        <span class="tag-card-count"><?= $keyword_data['count'] ?></span>
                    </div>
                    <div class="tag-card-articles">
                        <?php foreach ($keyword_data['articles'] as $article): ?>
                            <?php 
                                $relative = str_replace(CONTENT_DIR . '/', '', $article['path']);
                            ?>
                            <a href="edit.php?file=<?= urlencode($relative) ?>&lang=<?= $current_lang ?>" 
                               class="tag-card-article-link">
                                → <?= htmlspecialchars($article['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($keywords)): ?>
            <div class="empty-state">
                <p>No keywords found. Add keywords to your articles using the <code>keywords:</code> frontmatter field.</p>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<!-- Rename Modal -->
<div class="modal-overlay" id="renameModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Rename Tag</h3>
            <button class="modal-close" onclick="closeModal('renameModal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="rename_tag">
            <input type="hidden" name="old_tag" id="renameOldTag">
            <div class="modal-body">
                <p>Rename this tag across all articles that use it.</p>
                <div class="form-group">
                    <label>Current Tag Name</label>
                    <input type="text" id="renameCurrentDisplay" disabled>
                </div>
                <div class="form-group">
                    <label>New Tag Name</label>
                    <input type="text" name="new_tag" id="renameNewTag" required placeholder="Enter new tag name">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('renameModal')">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-primary">Rename Tag</button>
            </div>
        </form>
    </div>
</div>

<!-- Merge Modal -->
<div class="modal-overlay" id="mergeModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Merge Tag</h3>
            <button class="modal-close" onclick="closeModal('mergeModal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="merge_tags">
            <input type="hidden" name="source_tag" id="mergeSourceTag">
            <div class="modal-body">
                <p>Merge this tag into another. All articles with this tag will be updated to use the target tag instead.</p>
                <div class="form-group">
                    <label>Tag to Merge (will be removed)</label>
                    <input type="text" id="mergeSourceDisplay" disabled>
                </div>
                <div class="form-group">
                    <label>Merge Into</label>
                    <select name="target_tag" id="mergeTargetTag" required>
                        <option value="">Select target tag...</option>
                        <?php foreach ($tags as $tag_name => $tag_data): ?>
                            <option value="<?= htmlspecialchars($tag_name) ?>"><?= htmlspecialchars($tag_name) ?> (<?= $tag_data['count'] ?> articles)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('mergeModal')">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-primary">Merge Tags</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Delete Tag</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete_tag">
            <input type="hidden" name="tag" id="deleteTag">
            <div class="modal-body">
                <p>⚠️ This will remove the tag from all articles. The articles themselves will not be deleted.</p>
                <div class="form-group">
                    <label>Tag to Delete</label>
                    <input type="text" id="deleteTagDisplay" disabled>
                </div>
                <div class="form-group">
                    <label>Articles Affected</label>
                    <input type="text" id="deleteArticleCount" disabled>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="modal-btn modal-btn-danger">Delete Tag</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSection(header) {
    const body = header.nextElementSibling;
    body.classList.toggle('open');
}

function toggleCategory(row) {
    event.stopPropagation();
    const articles = row.nextElementSibling;
    if (articles && articles.classList.contains('category-articles')) {
        articles.style.display = articles.style.display === 'none' ? 'block' : 'none';
    }
}

function filterTags() {
    const search = document.getElementById('tagSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#tagsTable tbody tr');
    
    rows.forEach(row => {
        const tag = row.dataset.tag;
        row.style.display = tag.includes(search) ? '' : 'none';
    });
}

function openRenameModal(tag) {
    document.getElementById('renameOldTag').value = tag;
    document.getElementById('renameCurrentDisplay').value = tag;
    document.getElementById('renameNewTag').value = tag;
    document.getElementById('renameModal').classList.add('active');
    document.getElementById('renameNewTag').focus();
    document.getElementById('renameNewTag').select();
}

function openMergeModal(tag) {
    document.getElementById('mergeSourceTag').value = tag;
    document.getElementById('mergeSourceDisplay').value = tag;
    
    // Disable the source tag in the target dropdown
    const select = document.getElementById('mergeTargetTag');
    Array.from(select.options).forEach(opt => {
        opt.disabled = opt.value === tag;
    });
    select.value = '';
    
    document.getElementById('mergeModal').classList.add('active');
}

function openDeleteModal(tag, count) {
    document.getElementById('deleteTag').value = tag;
    document.getElementById('deleteTagDisplay').value = tag;
    document.getElementById('deleteArticleCount').value = count + ' article' + (count !== 1 ? 's' : '');
    document.getElementById('deleteModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

// Open section if hash matches
if (window.location.hash) {
    const target = document.querySelector(window.location.hash);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth' });
        target.style.animation = 'highlight 2s ease';
    }
}
</script>

<style>
@keyframes highlight {
    0%, 100% { background: var(--bg-secondary); }
    50% { background: rgba(245, 158, 11, 0.2); }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
