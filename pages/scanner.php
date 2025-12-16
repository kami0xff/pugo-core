<?php
/**
 * Hugo Admin - Project Scanner
 * Opinionated scanner for XloveCam Help Center Hugo project
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Project Scanner';

// Run the scan
$issues = [];
$warnings = [];
$info = [];
$stats = [
    'articles_scanned' => 0,
    'images_scanned' => 0,
    'data_files_scanned' => 0,
];

// ============================================================================
// OPINIONATED RULES FOR XLOVECAM HELP CENTER
// ============================================================================

// Discover sections dynamically from content folder
$discovered_sections = array_keys(discover_sections());

$RULES = [
    // Required frontmatter fields
    'required_frontmatter' => ['title', 'description'],
    
    // Recommended frontmatter fields
    'recommended_frontmatter' => ['date', 'author', 'translationKey'],
    
    // Valid sections - dynamically discovered from content folder
    'valid_sections' => $discovered_sections,
    
    // Image folder structure: /static/images/articles/{section}/{category}/{article-slug}/
    'image_base_path' => '/static/images/articles',
    
    // Max description length for SEO
    'max_description_length' => 160,
    
    // Max image file size (500KB)
    'max_image_size' => 500 * 1024,
    
    // Valid image extensions
    'valid_image_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'],
    
    // Files that should exist in each section
    'required_section_files' => ['_index.md'],
    
    // Files that should exist in each category
    'required_category_files' => ['_index.md'],
];

/**
 * Get the expected image folder for an article
 */
function get_expected_image_folder($article_path) {
    // article_path example: users/getting-started/create-account.md
    // Expected image folder: /images/articles/users/getting-started/create-account/
    
    $path_without_ext = preg_replace('/\.md$/', '', $article_path);
    return '/images/articles/' . $path_without_ext . '/';
}

/**
 * Scan all content files
 */
