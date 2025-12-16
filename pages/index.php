<?php
/**
 * Hugo Admin - Dashboard
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Dashboard';
$current_lang = $_GET['lang'] ?? 'en';

// Get stats
$sections = get_sections_with_counts($current_lang);
$total_articles = array_sum(array_column($sections, 'count'));
$recent_articles = get_articles($current_lang);
$recent_articles = array_slice($recent_articles, 0, 10);

// Count translations
$translation_stats = [];
foreach ($config['languages'] as $lang => $lang_config) {
    $content_dir = $lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . $lang_config['content_dir'];
    $translation_stats[$lang] = is_dir($content_dir) ? count(get_articles($lang)) : 0;
}

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back! Here's an overview of your help center.</p>
    </div>
    <a href="new.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Article
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

<!-- Stats Grid -->
<div class="grid grid-4" style="margin-bottom: 32px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= $total_articles ?></div>
            <div class="stat-label">Total Articles</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= count($recent_articles) ?></div>
            <div class="stat-label">Recent Updates</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= count($config['languages']) ?></div>
            <div class="stat-label">Languages</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= count($sections) ?></div>
            <div class="stat-label">Sections</div>
        </div>
    </div>
</div>

<!-- Content Grid -->
<div class="grid grid-2">
    <!-- Recent Articles -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Articles</h2>
            <a href="articles.php?lang=<?= $current_lang ?>" class="btn btn-secondary btn-sm">View All</a>
        </div>
        
        <?php if (empty($recent_articles)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            </svg>
            <h3>No articles yet</h3>
            <p>Create your first article to get started.</p>
        </div>
        <?php else: ?>
        <div class="article-list">
            <?php foreach (array_slice($recent_articles, 0, 5) as $article): ?>
            <a href="edit.php?file=<?= urlencode($article['relative_path']) ?>&lang=<?= $current_lang ?>" class="article-item">
                <span class="article-section-badge" style="background: <?= $sections[$article['section']]['color'] ?? '#666' ?>20; color: <?= $sections[$article['section']]['color'] ?? '#666' ?>;">
                    <?= $article['section'] ?>
                </span>
                <span class="article-title"><?= htmlspecialchars($article['frontmatter']['title'] ?? $article['filename']) ?></span>
                <span class="article-meta"><?= time_ago($article['modified']) ?></span>
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
        
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($sections as $key => $section): ?>
            <a href="articles.php?section=<?= $key ?>&lang=<?= $current_lang ?>" class="article-item" style="text-decoration: none;">
                <span style="width: 40px; height: 40px; background: <?= $section['color'] ?>20; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <span style="width: 12px; height: 12px; background: <?= $section['color'] ?>; border-radius: 50%;"></span>
                </span>
                <span class="article-title"><?= $section['name'] ?></span>
                <span class="article-meta"><?= $section['count'] ?> articles</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Translation Status -->
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
                <?= $translation_stats[$lang] ?> articles
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
