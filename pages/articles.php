<?php
/**
 * Hugo Admin - Content Browser
 * 
 * Browse all content with content type detection and filtering.
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/content-types.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$current_lang = $_GET['lang'] ?? 'en';
$current_section = $_GET['section'] ?? null;
$current_type = $_GET['type'] ?? null;
$search = $_GET['search'] ?? '';

// Get sections with content type info
$sections = get_sections_with_types($current_lang);

// Get articles
$articles = get_articles($current_lang, $current_section);

// Add content type to each article
foreach ($articles as &$article) {
    $article['content_type'] = detect_content_type($article['section'], $article['frontmatter']);
    $article['type_info'] = get_content_type($article['content_type']);
}
unset($article);

// Filter by content type
if ($current_type) {
    $articles = array_filter($articles, fn($a) => $a['content_type'] === $current_type);
}

// Filter by search
if ($search) {
    $articles = array_filter($articles, function($article) use ($search) {
        $title = $article['frontmatter']['title'] ?? '';
        $desc = $article['frontmatter']['description'] ?? '';
        return stripos($title, $search) !== false || stripos($desc, $search) !== false;
    });
}

// Get content types that have items
$active_types = [];
$all_articles = get_articles($current_lang);
foreach ($all_articles as $art) {
    $type = detect_content_type($art['section'], $art['frontmatter']);
    if (!isset($active_types[$type])) {
        $active_types[$type] = ['info' => get_content_type($type), 'count' => 0];
    }
    $active_types[$type]['count']++;
}

// Build page title
if ($current_type && isset($active_types[$current_type])) {
    $page_title = $active_types[$current_type]['info']['plural'];
} elseif ($current_section && isset($sections[$current_section])) {
    $page_title = $sections[$current_section]['name'];
} else {
    $page_title = 'All Content';
}

require __DIR__ . '/../includes/header.php';
?>

<style>
/* Content Type Pills */
.content-type-pills {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.type-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s;
}

.type-pill:hover {
    background: var(--bg-tertiary);
    border-color: var(--text-muted);
    color: var(--text-primary);
}

.type-pill.active {
    border-color: var(--accent-primary);
    background: rgba(225, 29, 72, 0.1);
    color: var(--accent-primary);
}

.type-pill svg {
    width: 16px;
    height: 16px;
    opacity: 0.7;
}

.type-pill .count {
    background: var(--bg-tertiary);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    color: var(--text-muted);
}

.type-pill.active .count {
    background: rgba(225, 29, 72, 0.2);
    color: var(--accent-primary);
}

/* Content Type Badge in List */
.content-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.content-type-badge svg {
    width: 12px;
    height: 12px;
}

/* Section indicator */
.section-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: var(--bg-tertiary);
    border-radius: 4px;
    font-size: 12px;
    color: var(--text-muted);
}

.section-indicator .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

/* Article item enhancements */
.article-item-enhanced {
    display: grid;
    grid-template-columns: auto 1fr auto auto auto auto;
    gap: 16px;
    align-items: center;
    padding: 16px 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    text-decoration: none;
    transition: all 0.15s ease;
}

.article-item-enhanced:hover {
    border-color: var(--accent-primary);
    background: var(--bg-tertiary);
}

.article-content {
    min-width: 0;
}

.article-content .title {
    font-weight: 500;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.article-content .description {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 400px;
}

/* Summary cards */
.content-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    text-decoration: none;
    transition: all 0.15s;
}

.summary-card:hover {
    border-color: var(--text-muted);
    background: var(--bg-tertiary);
}

.summary-card.active {
    border-color: var(--accent-primary);
}

.summary-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
}

.summary-icon svg {
    width: 20px;
    height: 20px;
    color: white;
}

.summary-info .count {
    font-size: 20px;
    font-weight: 700;
}

.summary-info .label {
    font-size: 12px;
    color: var(--text-muted);
}

