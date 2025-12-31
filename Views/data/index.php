<?php
/**
 * Data Editor View
 */
?>
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
                $is_current = $lang === $currentLang;
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
                <strong>Creating <?= $config['languages'][$currentLang]['name'] ?? $currentLang ?> Version:</strong>
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
                    <?= $config['languages'][$currentLang]['flag'] ?? '🌐' ?>
                    <?= $config['languages'][$currentLang]['name'] ?? $currentLang ?>
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

