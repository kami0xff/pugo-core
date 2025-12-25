<?php
/**
 * Hugo Admin - Data Files Editor
 * 
 * Supports multilingual data files with language variants.
 * Files can be named: menu.yaml (default), menu.de.yaml, menu.es.yaml
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Data Files';
$current_lang = $_GET['lang'] ?? $config['default_language'] ?? 'en';

if (!isset($config['languages'][$current_lang])) {
    $current_lang = $config['default_language'] ?? 'en';
}

/**
 * Get language suffix for data files
 */
function get_lang_suffix($lang, $config) {
    $default_lang = $config['default_language'] ?? 'en';
    if ($lang === $default_lang) {
        return ''; // No suffix for default language
    }
    return $config['languages'][$lang]['data_suffix'] ?? ('.' . $lang);
}

/**
 * Parse data filename to get base name and language
 */
function parse_data_filename($filename, $config) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    
    // Check for language suffix in filename
    foreach ($config['languages'] as $lang => $lang_config) {
        $suffix = $lang_config['data_suffix'] ?? ('.' . $lang);
        if ($suffix && str_ends_with($name, $suffix)) {
            return [
                'base' => substr($name, 0, -strlen($suffix)),
                'lang' => $lang,
                'ext' => $ext
            ];
        }
    }
    
    // No suffix = default language
    return [
        'base' => $name,
        'lang' => $config['default_language'] ?? 'en',
        'ext' => $ext
    ];
}

/**
 * Get all data files grouped by base name
 */
function get_data_files_grouped($config) {
    $files = [];
    $grouped = [];
    
    if (!is_dir(DATA_DIR)) {
        return ['files' => [], 'grouped' => []];
    }
    
    foreach (scandir(DATA_DIR) as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (!in_array($ext, ['yaml', 'yml', 'json'])) continue;
        
        $parsed = parse_data_filename($file, $config);
        $path = DATA_DIR . '/' . $file;
        
        $files[$file] = [
            'name' => $file,
            'path' => $path,
            'size' => filesize($path),
            'modified' => filemtime($path),
            'base' => $parsed['base'],
            'lang' => $parsed['lang'],
            'ext' => $parsed['ext']
        ];
        
        // Group by base name
        $key = $parsed['base'] . '.' . $parsed['ext'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'base' => $parsed['base'],
                'ext' => $parsed['ext'],
                'languages' => []
            ];
        }
        $grouped[$key]['languages'][$parsed['lang']] = $file;
    }
    
    ksort($grouped);
    return ['files' => $files, 'grouped' => $grouped];
}

$data = get_data_files_grouped($config);
$data_files = $data['files'];
$grouped_files = $data['grouped'];

// Handle file edit
$editing_file = null;
$editing_base = null;
$file_content = '';

if (isset($_GET['edit'])) {
    $edit_file = basename($_GET['edit']);
    $edit_path = DATA_DIR . '/' . $edit_file;
    
    if (file_exists($edit_path) && isset($data_files[$edit_file])) {
        $editing_file = $edit_file;
        $editing_base = $data_files[$edit_file]['base'] . '.' . $data_files[$edit_file]['ext'];
        $file_content = file_get_contents($edit_path);
        $current_lang = $data_files[$edit_file]['lang'];
    }
}

