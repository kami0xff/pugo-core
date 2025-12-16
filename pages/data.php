<?php
/**
 * Hugo Admin - Data Files Editor
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Data Files';

// Get data files
$data_files = [];
if (is_dir(DATA_DIR)) {
    foreach (scandir(DATA_DIR) as $file) {
        if (in_array(pathinfo($file, PATHINFO_EXTENSION), ['yaml', 'yml', 'json'])) {
            $data_files[] = [
                'name' => $file,
                'path' => DATA_DIR . '/' . $file,
                'size' => filesize(DATA_DIR . '/' . $file),
                'modified' => filemtime(DATA_DIR . '/' . $file)
            ];
        }
    }
}

// Sort by name
usort($data_files, fn($a, $b) => strcmp($a['name'], $b['name']));

// Handle file edit
$editing_file = null;
$file_content = '';
if (isset($_GET['edit'])) {
    $edit_file = basename($_GET['edit']);
    $edit_path = DATA_DIR . '/' . $edit_file;
    
    if (file_exists($edit_path)) {
        $editing_file = $edit_file;
        $file_content = file_get_contents($edit_path);
    }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_file'])) {
    $save_file = basename($_POST['save_file']);
    $save_path = DATA_DIR . '/' . $save_file;
    $content = $_POST['content'] ?? '';
    
    // Validate YAML/JSON
    $ext = pathinfo($save_file, PATHINFO_EXTENSION);
    $valid = true;
    
    if ($ext === 'json') {
        json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Invalid JSON: ' . json_last_error_msg();
            $valid = false;
        }
    }
    
    if ($valid && file_put_contents($save_path, $content) !== false) {
        $_SESSION['success'] = 'File saved successfully!';
        header('Location: data.php');
        exit;
    } elseif ($valid) {
        $error = 'Failed to save file';
    }
    
    $editing_file = $save_file;
    $file_content = $content;
}

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Data Files</h1>
        <p class="page-subtitle">
            Edit YAML and JSON data files used by your Hugo site
        </p>
    </div>
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
        <h3 class="card-title" style="margin-bottom: 16px;">Files</h3>
        
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <?php foreach ($data_files as $file): ?>
            <a href="data.php?edit=<?= urlencode($file['name']) ?>" 
               class="article-item" style="padding: 12px; <?= $editing_file === $file['name'] ? 'border-color: var(--accent-primary); background: var(--bg-tertiary);' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px; opacity: 0.5;">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/>
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                </svg>
                <div style="flex: 1;">
                    <div style="font-weight: 500;"><?= htmlspecialchars($file['name']) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted);">
                        <?= format_size($file['size']) ?> · <?= time_ago($file['modified']) ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($data_files)): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 20px;">
            No data files found
        </p>
        <?php endif; ?>
    </div>
    
    <!-- Editor -->
    <div class="card" style="grid-column: span 2;">
        <?php if ($editing_file): ?>
        <div class="card-header" style="margin-bottom: 16px;">
            <h3 class="card-title">
                <span style="opacity: 0.5;">Editing:</span> <?= htmlspecialchars($editing_file) ?>
            </h3>
        </div>
        
        <form method="POST">
            <input type="hidden" name="save_file" value="<?= htmlspecialchars($editing_file) ?>">
            
            <div class="form-group">
                <textarea name="content" class="form-input" 
                          style="font-family: 'JetBrains Mono', monospace; font-size: 13px; min-height: 500px; line-height: 1.6;"
                ><?= htmlspecialchars($file_content) ?></textarea>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
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
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

