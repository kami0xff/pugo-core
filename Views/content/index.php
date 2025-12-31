<?php
/**
 * Content Index View
 * 
 * Lists all content with filtering by section and content type.
 * 
 * Variables:
 * - $pageTitle: string
 * - $articles: array
 * - $sections: array
 * - $activeTypes: array
 * - $currentSection: string|null
 * - $currentType: string|null
 * - $search: string
 * - $config: array
 * - $currentLang: string
 */
?>

<style>
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
.summary-card:hover { border-color: var(--text-muted); background: var(--bg-tertiary); }
.summary-card.active { border-color: var(--accent-primary); }
.summary-icon {
    width: 40px; height: 40px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
}
.summary-icon svg { width: 20px; height: 20px; color: white; }
.summary-info .count { font-size: 20px; font-weight: 700; }
.summary-info .label { font-size: 12px; color: var(--text-muted); }
.content-type-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 8px; border-radius: 4px;
    font-size: 10px; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap;
}
.content-type-badge svg { width: 12px; height: 12px; }
.section-indicator {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; background: var(--bg-tertiary);
    border-radius: 4px; font-size: 12px; color: var(--text-muted);
}
.section-indicator .dot { width: 8px; height: 8px; border-radius: 50%; }
.article-item-enhanced {
    display: grid;
    grid-template-columns: auto 1fr auto auto auto auto;
    gap: 16px; align-items: center;
    padding: 16px 20px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    text-decoration: none;
    transition: all 0.15s ease;
}
.article-item-enhanced:hover { border-color: var(--accent-primary); background: var(--bg-tertiary); }
.article-content { min-width: 0; }
.article-content .title {
    font-weight: 500; color: var(--text-primary);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.article-content .description {
    font-size: 13px; color: var(--text-secondary);
    margin-top: 4px; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; max-width: 400px;
}
@media (max-width: 900px) {
    .article-item-enhanced { grid-template-columns: 1fr; gap: 12px; }
    .article-content .description { max-width: 100%; }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="page-subtitle">
            <?= count($articles) ?> item<?= count($articles) !== 1 ? 's' : '' ?>
            <?php if ($currentSection && isset($sections[$currentSection])): ?>
                in <?= htmlspecialchars($sections[$currentSection]['name']) ?>
            <?php endif; ?>
        </p>
    </div>
    <a href="new.php?section=<?= urlencode($currentSection ?? '') ?>&lang=<?= urlencode($currentLang) ?>" class="btn btn-primary">
        <?= pugo_icon('plus') ?> New Content
    </a>
</div>

<!-- Language Tabs -->
<div class="lang-tabs">
    <?php foreach ($config['languages'] as $lang => $langConfig): ?>
    <a href="?lang=<?= urlencode($lang) ?><?= $currentSection ? '&section=' . urlencode($currentSection) : '' ?><?= $currentType ? '&type=' . urlencode($currentType) : '' ?>" 
       class="lang-tab <?= $currentLang === $lang ? 'active' : '' ?>">
        <span class="flag"><?= $langConfig['flag'] ?></span>
        <?= htmlspecialchars($langConfig['name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Content Type Summary Cards -->
<?php if (count($activeTypes) > 1): ?>
<div class="content-summary">
    <a href="?lang=<?= urlencode($currentLang) ?><?= $currentSection ? '&section=' . urlencode($currentSection) : '' ?>" 
       class="summary-card <?= !$currentType ? 'active' : '' ?>">
        <div class="summary-icon" style="background: linear-gradient(135deg, #6b7280, #4b5563);">
            <?= pugo_icon('layers', 20) ?>
        </div>
        <div class="summary-info">
            <div class="count"><?= count($articles) ?></div>
            <div class="label">All Content</div>
        </div>
    </a>
    <?php foreach ($activeTypes as $typeKey => $typeData): ?>
    <a href="?lang=<?= urlencode($currentLang) ?>&type=<?= urlencode($typeKey) ?><?= $currentSection ? '&section=' . urlencode($currentSection) : '' ?>" 
       class="summary-card <?= $currentType === $typeKey ? 'active' : '' ?>">
        <div class="summary-icon" style="background: linear-gradient(135deg, <?= $typeData['info']['color'] ?>, <?= $typeData['info']['color'] ?>cc);">
            <?= pugo_icon($typeData['info']['icon'], 20) ?>
        </div>
        <div class="summary-info">
            <div class="count"><?= $typeData['count'] ?></div>
            <div class="label"><?= htmlspecialchars($typeData['info']['plural']) ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filters & Search -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <form method="GET" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="lang" value="<?= htmlspecialchars($currentLang) ?>">
        <?php if ($currentType): ?><input type="hidden" name="type" value="<?= htmlspecialchars($currentType) ?>"><?php endif; ?>
        <select name="section" class="form-input" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
            <option value="">All Sections</option>
            <?php foreach ($sections as $key => $section): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= $currentSection === $key ? 'selected' : '' ?>>
                <?= htmlspecialchars($section['name']) ?> (<?= $section['count'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <div style="flex: 1; position: relative;">
            <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" class="form-input" placeholder="Search content..." style="padding-left: 40px;">
            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); opacity: 0.4;"><?= pugo_icon('search') ?></span>
        </div>
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if (($search ?? '') || $currentSection || $currentType): ?>
        <a href="articles.php?lang=<?= urlencode($currentLang) ?>" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Content List -->
<?php if (empty($articles)): ?>
<div class="card">
    <div class="empty-state">
        <?= pugo_icon('file-text', 48) ?>
        <h3>No content found</h3>
        <p>Create your first content to get started</p>
        <a href="new.php?lang=<?= urlencode($currentLang) ?>" class="btn btn-primary" style="margin-top: 16px;">Create Content</a>
    </div>
</div>
<?php else: ?>
<div class="article-list">
    <?php foreach ($articles as $article): 
        $typeInfo = $article['type_info'];
        $translationKey = $article['frontmatter']['translationKey'] ?? null;
        $translations = $translationKey ? get_translation_status($translationKey) : [];
    ?>
    <a href="edit.php?file=<?= urlencode($article['relative_path']) ?>&lang=<?= urlencode($currentLang) ?>" class="article-item-enhanced">
        <span class="content-type-badge" style="background: <?= $typeInfo['color'] ?>15; color: <?= $typeInfo['color'] ?>;">
            <?= pugo_icon($typeInfo['icon'], 12) ?> <?= htmlspecialchars($typeInfo['name']) ?>
        </span>
        <div class="article-content">
            <div class="title"><?= htmlspecialchars($article['frontmatter']['title'] ?? $article['filename']) ?></div>
            <?php if (!empty($article['frontmatter']['description'])): ?>
            <div class="description"><?= htmlspecialchars($article['frontmatter']['description']) ?></div>
            <?php endif; ?>
        </div>
        <span class="section-indicator">
            <span class="dot" style="background: <?= $sections[$article['section']]['color'] ?? '#666' ?>;"></span>
            <?= htmlspecialchars($article['section']) ?>
        </span>
        <?php if ($article['category']): ?>
        <span style="font-size: 11px; color: var(--text-muted); background: var(--bg-tertiary); padding: 3px 8px; border-radius: 4px;">
            <?= htmlspecialchars(ucwords(str_replace('-', ' ', $article['category']))) ?>
        </span>
        <?php endif; ?>
        <div class="article-langs">
            <?php foreach ($config['languages'] as $lang => $langConfig): 
                $exists = $lang === $currentLang || (isset($translations[$lang]) && $translations[$lang]['exists']);
            ?>
            <span class="<?= $exists ? '' : 'missing' ?>" title="<?= htmlspecialchars($langConfig['name']) ?>"><?= $langConfig['flag'] ?></span>
            <?php endforeach; ?>
        </div>
        <span class="article-meta"><?= time_ago($article['modified']) ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
