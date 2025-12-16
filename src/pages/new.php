<?php
/**
 * Hugo Admin - Create New Article
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'New Article';
$current_lang = $_GET['lang'] ?? 'en';
$preselected_section = $_GET['section'] ?? '';

// Translation mode
$translate_from = $_GET['translate_from'] ?? '';
$source_lang = $_GET['source_lang'] ?? 'en';
$target_lang = $_GET['target_lang'] ?? $current_lang;

$source_article = null;
if ($translate_from) {
    $source_content_dir = $source_lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . $config['languages'][$source_lang]['content_dir'];
    $source_path = $source_content_dir . '/' . $translate_from;
    
    if (file_exists($source_path)) {
        $content = file_get_contents($source_path);
        $parsed = parse_frontmatter($content);
        $source_article = [
            'frontmatter' => $parsed['frontmatter'],
            'body' => $parsed['body'],
            'path' => $translate_from
        ];
        $current_lang = $target_lang;
        $page_title = 'Translate Article';
    }
}

$sections = get_sections_with_counts($current_lang);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    $category = $_POST['category'] ?? '';
    $slug = generate_slug($_POST['title'] ?? 'untitled');
    
    // Build path
    $content_dir = $current_lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . $config['languages'][$current_lang]['content_dir'];
    
    if ($category) {
        $file_path = $content_dir . '/' . $section . '/' . $category . '/' . $slug . '.md';
    } else {
        $file_path = $content_dir . '/' . $section . '/' . $slug . '.md';
    }
    
    // Check if file already exists
    if (file_exists($file_path)) {
        $error = 'An article with this slug already exists. Please choose a different title.';
    } else {
        // Build frontmatter
        $frontmatter = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'author' => $_POST['author'] ?? 'XloveCam Team',
            'date' => date('Y-m-d'),
            'lastmod' => date('Y-m-d'),
        ];
        
        if (!empty($_POST['image'])) {
            $frontmatter['image'] = $_POST['image'];
        }
        
        if (!empty($_POST['translationKey'])) {
            $frontmatter['translationKey'] = $_POST['translationKey'];
        }
        
        if (!empty($_POST['keywords'])) {
            $keywords = json_decode($_POST['keywords'], true);
            if ($keywords) $frontmatter['keywords'] = $keywords;
        }
        
        if (!empty($_POST['tags'])) {
            $tags = json_decode($_POST['tags'], true);
            if ($tags) $frontmatter['tags'] = $tags;
        }
        
        if (isset($_POST['draft']) && $_POST['draft']) {
            $frontmatter['draft'] = true;
        }
        
        $body = $_POST['body'] ?? '';
        
        if (save_article($file_path, $frontmatter, $body)) {
            $_SESSION['success'] = 'Article created successfully!';
            
            // Get relative path for edit redirect
            $relative_path = str_replace($content_dir . '/', '', $file_path);
            header('Location: edit.php?file=' . urlencode($relative_path) . '&lang=' . $current_lang);
            exit;
        } else {
            $error = 'Failed to create article';
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="articles.php?lang=<?= $current_lang ?>">Articles</a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 18 15 12 9 6"/>
    </svg>
    <span><?= $source_article ? 'Translate Article' : 'New Article' ?></span>
</div>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $source_article ? 'Translate Article' : 'Create New Article' ?></h1>
        <p class="page-subtitle">
            <?php if ($source_article): ?>
                Creating <?= $config['languages'][$target_lang]['flag'] ?> <?= $config['languages'][$target_lang]['name'] ?> 
                translation from <?= $config['languages'][$source_lang]['flag'] ?> <?= $config['languages'][$source_lang]['name'] ?>
            <?php else: ?>
                Fill in the details to create a new article
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($source_article): ?>
<!-- Source Article Reference -->
<div class="card" style="margin-bottom: 24px; background: var(--bg-tertiary);">
    <div style="display: flex; gap: 16px; align-items: start;">
        <div style="flex: 1;">
            <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px;">
                Original Article (<?= $config['languages'][$source_lang]['name'] ?>)
            </div>
            <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">
                <?= htmlspecialchars($source_article['frontmatter']['title'] ?? '') ?>
            </div>
            <div style="font-size: 13px; color: var(--text-secondary);">
                <?= htmlspecialchars($source_article['frontmatter']['description'] ?? '') ?>
            </div>
        </div>
        <a href="edit.php?file=<?= urlencode($translate_from) ?>&lang=<?= $source_lang ?>" 
           target="_blank" class="btn btn-secondary btn-sm">
            View Original
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div style="background: rgba(225, 29, 72, 0.1); border: 1px solid #e11d48; color: #e11d48; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- Create Form -->
<form method="POST" id="createForm">
    <input type="hidden" name="translationKey" value="<?= htmlspecialchars($source_article['frontmatter']['translationKey'] ?? '') ?>">
    
    <?php if (!$source_article): ?>
    <!-- Section & Category Selection -->
    <div class="card" style="margin-bottom: 24px;">
        <h3 class="card-title" style="margin-bottom: 16px;">Where should this article live?</h3>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Section *</label>
                <select name="section" id="sectionSelect" class="form-input" required>
                    <option value="">Select a section...</option>
                    <?php foreach ($sections as $key => $section): ?>
                    <option value="<?= $key ?>" <?= $preselected_section === $key ? 'selected' : '' ?>
                            data-color="<?= $section['color'] ?>">
                        <?= $section['name'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category" id="categorySelect" class="form-input">
                    <option value="">Select a category (optional)...</option>
                </select>
            </div>
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Language</label>
            <div class="lang-tabs" style="margin-bottom: 0;">
                <?php foreach ($config['languages'] as $lang => $lang_config): ?>
                <a href="?lang=<?= $lang ?><?= $preselected_section ? '&section=' . $preselected_section : '' ?>" 
                   class="lang-tab <?= $current_lang === $lang ? 'active' : '' ?>">
                    <span class="flag"><?= $lang_config['flag'] ?></span>
                    <?= $lang_config['name'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <input type="hidden" name="section" value="<?= htmlspecialchars(explode('/', $translate_from)[0]) ?>">
    <input type="hidden" name="category" value="<?= count(explode('/', $translate_from)) > 2 ? htmlspecialchars(explode('/', $translate_from)[1]) : '' ?>">
    <?php endif; ?>
    
    <!-- Article Content -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="form-group">
            <label class="form-label">Title *</label>
            <input type="text" name="title" class="form-input" required
                   style="font-size: 18px; font-weight: 600;"
                   placeholder="Enter article title..."
                   value="<?= htmlspecialchars($source_article['frontmatter']['title'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">Description *</label>
            <textarea name="description" class="form-input" rows="2" required
                      placeholder="Brief description for SEO and previews..."><?= htmlspecialchars($source_article['frontmatter']['description'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Content</label>
            <textarea name="body" id="editor" class="markdown-editor"><?= htmlspecialchars($source_article['body'] ?? '') ?></textarea>
        </div>
    </div>
    
    <!-- Additional Settings -->
    <div class="grid grid-3" style="gap: 24px;">
        <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">Publishing</h3>
            
            <div class="form-group">
                <label class="form-label">Author</label>
                <input type="text" name="author" class="form-input" 
                       value="<?= htmlspecialchars($source_article['frontmatter']['author'] ?? 'XloveCam Team') ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="draft" value="1"
                           style="width: 18px; height: 18px;">
                    <span>Save as draft</span>
                </label>
            </div>
        </div>
        
        <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">Featured Image</h3>
            
            <div class="form-group">
                <input type="text" name="image" id="imageInput" class="form-input" 
                       value="<?= htmlspecialchars($source_article['frontmatter']['image'] ?? '') ?>"
                       placeholder="/images/articles/...">
            </div>
            
            <button type="button" class="btn btn-secondary btn-sm" style="width: 100%;"
                    onclick="openMediaBrowser()">
                Browse Media
            </button>
        </div>
        
        <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">Tags</h3>
            
            <div class="form-group" style="margin-bottom: 0;">
                <div class="tags-container">
                    <?php 
                    $tags = $source_article['frontmatter']['tags'] ?? [];
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
    
    <!-- Submit -->
    <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
        <a href="articles.php?lang=<?= $current_lang ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Create Article
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

<script>
// Load categories when section changes
document.getElementById('sectionSelect')?.addEventListener('change', function() {
    const section = this.value;
    const categorySelect = document.getElementById('categorySelect');
    
    if (!section) {
        categorySelect.innerHTML = '<option value="">Select a category (optional)...</option>';
        return;
    }
    
    fetch('api.php?action=categories&section=' + section + '&lang=<?= $current_lang ?>')
        .then(r => r.json())
        .then(categories => {
            let html = '<option value="">No category (section root)</option>';
            Object.entries(categories).forEach(([slug, cat]) => {
                html += `<option value="${slug}">${cat.name} (${cat.count} articles)</option>`;
            });
            categorySelect.innerHTML = html;
        });
});

// Trigger initial load if section is preselected
<?php if ($preselected_section): ?>
document.getElementById('sectionSelect').dispatchEvent(new Event('change'));
<?php endif; ?>

// Media browser functions
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
            let html = '<div class="media-grid">';
            
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
            content.innerHTML = html;
        });
}

function selectMedia(path) {
    document.getElementById('imageInput').value = path;
    closeModal('mediaBrowserModal');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

