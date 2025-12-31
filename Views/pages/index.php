<?php
/**
 * Pages List View
 */
?>
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
    <a href="?lang=<?= $lang ?>" class="lang-tab <?= $currentLang === $lang ? 'active' : '' ?>">
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
    <a href="page-edit.php?page=<?= urlencode($slug) ?>&lang=<?= $currentLang ?>" 
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
            <input type="hidden" name="lang" value="<?= $currentLang ?>">
            
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