function scan_content() {
    global $config, $issues, $warnings, $info, $stats, $RULES;
    
    foreach ($config['languages'] as $lang => $lang_config) {
        $content_dir = $lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . $lang_config['content_dir'];
        
        if (!is_dir($content_dir)) {
            if ($lang !== 'en') {
                $info[] = [
                    'type' => 'missing_language',
                    'message' => "Content directory missing for {$lang_config['name']}",
                    'path' => $lang_config['content_dir'],
                    'fix' => "Create directory: {$lang_config['content_dir']}/ when you need translations"
                ];
            }
            continue;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $stats['articles_scanned']++;
                $path = $file->getPathname();
                $relative_path = str_replace($content_dir . '/', '', $path);
                $full_relative = str_replace(HUGO_ROOT . '/', '', $path);
                
                // Parse path parts
                $path_parts = explode('/', $relative_path);
                $section = $path_parts[0] ?? '';
                $category = count($path_parts) > 2 ? $path_parts[1] : null;
                $filename = $file->getFilename();
                
                // ===== FILENAME CONVENTIONS =====
                if ($filename !== '_index.md') {
                    // Check lowercase
                    if (preg_match('/[A-Z]/', $filename)) {
                        $issues[] = [
                            'type' => 'naming',
                            'severity' => 'error',
                            'message' => 'Filename must be lowercase',
                            'path' => $full_relative,
                            'fix' => 'Rename to: ' . strtolower($filename)
                        ];
                    }
                    // Check for spaces or underscores
                    if (preg_match('/[_ ]/', $filename)) {
                        $issues[] = [
                            'type' => 'naming',
                            'severity' => 'error',
                            'message' => 'Filename must use hyphens, not spaces or underscores',
                            'path' => $full_relative,
                            'fix' => 'Rename to: ' . preg_replace('/[_ ]/', '-', $filename)
                        ];
                    }
                }
                
                // ===== SECTION VALIDATION =====
                if ($lang === 'en' && !in_array($section, $RULES['valid_sections']) && $section !== '') {
                    $warnings[] = [
                        'type' => 'structure',
                        'message' => "Unknown section: '$section' (expected: " . implode(', ', $RULES['valid_sections']) . ")",
                        'path' => $full_relative,
                        'fix' => 'Move to a valid section or add section to config'
                    ];
                }
                
                // ===== PARSE AND VALIDATE CONTENT =====
                $content = file_get_contents($path);
                $parsed = parse_frontmatter($content);
                $fm = $parsed['frontmatter'];
                
                // Required frontmatter
                foreach ($RULES['required_frontmatter'] as $field) {
                    if (empty($fm[$field])) {
                        $issues[] = [
                            'type' => 'frontmatter',
                            'severity' => 'error',
                            'message' => "Missing required field: $field",
                            'path' => $full_relative,
                            'fix' => "Add '$field' to frontmatter"
                        ];
                    }
                }
                
                // Description length
                if (!empty($fm['description']) && strlen($fm['description']) > $RULES['max_description_length']) {
                    $warnings[] = [
                        'type' => 'seo',
                        'message' => 'Description too long (' . strlen($fm['description']) . ' chars, max ' . $RULES['max_description_length'] . ')',
                        'path' => $full_relative,
                        'fix' => 'Shorten description for better SEO'
                    ];
                }
                
                // Recommended frontmatter (for non-index files)
                if ($filename !== '_index.md') {
                    foreach ($RULES['recommended_frontmatter'] as $field) {
                        if (empty($fm[$field])) {
                            $info[] = [
                                'type' => 'frontmatter',
                                'message' => "Missing recommended field: $field",
                                'path' => $full_relative,
                                'fix' => "Consider adding '$field' to frontmatter"
                            ];
                        }
                    }
                }
                
                // Translation key for non-English
                if ($lang !== 'en' && $filename !== '_index.md' && empty($fm['translationKey'])) {
                    $issues[] = [
                        'type' => 'translation',
                        'severity' => 'error',
                        'message' => 'Missing translationKey (required for translations)',
                        'path' => $full_relative,
                        'fix' => 'Add translationKey matching the English article'
                    ];
                }
                
                // ===== FEATURED IMAGE VALIDATION =====
                if (!empty($fm['image'])) {
                    $image_path = STATIC_DIR . $fm['image'];
                    if (!file_exists($image_path)) {
                        $issues[] = [
                            'type' => 'image',
                            'severity' => 'error',
                            'message' => 'Featured image not found',
                            'path' => $full_relative,
                            'detail' => $fm['image'],
                            'fix' => 'Upload image or fix path'
                        ];
                    }
                }
                
                // ===== IMAGE REFERENCES IN CONTENT =====
                preg_match_all('/!\[.*?\]\((\/images\/[^)]+)\)/', $parsed['body'], $matches);
                foreach ($matches[1] as $img_path) {
                    $full_img_path = STATIC_DIR . $img_path;
                    if (!file_exists($full_img_path)) {
                        $issues[] = [
                            'type' => 'image',
                            'severity' => 'error',
                            'message' => 'Referenced image not found in content',
                            'path' => $full_relative,
                            'detail' => $img_path,
                            'fix' => 'Upload image or fix path'
                        ];
                    } else {
                        // Check if image is in the correct folder structure
                        $expected_folder = get_expected_image_folder($relative_path);
                        if (strpos($img_path, $expected_folder) !== 0 && strpos($img_path, '/images/shared/') !== 0) {
                            $warnings[] = [
                                'type' => 'structure',
                                'message' => 'Image not in expected folder',
                                'path' => $full_relative,
                                'detail' => "Found: $img_path",
                                'fix' => "Move image to: $expected_folder"
                            ];
                        }
                    }
                }
            }
        }
    }
}

/**
 * Check section and category structure
 */
function scan_structure() {
    global $issues, $warnings, $info, $RULES;
    
    foreach ($RULES['valid_sections'] as $section) {
        $section_path = CONTENT_DIR . '/' . $section;
        
        // Check section exists
        if (!is_dir($section_path)) {
            $info[] = [
                'type' => 'structure',
                'message' => "Section '$section' directory does not exist",
                'path' => "content/$section/",
                'fix' => 'Create section directory if needed'
            ];
            continue;
        }
        
        // Check _index.md exists
        if (!file_exists($section_path . '/_index.md')) {
            $issues[] = [
                'type' => 'structure',
                'severity' => 'error',
                'message' => "Section '$section' missing _index.md",
                'path' => "content/$section/_index.md",
                'fix' => 'Create _index.md with title and description'
            ];
        }
        
        // Check categories
        foreach (scandir($section_path) as $item) {
            if ($item[0] === '.') continue;
            $item_path = $section_path . '/' . $item;
            
            if (is_dir($item_path)) {
                // This is a category
                if (!file_exists($item_path . '/_index.md')) {
                    $warnings[] = [
                        'type' => 'structure',
                        'message' => "Category '$item' missing _index.md",
                        'path' => "content/$section/$item/_index.md",
                        'fix' => 'Create _index.md with title and description'
                    ];
                }
                
                // Check category name conventions
                if (preg_match('/[A-Z_ ]/', $item)) {
                    $issues[] = [
                        'type' => 'naming',
                        'severity' => 'error',
                        'message' => "Category folder name invalid: '$item'",
                        'path' => "content/$section/$item/",
                        'fix' => 'Rename to: ' . strtolower(preg_replace('/[_ ]/', '-', $item))
                    ];
                }
            }
        }
    }
}