// Handle creating new language variant
if (isset($_GET['create_variant'])) {
    $base_file = basename($_GET['create_variant']);
    $target_lang = $_GET['target_lang'] ?? '';
    
    if (isset($data_files[$base_file]) && isset($config['languages'][$target_lang])) {
        $base_info = $data_files[$base_file];
        $suffix = get_lang_suffix($target_lang, $config);
        $new_filename = $base_info['base'] . $suffix . '.' . $base_info['ext'];
        
        // Load source content
        $file_content = file_get_contents($base_info['path']);
        $editing_file = $new_filename;
        $editing_base = $base_info['base'] . '.' . $base_info['ext'];
        $current_lang = $target_lang;
        
        // Mark as new file creation
        $is_creating = true;
    }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_file'])) {
    csrf_check();
    
    $save_file = basename($_POST['save_file']);
    $save_path = DATA_DIR . '/' . $save_file;
    $content = $_POST['content'] ?? '';
    
    // Validate YAML/JSON
    $ext = pathinfo($save_file, PATHINFO_EXTENSION);
    $valid = true;
    $error = null;
    
    if ($ext === 'json') {
        json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Invalid JSON: ' . json_last_error_msg();
            $valid = false;
        }
    }
    
    if ($valid && file_put_contents($save_path, $content) !== false) {
        $_SESSION['success'] = 'File saved successfully!';
        
        // Rebuild Hugo
        build_hugo();
        
        header('Location: data.php?edit=' . urlencode($save_file));
        exit;
    } elseif ($valid) {
        $error = 'Failed to save file. Check permissions.';
    }
    
    $editing_file = $save_file;
    $file_content = $content;
}

// Handle new file creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_file'])) {
    csrf_check();
    
    $new_name = trim($_POST['filename'] ?? '');
    $new_ext = $_POST['filetype'] ?? 'yaml';
    $new_lang = $_POST['filelang'] ?? $config['default_language'] ?? 'en';
    
    if ($new_name && preg_match('/^[a-z0-9_-]+$/i', $new_name)) {
        $suffix = get_lang_suffix($new_lang, $config);
        $new_filename = $new_name . $suffix . '.' . $new_ext;
        $new_path = DATA_DIR . '/' . $new_filename;
        
        if (file_exists($new_path)) {
            $error = "File '{$new_filename}' already exists";
        } else {
            // Create with sample content
            $sample = $new_ext === 'json' 
                ? "{\n  \"example\": \"value\"\n}" 
                : "# " . ucfirst($new_name) . " Data File\n\nexample: value\n";
            
            if (file_put_contents($new_path, $sample) !== false) {
                $_SESSION['success'] = "File '{$new_filename}' created!";
                header('Location: data.php?edit=' . urlencode($new_filename));
                exit;
            } else {
                $error = 'Failed to create file. Check permissions.';
            }
        }
    } else {
        $error = 'Invalid filename. Use only letters, numbers, hyphens, and underscores.';
    }
}

require __DIR__ . '/../includes/header.php';
?>

<style>
/* Data file grouping */
.data-group {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    margin-bottom: 8px;
    overflow: hidden;
}

.data-group-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
}

.data-group-header:hover {
    background: var(--bg-hover);
}

.data-group-icon {
    width: 20px;
    height: 20px;
    opacity: 0.5;
}

.data-group-name {
    font-weight: 600;
    font-size: 14px;
    flex: 1;
}

.data-group-langs {
    display: flex;
    gap: 4px;
}

.data-lang-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    background: rgba(99, 102, 241, 0.1);
    color: var(--accent-primary);
}

.data-lang-badge.active {
    background: var(--accent-primary);
    color: white;
}

.data-lang-badge.missing {
    background: rgba(107, 114, 128, 0.1);
    color: var(--text-muted);
    opacity: 0.6;
}

/* Language tabs for editing */
.data-lang-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.data-lang-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.15s ease;
}

.data-lang-tab:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.data-lang-tab.active {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

.data-lang-tab .flag {
    font-size: 16px;
}

.data-lang-tab.creating {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(168, 85, 247, 0.2) 100%);
    border-color: var(--accent-primary);
}

/* Create notice */
.create-notice {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: var(--radius-sm);
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.create-notice svg {
    width: 24px;
    height: 24px;
    color: var(--accent-primary);
    flex-shrink: 0;
}

.create-notice-text {
    font-size: 14px;
    color: var(--text-secondary);
}

.create-notice-text strong {
    color: var(--text-primary);
}

/* New file modal */
.new-file-form {
    display: grid;
    gap: 16px;
}

.new-file-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 12px;
}

