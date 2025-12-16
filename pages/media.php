<?php
/**
 * Hugo Admin - Media Browser
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Media Library';
$current_path = $_GET['path'] ?? '';

// Handle file upload
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $target_dir = $_POST['directory'] ?? 'articles';
    $target_path = IMAGES_DIR . '/' . $target_dir;
    
    // Create directory if needed
    if (!is_dir($target_path)) {
        mkdir($target_path, 0755, true);
    }
    
    // Validate file type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array_merge($config['allowed_images'], $config['allowed_videos']);
    
    if (!in_array($ext, $allowed)) {
        $upload_message = ['type' => 'error', 'text' => 'File type not allowed'];
    } elseif ($file['size'] > $config['max_upload_size']) {
        $upload_message = ['type' => 'error', 'text' => 'File too large (max ' . format_size($config['max_upload_size']) . ')'];
    } else {
        // Generate unique filename
        $filename = generate_slug(pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = $filename . '-' . time() . '.' . $ext;
        $destination = $target_path . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $upload_message = ['type' => 'success', 'text' => 'File uploaded successfully'];
            $current_path = $target_dir;
        } else {
            $upload_message = ['type' => 'error', 'text' => 'Failed to upload file'];
        }
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $delete_path = $_POST['delete'];
    $full_path = STATIC_DIR . $delete_path;
    
    // Security check
    $real_path = realpath($full_path);
    if ($real_path && strpos($real_path, realpath(IMAGES_DIR)) === 0 && file_exists($real_path)) {
        if (unlink($real_path)) {
            $upload_message = ['type' => 'success', 'text' => 'File deleted'];
        } else {
            $upload_message = ['type' => 'error', 'text' => 'Failed to delete file'];
        }
    }
}

// Handle folder creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_folder'])) {
    $folder_name = generate_slug($_POST['new_folder']);
    $parent = $_POST['parent'] ?? '';
    $new_folder_path = IMAGES_DIR . ($parent ? '/' . $parent : '') . '/' . $folder_name;
    
    if (!is_dir($new_folder_path)) {
        if (mkdir($new_folder_path, 0755, true)) {
            $upload_message = ['type' => 'success', 'text' => 'Folder created'];
            $current_path = ($parent ? $parent . '/' : '') . $folder_name;
        } else {
            $upload_message = ['type' => 'error', 'text' => 'Failed to create folder'];
        }
    } else {
        $upload_message = ['type' => 'error', 'text' => 'Folder already exists'];
    }
}

$media = get_media_files($current_path);

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Media Library</h1>
        <p class="page-subtitle">
            Manage images and videos for your articles
        </p>
    </div>
    <div style="display: flex; gap: 12px;">
        <button type="button" class="btn btn-secondary" onclick="openModal('newFolderModal')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                <line x1="12" y1="11" x2="12" y2="17"/>
                <line x1="9" y1="14" x2="15" y2="14"/>
            </svg>
            New Folder
        </button>
        <button type="button" class="btn btn-primary" onclick="openModal('uploadModal')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Upload
        </button>
    </div>
</div>

<?php if ($upload_message): ?>
<div style="background: <?= $upload_message['type'] === 'success' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(225, 29, 72, 0.1)' ?>; 
            border: 1px solid <?= $upload_message['type'] === 'success' ? '#10b981' : '#e11d48' ?>; 
            color: <?= $upload_message['type'] === 'success' ? '#10b981' : '#e11d48' ?>; 
            padding: 12px 16px; border-radius: 8px; margin-bottom: 24px;">
    <?= htmlspecialchars($upload_message['text']) ?>
</div>
<?php endif; ?>

<!-- Breadcrumb Navigation -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <div class="breadcrumb" style="margin: 0;">
        <a href="media.php" style="display: flex; align-items: center; gap: 4px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
            </svg>
            images
        </a>
        
        <?php if ($current_path): 
            $parts = explode('/', $current_path);
            $cumulative_path = '';
            foreach ($parts as $part): 
                $cumulative_path .= ($cumulative_path ? '/' : '') . $part;
        ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
        <a href="media.php?path=<?= urlencode($cumulative_path) ?>"><?= htmlspecialchars($part) ?></a>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Media Grid -->
<div class="card">
    <?php if (empty($media['files']) && empty($media['directories'])): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21 15 16 10 5 21"/>
        </svg>
        <h3>No media files</h3>
        <p>Upload your first image or create a folder to organize your media.</p>
        <button type="button" class="btn btn-primary" style="margin-top: 16px;" onclick="openModal('uploadModal')">
            Upload Image
        </button>
    </div>
    <?php else: ?>
    <div class="media-grid">
        <!-- Folders -->
        <?php foreach ($media['directories'] as $dir): ?>
        <a href="media.php?path=<?= urlencode($dir['path']) ?>" class="media-item media-folder">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
            <span><?= htmlspecialchars($dir['name']) ?></span>
        </a>
        <?php endforeach; ?>
        
        <!-- Files -->
        <?php foreach ($media['files'] as $file): ?>
        <div class="media-item" onclick="showMediaDetails(<?= htmlspecialchars(json_encode($file)) ?>)">
            <?php if ($file['type'] === 'video'): ?>
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: var(--bg-tertiary);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 32px; height: 32px; opacity: 0.5;">
                    <polygon points="5 3 19 12 5 21 5 3"/>
                </svg>
            </div>
            <?php else: ?>
            <img src="<?= htmlspecialchars($file['path']) ?>" alt="<?= htmlspecialchars($file['name']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="media-item-name"><?= htmlspecialchars($file['name']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Upload Media</h2>
            <button type="button" class="modal-close" onclick="closeModal('uploadModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Upload to folder</label>
                <input type="text" name="directory" class="form-input" 
                       value="<?= htmlspecialchars($current_path ?: 'articles') ?>"
                       placeholder="articles">
            </div>
            
            <div class="form-group">
                <label class="form-label">Select file</label>
                <input type="file" name="file" class="form-input" 
                       accept="image/*,video/mp4,video/webm" required
                       style="padding: 12px;">
            </div>
            
            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">
                Allowed: JPG, PNG, GIF, SVG, WebP, MP4, WebM · Max size: <?= format_size($config['max_upload_size']) ?>
            </p>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- New Folder Modal -->
<div id="newFolderModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Create Folder</h2>
            <button type="button" class="modal-close" onclick="closeModal('newFolderModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="parent" value="<?= htmlspecialchars($current_path) ?>">
            
            <div class="form-group">
                <label class="form-label">Folder name</label>
                <input type="text" name="new_folder" class="form-input" required
                       placeholder="my-folder">
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newFolderModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Media Details Modal -->
<div id="mediaDetailsModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Media Details</h2>
            <button type="button" class="modal-close" onclick="closeModal('mediaDetailsModal')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div id="mediaDetailsContent"></div>
    </div>
</div>

<script>
function showMediaDetails(file) {
    const content = document.getElementById('mediaDetailsContent');
    
    let preview = '';
    if (file.type === 'video') {
        preview = `<video src="${file.path}" controls style="max-width: 100%; border-radius: 8px;"></video>`;
    } else {
        preview = `<img src="${file.path}" style="max-width: 100%; border-radius: 8px;">`;
    }
    
    content.innerHTML = `
        <div style="margin-bottom: 16px;">
            ${preview}
        </div>
        
        <div class="form-group">
            <label class="form-label">File path (click to copy)</label>
            <input type="text" class="form-input" value="${file.path}" readonly 
                   onclick="this.select(); document.execCommand('copy'); showToast('Path copied!');"
                   style="cursor: pointer;">
        </div>
        
        <div style="display: flex; gap: 16px; margin-bottom: 16px;">
            <div>
                <div style="font-size: 11px; color: var(--text-muted);">Size</div>
                <div>${formatSize(file.size)}</div>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted);">Modified</div>
                <div>${new Date(file.modified * 1000).toLocaleDateString()}</div>
            </div>
        </div>
        
        <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this file permanently?')">
                <input type="hidden" name="delete" value="${file.path}">
                <button type="submit" class="btn btn-secondary" style="color: #e11d48;">
                    Delete
                </button>
            </form>
            <button type="button" class="btn btn-secondary" onclick="closeModal('mediaDetailsModal')">Close</button>
        </div>
    `;
    
    openModal('mediaDetailsModal');
}

function formatSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
        bytes /= 1024;
        i++;
    }
    return bytes.toFixed(1) + ' ' + units[i];
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