/**
 * Scan images for issues
 */
function scan_images() {
    global $issues, $warnings, $info, $stats, $RULES;
    
    $articles_images_dir = IMAGES_DIR . '/articles';
    
    if (!is_dir($articles_images_dir)) {
        $info[] = [
            'type' => 'structure',
            'message' => 'Articles image directory does not exist',
            'path' => 'static/images/articles/',
            'fix' => 'Create the directory structure for article images'
        ];
        return;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($articles_images_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $stats['images_scanned']++;
            $path = $file->getPathname();
            $relative_path = str_replace(HUGO_ROOT . '/', '', $path);
            $ext = strtolower($file->getExtension());
            $filename = $file->getFilename();
            
            // Check valid extension
            if (!in_array($ext, $RULES['valid_image_extensions'])) {
                $issues[] = [
                    'type' => 'image',
                    'severity' => 'error',
                    'message' => "Invalid file type in images folder: .$ext",
                    'path' => $relative_path,
                    'fix' => 'Remove file or use valid format: ' . implode(', ', $RULES['valid_image_extensions'])
                ];
                continue;
            }
            
            // Check filename conventions
            if (preg_match('/[A-Z]/', $filename)) {
                $issues[] = [
                    'type' => 'naming',
                    'severity' => 'error',
                    'message' => 'Image filename must be lowercase',
                    'path' => $relative_path,
                    'fix' => 'Rename to: ' . strtolower($filename)
                ];
            }
            
            if (preg_match('/[_ ]/', pathinfo($filename, PATHINFO_FILENAME))) {
                $issues[] = [
                    'type' => 'naming',
                    'severity' => 'error',
                    'message' => 'Image filename must use hyphens, not spaces or underscores',
                    'path' => $relative_path,
                    'fix' => 'Rename using hyphens instead'
                ];
            }
            
            // Check file size
            $size = $file->getSize();
            if ($size > $RULES['max_image_size']) {
                $warnings[] = [
                    'type' => 'optimization',
                    'message' => 'Large image file (' . format_size($size) . ', max ' . format_size($RULES['max_image_size']) . ')',
                    'path' => $relative_path,
                    'fix' => 'Compress or resize image for better performance'
                ];
            }
        }
    }
    
    // Also scan shared images
    $shared_images_dir = IMAGES_DIR . '/shared';
    if (is_dir($shared_images_dir)) {
        $shared_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($shared_images_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($shared_iterator as $file) {
            if ($file->isFile()) {
                $stats['images_scanned']++;
            }
        }
    }
}

/**
 * Check image folder structure matches article structure
 */
function scan_image_structure() {
    global $config, $warnings, $info, $RULES;
    
    // Get all articles with images referenced
    $articles_with_images = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CONTENT_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md' && $file->getFilename() !== '_index.md') {
            $content = file_get_contents($file->getPathname());
            $parsed = parse_frontmatter($content);
            
            // Check if article has images
            preg_match_all('/!\[.*?\]\((\/images\/articles\/[^)]+)\)/', $parsed['body'], $matches);
            if (!empty($matches[1]) || !empty($parsed['frontmatter']['image'])) {
                $relative_path = str_replace(CONTENT_DIR . '/', '', $file->getPathname());
                $expected_folder = get_expected_image_folder($relative_path);
                $full_expected = STATIC_DIR . $expected_folder;
                
                // Check if expected folder exists
                if (!is_dir($full_expected) && !empty($matches[1])) {
                    $info[] = [
                        'type' => 'structure',
                        'message' => 'Image folder not created for article with images',
                        'path' => 'static' . $expected_folder,
                        'fix' => "Create folder: static$expected_folder"
                    ];
                }
            }
        }
    }
}