@media (max-width: 600px) {
    .new-file-row {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Data Files</h1>
        <p class="page-subtitle">
            Edit YAML and JSON data files with multilingual support
        </p>
    </div>
    <button onclick="document.getElementById('newFileModal').classList.add('active')" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
            <path d="M12 5v14M5 12h14"/>
        </svg>
        New Data File
    </button>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div style="background: rgba(225, 29, 72, 0.1); border: 1px solid #e11d48; color: #e11d48; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="grid grid-3" style="gap: 24px;">
    <!-- File List -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 16px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px; opacity: 0.5; vertical-align: middle;">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
            </svg>
            Data Files
        </h3>
        
        <?php if (empty($grouped_files)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 20px;">
            No data files found. Create one to get started.
        </p>
        <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <?php foreach ($grouped_files as $key => $group): ?>
            <div class="data-group <?= $editing_base === $key ? 'active' : '' ?>">
                <a href="data.php?edit=<?= urlencode($group['languages'][$config['default_language'] ?? 'en'] ?? reset($group['languages'])) ?>" 
                   class="data-group-header" style="text-decoration: none; color: inherit;">
                    <svg class="data-group-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?php if ($group['ext'] === 'json'): ?>
                        <path d="M8 3H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-2"/>
                        <path d="M8 3v4h8V3"/>
                        <?php else: ?>
                        <ellipse cx="12" cy="5" rx="9" ry="3"/>
                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                        <?php endif; ?>
                    </svg>
                    <span class="data-group-name"><?= htmlspecialchars($group['base']) ?>.<?= $group['ext'] ?></span>
                    <div class="data-group-langs">
                        <?php foreach ($config['languages'] as $lang => $lang_config): ?>
                            <?php if (isset($group['languages'][$lang])): ?>
                            <span class="data-lang-badge <?= (isset($data_files[$editing_file]) && $data_files[$editing_file]['lang'] === $lang && $editing_base === $key) ? 'active' : '' ?>">
                                <?= $lang_config['flag'] ?>
                            </span>
                            <?php else: ?>
                            <span class="data-lang-badge missing" title="No <?= $lang_config['name'] ?> version">
                                <?= $lang_config['flag'] ?>
                            </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Editor -->
    <div class="card" style="grid-column: span 2;">
        <?php if ($editing_file): ?>
        
        <?php 
        // Find all language variants for this file
        $file_info = $data_files[$editing_file] ?? parse_data_filename($editing_file, $config);
        $base_key = $file_info['base'] . '.' . $file_info['ext'];
        $variants = $grouped_files[$base_key]['languages'] ?? [];
        ?>
        
        <!-- Language Tabs -->
        <?php if (count($config['languages']) > 1): ?>
        <div class="data-lang-tabs">
            <?php foreach ($config['languages'] as $lang => $lang_config): 
                $has_variant = isset($variants[$lang]);
                $variant_file = $variants[$lang] ?? null;
                $is_current = $lang === $current_lang;
            ?>
                <?php if ($is_current): ?>
                <span class="data-lang-tab active <?= isset($is_creating) && $is_creating ? 'creating' : '' ?>">
                    <span class="flag"><?= $lang_config['flag'] ?></span>
                    <?= $lang_config['name'] ?>
                    <?php if (isset($is_creating) && $is_creating): ?>
                    <span style="font-size: 11px; opacity: 0.8;">(Creating)</span>
                    <?php endif; ?>
                </span>
                <?php elseif ($has_variant): ?>
                <a href="data.php?edit=<?= urlencode($variant_file) ?>" class="data-lang-tab">
                    <span class="flag"><?= $lang_config['flag'] ?></span>
                    <?= $lang_config['name'] ?>
                </a>
                <?php else: ?>
                <?php 
                // Find source file for creating variant
                $source_file = $variants[$config['default_language'] ?? 'en'] ?? reset($variants);
                ?>
                <a href="data.php?create_variant=<?= urlencode($source_file) ?>&target_lang=<?= $lang ?>" 
                   class="data-lang-tab" style="opacity: 0.5;">
                    <span class="flag"><?= $lang_config['flag'] ?></span>
                    <?= $lang_config['name'] ?>
                    <span style="font-size: 10px;">+ Create</span>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($is_creating) && $is_creating): ?>
        <!-- Creating new variant notice -->
        <div class="create-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                <path d="M2 12h20"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
            <div class="create-notice-text">
                <strong>Creating <?= $config['languages'][$current_lang]['name'] ?? $current_lang ?> Version:</strong>
                You are creating <strong><?= htmlspecialchars($editing_file) ?></strong>. 
                The content has been pre-filled from the source file. Translate the values as needed.
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card-header" style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <span style="opacity: 0.5;">Editing:</span> 
                <?= htmlspecialchars($editing_file) ?>
                <span class="data-lang-badge active" style="margin-left: 8px;">
                    <?= $config['languages'][$current_lang]['flag'] ?? '🌐' ?>
                    <?= $config['languages'][$current_lang]['name'] ?? $current_lang ?>
                </span>
            </h3>
            <?php if (!isset($is_creating) && isset($data_files[$editing_file])): ?>
            <span style="font-size: 12px; color: var(--text-muted);">
                <?= format_size($data_files[$editing_file]['size']) ?> · 
                Modified <?= time_ago($data_files[$editing_file]['modified']) ?>
            </span>
            <?php endif; ?>
        </div>
        
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="save_file" value="<?= htmlspecialchars($editing_file) ?>">
            
            <div class="form-group">
                <textarea name="content" id="dataContent" class="form-input" 
                          style="font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 13px; min-height: 500px; line-height: 1.6; tab-size: 2;"
                          spellcheck="false"
                ><?= htmlspecialchars($file_content) ?></textarea>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: space-between; align-items: center;">
                <div style="font-size: 12px; color: var(--text-muted);">
                    <?php 
                    $ext = pathinfo($editing_file, PATHINFO_EXTENSION);
                    echo strtoupper($ext) . ' format';
                    if ($ext === 'yaml' || $ext === 'yml') {
                        echo ' · Use spaces for indentation';
                    }
                    ?>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="data.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
        <?php else: ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
            </svg>
            <h3>Select a file to edit</h3>
            <p>Choose a data file from the list to view and edit its contents.</p>
            <p style="margin-top: 12px; font-size: 13px; color: var(--text-muted);">
                Data files provide content for your Hugo templates, like menus, team members, FAQs, etc.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- New File Modal -->
<div class="modal-overlay" id="newFileModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Create New Data File</h3>
            <button onclick="document.getElementById('newFileModal').classList.remove('active')" class="modal-close">&times;</button>
        </div>
        <form method="POST" class="new-file-form">
            <?= csrf_field() ?>
            <input type="hidden" name="create_file" value="1">
            
            <div class="form-group">
                <label class="form-label">File Name</label>
                <div class="new-file-row">
                    <input type="text" name="filename" class="form-input" placeholder="e.g., menu, team, faqs" required
                           pattern="[a-zA-Z0-9_-]+" title="Use only letters, numbers, hyphens, and underscores">
                    
                    <select name="filetype" class="form-input">
                        <option value="yaml">.yaml</option>
                        <option value="json">.json</option>
                    </select>
                    
                    <select name="filelang" class="form-input">
                        <?php foreach ($config['languages'] as $lang => $lang_config): ?>
                        <option value="<?= $lang ?>" <?= $lang === ($config['default_language'] ?? 'en') ? 'selected' : '' ?>>
                            <?= $lang_config['flag'] ?> <?= $lang_config['name'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <small style="color: var(--text-muted); margin-top: 8px; display: block;">
                    For multilingual data, create the default language version first, then add translations.
                </small>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('newFileModal').classList.remove('active')" class="btn btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    Create File
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab key support for textarea
document.getElementById('dataContent')?.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + '  ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 2;
    }
});

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('newFileModal')?.classList.remove('active');
    }
});

// Close modal on overlay click
document.getElementById('newFileModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.remove('active');
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
