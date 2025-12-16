<?php
/**
 * Hugo Admin - Articles List
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$current_lang = $_GET['lang'] ?? 'en';
$current_section = $_GET['section'] ?? null;
$search = $_GET['search'] ?? '';

$page_title = $current_section ? ucfirst($current_section) . ' Articles' : 'All Articles';

// Get articles
$articles = get_articles($current_lang, $current_section);

// Filter by search
if ($search) {
    $articles = array_filter($articles, function($article) use ($search) {
        $title = $article['frontmatter']['title'] ?? '';
        $desc = $article['frontmatter']['description'] ?? '';
        return stripos($title, $search) !== false || stripos($desc, $search) !== false;
    });
}

$sections = get_sections_with_counts($current_lang);

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= $page_title ?></h1>
        <p class="page-subtitle">
            <?= count($articles) ?> article<?= count($articles) !== 1 ? 's' : '' ?>
            <?= $current_section ? 'in ' . $sections[$current_section]['name'] : '' ?>
        </p>
    </div>
    <a href="new.php?section=<?= $current_section ?>&lang=<?= $current_lang ?>" class="btn btn-primary">
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
    <a href="?lang=<?= $lang ?><?= $current_section ? '&section=' . $current_section : '' ?>" 
       class="lang-tab <?= $current_lang === $lang ? 'active' : '' ?>">
        <span class="flag"><?= $lang_config['flag'] ?></span>
        <?= $lang_config['name'] ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filters & Search -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <form method="GET" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="lang" value="<?= $current_lang ?>">
        
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
                   class="form-input" placeholder="Search articles..." 
                   style="padding-left: 40px;">
            <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; opacity: 0.4;" 
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
            </svg>
        </div>
        
        <button type="submit" class="btn btn-secondary">Search</button>
        
        <?php if ($search || $current_section): ?>
        <a href="articles.php?lang=<?= $current_lang ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Articles List -->
<?php if (empty($articles)): ?>
<div class="card">
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
        </svg>
        <h3>No articles found</h3>
        <p>
            <?php if ($search): ?>
                No articles match your search "<?= htmlspecialchars($search) ?>"
            <?php elseif ($current_section): ?>
                No articles in this section yet
            <?php else: ?>
                Create your first article to get started
            <?php endif; ?>
        </p>
        <a href="new.php?section=<?= $current_section ?>&lang=<?= $current_lang ?>" class="btn btn-primary" style="margin-top: 16px;">
            Create Article
        </a>
    </div>
</div>
<?php else: ?>
<div class="article-list">
    <?php foreach ($articles as $article): 
        // Get translation status
        $translation_key = $article['frontmatter']['translationKey'] ?? null;
        $translations = $translation_key ? get_translation_status($translation_key) : [];
    ?>
    <a href="edit.php?file=<?= urlencode($article['relative_path']) ?>&lang=<?= $current_lang ?>" class="article-item">
        <span class="article-section-badge" style="background: <?= $sections[$article['section']]['color'] ?? '#666' ?>20; color: <?= $sections[$article['section']]['color'] ?? '#666' ?>;">
            <?= $article['section'] ?>
        </span>
        
        <div style="flex: 1;">
            <div class="article-title"><?= htmlspecialchars($article['frontmatter']['title'] ?? $article['filename']) ?></div>
            <?php if (!empty($article['frontmatter']['description'])): ?>
            <div style="font-size: 13px; color: var(--text-secondary); margin-top: 4px; max-width: 500px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= htmlspecialchars($article['frontmatter']['description']) ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($article['category']): ?>
        <span style="font-size: 12px; color: var(--text-muted); background: var(--bg-tertiary); padding: 4px 10px; border-radius: 4px;">
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
        
        <span class="article-meta"><?= time_ago($article['modified']) ?></span>
        
        <svg style="width: 18px; height: 18px; opacity: 0.3;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