/**
 * Check for orphaned images
 */
function scan_orphaned_images() {
    global $config, $info, $warnings;
    
    $articles_images_dir = IMAGES_DIR . '/articles';
    if (!is_dir($articles_images_dir)) return;
    
    // Get all images
    $all_images = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($articles_images_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $ext = strtolower($file->getExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
                $relative = str_replace(STATIC_DIR, '', $file->getPathname());
                $all_images[$relative] = false;
            }
        }
    }
    
    if (empty($all_images)) return;
    
    // Check all content for references
    foreach ($config['languages'] as $lang => $lang_config) {
        $content_dir = $lang === 'en' ? CONTENT_DIR : HUGO_ROOT . '/' . $lang_config['content_dir'];
        if (!is_dir($content_dir)) continue;
        
        $content_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($content_iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $content = file_get_contents($file->getPathname());
                foreach (array_keys($all_images) as $img) {
                    if (!$all_images[$img] && strpos($content, $img) !== false) {
                        $all_images[$img] = true;
                    }
                }
            }
        }
    }
    
    // Check data files too
    if (is_dir(DATA_DIR)) {
        foreach (scandir(DATA_DIR) as $file) {
            if (in_array(pathinfo($file, PATHINFO_EXTENSION), ['yaml', 'yml', 'json'])) {
                $content = file_get_contents(DATA_DIR . '/' . $file);
                foreach (array_keys($all_images) as $img) {
                    if (!$all_images[$img] && strpos($content, $img) !== false) {
                        $all_images[$img] = true;
                    }
                }
            }
        }
    }
    
    // Report orphaned
    $orphaned = array_keys(array_filter($all_images, function($used) { return !$used; }));
    if (count($orphaned) > 0) {
        if (count($orphaned) <= 10) {
            foreach ($orphaned as $img) {
                $warnings[] = [
                    'type' => 'orphaned',
                    'message' => 'Image not referenced anywhere',
                    'path' => 'static' . $img,
                    'fix' => 'Remove if not needed, or add reference in content'
                ];
            }
        } else {
            $warnings[] = [
                'type' => 'orphaned',
                'message' => count($orphaned) . ' images not referenced in any content',
                'path' => 'static/images/articles/',
                'fix' => 'Review and clean up unused images'
            ];
        }
    }
}

/**
 * Check translation coverage
 */
function scan_translations() {
    global $config, $info;
    
    // Get all English articles with translation keys
    $english_articles = [];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CONTENT_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'md' && $file->getFilename() !== '_index.md') {
            $content = file_get_contents($file->getPathname());
            $parsed = parse_frontmatter($content);
            $key = $parsed['frontmatter']['translationKey'] ?? null;
            if ($key) {
                $english_articles[$key] = str_replace(CONTENT_DIR . '/', '', $file->getPathname());
            }
        }
    }
    
    if (empty($english_articles)) return;
    
    // Check each language
    foreach ($config['languages'] as $lang => $lang_config) {
        if ($lang === 'en') continue;
        
        $content_dir = HUGO_ROOT . '/' . $lang_config['content_dir'];
        if (!is_dir($content_dir)) {
            $info[] = [
                'type' => 'translation',
                'message' => "{$lang_config['name']}: 0/" . count($english_articles) . " articles translated",
                'path' => $lang_config['content_dir'] . '/',
                'fix' => 'Create translations for your content'
            ];
            continue;
        }
        
        $translated_keys = [];
        $lang_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($content_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($lang_iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md' && $file->getFilename() !== '_index.md') {
                $content = file_get_contents($file->getPathname());
                $parsed = parse_frontmatter($content);
                if (!empty($parsed['frontmatter']['translationKey'])) {
                    $translated_keys[] = $parsed['frontmatter']['translationKey'];
                }
            }
        }
        
        $translated_count = count(array_intersect(array_keys($english_articles), $translated_keys));
        $total_count = count($english_articles);
        $percentage = $total_count > 0 ? round(($translated_count / $total_count) * 100) : 0;
        
        $info[] = [
            'type' => 'translation',
            'message' => "{$lang_config['name']}: $translated_count/$total_count articles translated ($percentage%)",
            'path' => $lang_config['content_dir'] . '/',
            'fix' => $translated_count < $total_count ? 'Create missing translations' : 'All articles translated!'
        ];
    }
}

