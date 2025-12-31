<?php
/**
 * Hugo Admin - Dashboard
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/content-types.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Dashboard';
$current_lang = $_GET['lang'] ?? 'en';

// Get stats
$sections = get_sections_with_types($current_lang);
$total_articles = array_sum(array_column($sections, 'count'));
$recent_articles = get_articles($current_lang);

// Add content type to recent articles
foreach ($recent_articles as &$article) {
    $article['content_type'] = detect_content_type($article['section'], $article['frontmatter']);
    $article['type_info'] = get_content_type($article['content_type']);
}
unset($article);

$recent_articles = array_slice($recent_articles, 0, 10);

// Get content stats by type
$content_by_type = get_content_stats_by_type($current_lang);

// Count translations
$translation_stats = [];
foreach ($config['languages'] as $lang => $lang_config) {
    $content_dir = get_content_dir_for_lang($lang);
    $translation_stats[$lang] = is_dir($content_dir) ? count(get_articles($lang)) : 0;
}

// Get standalone pages count
$pages_count = 0;
$content_dir = get_content_dir_for_lang($current_lang);
if (is_dir($content_dir)) {
    foreach (scandir($content_dir) as $item) {
        if ($item[0] === '.' || !is_dir($content_dir . '/' . $item)) continue;
        if (file_exists($content_dir . '/' . $item . '/_index.md')) {
            // Check if it's not a section (no child .md files other than _index.md)
            $has_children = false;
            foreach (scandir($content_dir . '/' . $item) as $child) {
                if ($child !== '_index.md' && pathinfo($child, PATHINFO_EXTENSION) === 'md') {
                    $has_children = true;
                    break;
                }
            }
            if (!$has_children) {
                $pages_count++;
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<style>
/* Content Type Cards */
.content-type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.content-type-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    text-decoration: none;
    transition: all 0.15s ease;
}

.content-type-card:hover {
    border-color: var(--text-muted);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.content-type-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.content-type-icon svg {
    width: 24px;
    height: 24px;
    color: white;
}

.content-type-info {
    flex: 1;
    min-width: 0;
}

.content-type-count {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
}

.content-type-label {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 4px;
}

/* Recent item with type badge */
.recent-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    text-decoration: none;
    transition: all 0.15s ease;
}

.recent-item:hover {
    border-color: var(--accent-primary);
    background: var(--bg-tertiary);
}

.recent-type-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    flex-shrink: 0;
}

.recent-type-badge svg {
    width: 16px;
    height: 16px;
    color: white;
}

.recent-content {
    flex: 1;
    min-width: 0;
}

.recent-title {
    font-weight: 500;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.recent-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 2px;
    font-size: 11px;
    color: var(--text-muted);
}