@media (max-width: 900px) {
    .article-item-enhanced {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .article-content .description {
        max-width: 100%;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $page_title ?></h1>
        <p class="page-subtitle">
            <?= count($articles) ?> item<?= count($articles) !== 1 ? 's' : '' ?>
            <?php if ($current_section): ?>
                in <?= $sections[$current_section]['name'] ?? $current_section ?>
            <?php endif; ?>
            <?php if ($current_type && isset($active_types[$current_type])): ?>
                · <?= $active_types[$current_type]['info']['name'] ?>
            <?php endif; ?>
        </p>
    </div>
    <a href="new.php?section=<?= $current_section ?>&lang=<?= $current_lang ?>" class="btn btn-primary">
        <?= pugo_icon('plus') ?>
        New Content
    </a>
</div>

<!-- Language Tabs -->
<div class="lang-tabs">
    <?php foreach ($config['languages'] as $lang => $lang_config): ?>
    <a href="?lang=<?= $lang ?><?= $current_section ? '&section=' . $current_section : '' ?><?= $current_type ? '&type=' . $current_type : '' ?>" 
       class="lang-tab <?= $current_lang === $lang ? 'active' : '' ?>">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Content Type Summary Cards -->
<?php if (count($active_types) > 1): ?>
<div class="content-summary">
    <a href="?lang=<?= $current_lang ?><?= $current_section ? '&section=' . $current_section : '' ?>" 
       class="summary-card <?= !$current_type ? 'active' : '' ?>">
        <div class="summary-icon" style="background: linear-gradient(135deg, #6b7280, #4b5563);">
            <?= pugo_icon('layers', 20) ?>
        </div>
        <div class="summary-info">
            <div class="count"><?= count($all_articles) ?></div>
            <div class="label">All Content</div>
        </div>
    </a>
    
    <?php foreach ($active_types as $type_key => $type_data): ?>
    <a href="?lang=<?= $current_lang ?>&type=<?= $type_key ?><?= $current_section ? '&section=' . $current_section : '' ?>" 
       class="summary-card <?= $current_type === $type_key ? 'active' : '' ?>">
        <div class="summary-icon" style="background: linear-gradient(135deg, <?= $type_data['info']['color'] ?>, <?= $type_data['info']['color'] ?>cc);">
            <?= pugo_icon($type_data['info']['icon'], 20) ?>
        </div>
        <div class="summary-info">
            <div class="count"><?= $type_data['count'] ?></div>
            <div class="label"><?= $type_data['info']['plural'] ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filters & Search -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <form method="GET" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="lang" value="<?= $current_lang ?>">
        <?php if ($current_type): ?>
        <input type="hidden" name="type" value="<?= $current_type ?>">
        <?php endif; ?>
        
        <select name="section" class="form-input" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
            <option value="">All Sections</option>
            <?php foreach ($sections as $key => $section): ?>
            <option value="<?= $key ?>" <?= $current_section === $key ? 'selected' : '' ?>>
                <?= $section['name'] ?> (<?= $section['count'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
        
        <div style="flex: 1; position: relative;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   class="form-input" placeholder="Search content..." 
                   style="padding-left: 40px;">
            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); opacity: 0.4;">
                <?= pugo_icon('search') ?>
            </span>
        </div>
        
        <button type="submit" class="btn btn-secondary">Search</button>
        
        <?php if ($search || $current_section || $current_type): ?>
        <a href="articles.php?lang=<?= $current_lang ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Content List -->
<?php if (empty($articles)): ?>
<div class="card">
    <div class="empty-state">
        <?= pugo_icon('file-text', 48) ?>
        <h3>No content found</h3>
        <p>
            <?php if ($search): ?>
                No items match your search "<?= htmlspecialchars($search) ?>"
            <?php elseif ($current_type): ?>
                No <?= strtolower($active_types[$current_type]['info']['plural'] ?? 'items') ?> yet
            <?php elseif ($current_section): ?>
                No content in this section yet
            <?php else: ?>
                Create your first content to get started
            <?php endif; ?>
        </p>
        <a href="new.php?section=<?= $current_section ?>&lang=<?= $current_lang ?>" class="btn btn-primary" style="margin-top: 16px;">
            Create Content
        </a>
    </div>
</div>
<?php else: ?>
<div class="article-list">
    <?php foreach ($articles as $article): 
        $type_info = $article['type_info'];
        $translation_key = $article['frontmatter']['translationKey'] ?? null;
        $translations = $translation_key ? get_translation_status($translation_key) : [];
    ?>
    <a href="edit.php?file=<?= urlencode($article['relative_path']) ?>&lang=<?= $current_lang ?>" class="article-item-enhanced">
        <!-- Content Type Badge -->
        <span class="content-type-badge" style="background: <?= $type_info['color'] ?>15; color: <?= $type_info['color'] ?>;">
            <?= pugo_icon($type_info['icon'], 12) ?>
            <?= $type_info['name'] ?>
        </span>
        
        <!-- Title & Description -->
        <div class="article-content">
            <div class="title"><?= htmlspecialchars($article['frontmatter']['title'] ?? $article['filename']) ?></div>
            <?php if (!empty($article['frontmatter']['description'])): ?>
            <div class="description"><?= htmlspecialchars($article['frontmatter']['description']) ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Section -->
        <span class="section-indicator">
            <span class="dot" style="background: <?= $sections[$article['section']]['color'] ?? '#666' ?>;"></span>
            <?= $article['section'] ?>
        </span>
        
        <!-- Category (if any) -->
        <?php if ($article['category']): ?>
        <span style="font-size: 11px; color: var(--text-muted); background: var(--bg-tertiary); padding: 3px 8px; border-radius: 4px;">
            <?= ucwords(str_replace('-', ' ', $article['category'])) ?>
        </span>
        <?php endif; ?>
        
        <!-- Translation indicators -->
        <div class="article-langs">
            <?php foreach ($config['languages'] as $lang => $lang_config): 
                $exists = $lang === $current_lang || (isset($translations[$lang]) && $translations[$lang]['exists']);
            ?>
            <span class="<?= $exists ? '' : 'missing' ?>" title="<?= $lang_config['name'] ?>">
                <?= $lang_config['flag'] ?>
            </span>
            <?php endforeach; ?>
        </div>
        
        <!-- Modified time -->
        <span class="article-meta"><?= time_ago($article['modified']) ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