/**
 * Check data files
 */
function scan_data_files() {
    global $issues, $warnings, $stats;
    
    if (!is_dir(DATA_DIR)) return;
    
    foreach (scandir(DATA_DIR) as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (!in_array($ext, ['yaml', 'yml', 'json'])) continue;
        if ($file === '.keep') continue;
        
        $stats['data_files_scanned']++;
        $path = DATA_DIR . '/' . $file;
        $relative = 'data/' . $file;
        $content = file_get_contents($path);
        
        // Check JSON validity
        if ($ext === 'json') {
            json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = [
                    'type' => 'data',
                    'severity' => 'error',
                    'message' => 'Invalid JSON: ' . json_last_error_msg(),
                    'path' => $relative,
                    'fix' => 'Fix JSON syntax error'
                ];
            }
        }
        
        // Check for broken image references
        preg_match_all('/(\/images\/[a-zA-Z0-9\/_.-]+\.(jpg|jpeg|png|gif|svg|webp))/', $content, $matches);
        foreach ($matches[1] as $img_path) {
            if (!file_exists(STATIC_DIR . $img_path)) {
                $issues[] = [
                    'type' => 'image',
                    'severity' => 'error',
                    'message' => 'Referenced image not found in data file',
                    'path' => $relative,
                    'detail' => $img_path,
                    'fix' => 'Upload image or fix path'
                ];
            }
        }
    }
}

/**
 * Check section parity across languages
 */
function scan_section_parity() {
    global $warnings, $info;
    
    $parity_issues = get_section_language_parity();
    
    foreach ($parity_issues as $issue) {
        if ($issue['type'] === 'warning') {
            $warnings[] = [
                'type' => 'translation',
                'message' => $issue['message'],
                'path' => "content.{$issue['language']}/{$issue['section']}/",
                'fix' => "Create the section folder and _index.md for {$issue['language']}"
            ];
        } else {
            $info[] = [
                'type' => 'translation',
                'message' => $issue['message'],
                'path' => "content.{$issue['language']}/{$issue['section']}/",
                'fix' => "This section only exists in {$issue['language']}"
            ];
        }
    }
}

// Run all scans
scan_content();
scan_structure();
scan_images();
scan_image_structure();
scan_orphaned_images();
scan_translations();
scan_section_parity();
scan_data_files();

// Sort issues by severity
usort($issues, function($a, $b) {
    $severity_order = ['error' => 0, 'warning' => 1];
    return ($severity_order[$a['severity'] ?? 'warning'] ?? 1) - ($severity_order[$b['severity'] ?? 'warning'] ?? 1);
});

$total_issues = count($issues);
$total_warnings = count($warnings);
$total_info = count($info);

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Project Scanner</h1>
        <p class="page-subtitle">
            Scanned <?= $stats['articles_scanned'] ?> articles, <?= $stats['images_scanned'] ?> images, <?= $stats['data_files_scanned'] ?> data files
        </p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="help.php" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            View Guidelines
        </a>
        <a href="scanner.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M23 4v6h-6"/>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
            Re-scan
        </a>
    </div>
</div>

<!-- Rules Reference -->
<div class="card" style="margin-bottom: 24px; background: var(--bg-tertiary);">
    <div style="display: flex; gap: 24px; flex-wrap: wrap; font-size: 12px;">
        <div>
            <span style="color: var(--text-muted);">Valid Sections:</span>
            <span style="color: var(--text-primary);"><?= implode(', ', $RULES['valid_sections']) ?></span>
        </div>
        <div>
            <span style="color: var(--text-muted);">Max Image Size:</span>
            <span style="color: var(--text-primary);"><?= format_size($RULES['max_image_size']) ?></span>
        </div>
        <div>
            <span style="color: var(--text-muted);">Max Description:</span>
            <span style="color: var(--text-primary);"><?= $RULES['max_description_length'] ?> chars</span>
        </div>
        <div>
            <span style="color: var(--text-muted);">Image Formats:</span>
            <span style="color: var(--text-primary);"><?= implode(', ', $RULES['valid_image_extensions']) ?></span>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-3" style="gap: 20px; margin-bottom: 32px;">
    <div class="stat-card" style="border-left: 4px solid <?= $total_issues > 0 ? '#e11d48' : '#10b981' ?>;">
        <div class="stat-icon" style="background: <?= $total_issues > 0 ? 'linear-gradient(135deg, #e11d48, #be123c)' : 'linear-gradient(135deg, #10b981, #059669)' ?>;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <?php if ($total_issues > 0): ?>
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
                <?php else: ?>
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
                <?php endif; ?>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= $total_issues ?></div>
            <div class="stat-label">Errors</div>
        </div>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid <?= $total_warnings > 0 ? '#f59e0b' : '#10b981' ?>;">
        <div class="stat-icon" style="background: <?= $total_warnings > 0 ? 'linear-gradient(135deg, #f59e0b, #d97706)' : 'linear-gradient(135deg, #10b981, #059669)' ?>;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= $total_warnings ?></div>
            <div class="stat-label">Warnings</div>
        </div>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid #3b82f6;">
        <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
        </div>
        <div>
            <div class="stat-value"><?= $total_info ?></div>
            <div class="stat-label">Info</div>
        </div>
    </div>