.recent-section {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.recent-section .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

/* Section cards enhanced */
.section-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    background: var(--bg-tertiary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    transition: all 0.15s ease;
}

.section-card:hover {
    background: var(--bg-hover);
}

.section-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.section-icon svg {
    width: 20px;
    height: 20px;
    opacity: 0.8;
}

.section-info {
    flex: 1;
}

.section-name {
    font-weight: 600;
    color: var(--text-primary);
}

.section-meta {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.section-count {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back! Here's an overview of your content.</p>
    </div>
    <a href="new.php" class="btn btn-primary">
        <?= pugo_icon('plus') ?>
        New Content
    </a>
</div>

<!-- Language Tabs -->
<div class="lang-tabs">
    <?php foreach ($config['languages'] as $lang => $lang_config): ?>
    <a href="?lang=<?= $lang ?>" class="lang-tab <?= $current_lang === $lang ? 'active' : '' ?>">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
        <span style="opacity: 0.5">(<?= $translation_stats[$lang] ?>)</span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Content Type Overview -->
<div class="content-type-grid">
    <!-- All Content -->
    <a href="articles.php?lang=<?= $current_lang ?>" class="content-type-card">
        <div class="content-type-icon" style="background: linear-gradient(135deg, #6b7280, #4b5563);">
            <?= pugo_icon('layers', 24) ?>
        </div>
        <div class="content-type-info">
            <div class="content-type-count"><?= $total_articles ?></div>
            <div class="content-type-label">Total Content</div>
        </div>
    </a>
    
    <!-- Pages -->
    <a href="pages.php?lang=<?= $current_lang ?>" class="content-type-card">
        <div class="content-type-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
            <?= pugo_icon('layout', 24) ?>
        </div>
        <div class="content-type-info">
            <div class="content-type-count"><?= $pages_count ?></div>
            <div class="content-type-label">Pages</div>
        </div>
    </a>
    
    <!-- Content Types -->
    <?php foreach ($content_by_type as $type_key => $type_data): ?>
    <a href="articles.php?lang=<?= $current_lang ?>&type=<?= $type_key ?>" class="content-type-card">
        <div class="content-type-icon" style="background: linear-gradient(135deg, <?= $type_data['type']['color'] ?>, <?= $type_data['type']['color'] ?>cc);">
            <?= pugo_icon($type_data['type']['icon'], 24) ?>
        </div>
        <div class="content-type-info">
            <div class="content-type-count"><?= $type_data['count'] ?></div>
            <div class="content-type-label"><?= $type_data['type']['plural'] ?></div>
        </div>
    </a>
    <?php endforeach; ?>
    
    <!-- Languages -->
    <div class="content-type-card" style="cursor: default;">
        <div class="content-type-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <?= pugo_icon('globe', 24) ?>
        </div>
        <div class="content-type-info">
            <div class="content-type-count"><?= count($config['languages']) ?></div>
            <div class="content-type-label">Languages</div>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="grid grid-2">
    <!-- Recent Content -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Content</h2>
            <a href="articles.php?lang=<?= $current_lang ?>" class="btn btn-secondary btn-sm">View All</a>
        </div>
        
        <?php if (empty($recent_articles)): ?>
        <div class="empty-state">
            <?= pugo_icon('file-text', 48) ?>
            <h3>No content yet</h3>
            <p>Create your first content to get started.</p>
        </div>
        <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <?php foreach (array_slice($recent_articles, 0, 6) as $article): ?>
            <a href="edit.php?file=<?= urlencode($article['relative_path']) ?>&lang=<?= $current_lang ?>" class="recent-item">
                <div class="recent-type-badge" style="background: <?= $article['type_info']['color'] ?>;">
                    <?= pugo_icon($article['type_info']['icon'], 16) ?>
                </div>
                <div class="recent-content">
                    <div class="recent-title"><?= htmlspecialchars($article['frontmatter']['title'] ?? $article['filename']) ?></div>
                    <div class="recent-meta">
                        <span class="recent-section">
                            <span class="dot" style="background: <?= $sections[$article['section']]['color'] ?? '#666' ?>;"></span>
                            <?= $article['section'] ?>
                        </span>
                        <span>·</span>
                        <span><?= time_ago($article['modified']) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sections Overview -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Sections</h2>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($sections as $key => $section): ?>
            <a href="articles.php?section=<?= $key ?>&lang=<?= $current_lang ?>" class="section-card">
                <div class="section-icon" style="background: <?= $section['color'] ?>20; color: <?= $section['color'] ?>;">
                    <?= pugo_icon($section['type_icon'] ?? 'folder', 20) ?>
                </div>
                <div class="section-info">
                    <div class="section-name"><?= $section['name'] ?></div>
                    <div class="section-meta"><?= $section['type_name'] ?? 'Content' ?></div>
                </div>
                <div class="section-count"><?= $section['count'] ?></div>
            </a>
            <?php endforeach; ?>
            
            <?php if (empty($sections)): ?>
            <div class="empty-state" style="padding: 30px;">
                <p style="color: var(--text-muted);">No sections found</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Translation Status -->
<?php if (count($config['languages']) > 1): ?>
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h2 class="card-title">Translation Coverage</h2>
    </div>
    
    <div class="translation-grid">
        <?php foreach ($config['languages'] as $lang => $lang_config): ?>
        <div class="translation-item <?= $translation_stats[$lang] > 0 ? 'exists' : 'missing' ?>">
            <span class="flag"><?= $lang_config['flag'] ?></span>
            <span class="lang-name"><?= $lang_config['name'] ?></span>
            <span class="status">
                <?= $translation_stats[$lang] ?> items
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