</div>

<?php if ($total_issues === 0 && $total_warnings === 0): ?>
<!-- All Good -->
<div class="card" style="text-align: center; padding: 60px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="width: 64px; height: 64px; margin: 0 auto 20px;">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
    </svg>
    <h2 style="color: #10b981; margin-bottom: 8px;">All Clear!</h2>
    <p style="color: var(--text-secondary);">No errors or warnings. Your project structure is looking great!</p>
</div>
<?php else: ?>

<!-- Errors -->
<?php if ($total_issues > 0): ?>
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px; color: #e11d48;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline; vertical-align: middle; margin-right: 8px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        Errors (<?= $total_issues ?>) — Must Fix
    </h2>
    
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($issues as $issue): ?>
        <div style="background: rgba(225, 29, 72, 0.05); border: 1px solid rgba(225, 29, 72, 0.2); border-radius: 8px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($issue['message']) ?></div>
                    <div style="font-size: 12px; font-family: monospace; color: var(--text-secondary);">
                        <?= htmlspecialchars($issue['path']) ?>
                        <?php if (!empty($issue['detail'])): ?>
                        <br><span style="color: #e11d48;"><?= htmlspecialchars($issue['detail']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">
                    <?= htmlspecialchars($issue['type']) ?>
                </div>
            </div>
            <?php if (!empty($issue['fix'])): ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(225, 29, 72, 0.1); font-size: 12px;">
                <strong style="color: #10b981;">✓ Fix:</strong> <?= htmlspecialchars($issue['fix']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Warnings -->
<?php if ($total_warnings > 0): ?>
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="margin-bottom: 20px; color: #f59e0b;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline; vertical-align: middle; margin-right: 8px;">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Warnings (<?= $total_warnings ?>) — Should Fix
    </h2>
    
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($warnings as $warning): ?>
        <div style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 8px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($warning['message']) ?></div>
                    <div style="font-size: 12px; font-family: monospace; color: var(--text-secondary);">
                        <?= htmlspecialchars($warning['path']) ?>
                        <?php if (!empty($warning['detail'])): ?>
                        <br><span style="color: #f59e0b;"><?= htmlspecialchars($warning['detail']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">
                    <?= htmlspecialchars($warning['type']) ?>
                </div>
            </div>
            <?php if (!empty($warning['fix'])): ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(245, 158, 11, 0.1); font-size: 12px;">
                <strong style="color: #10b981;">✓ Fix:</strong> <?= htmlspecialchars($warning['fix']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Info -->
<?php if ($total_info > 0): ?>
<div class="card">
    <h2 class="card-title" style="margin-bottom: 20px; color: #3b82f6;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline; vertical-align: middle; margin-right: 8px;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        Info (<?= $total_info ?>)
    </h2>
    
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($info as $item): ?>
        <div style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars($item['message']) ?></div>
                    <div style="font-size: 12px; font-family: monospace; color: var(--text-secondary);">
                        <?= htmlspecialchars($item['path']) ?>
                    </div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">
                    <?= htmlspecialchars($item['type']) ?>
                </div>
            </div>
            <?php if (!empty($item['fix'])): ?>
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(59, 130, 246, 0.1); font-size: 12px;">
                <strong style="color: #3b82f6;">ℹ</strong> <?= htmlspecialchars($item['fix']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
