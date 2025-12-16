<?php
/**
 * Hugo Admin - Settings
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = 'Settings';

// Handle Hugo build
$build_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['build'])) {
    $build_result = build_hugo();
}

// Generate password hash helper
$new_hash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_hash'])) {
    $password = $_POST['password'] ?? '';
    if ($password) {
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
    }
}

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">
            Configure your Hugo Admin panel
        </p>
    </div>
</div>

<div class="grid grid-2" style="gap: 24px;">
    <!-- Hugo Build -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 16px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
            Hugo Site Build
        </h3>
        
        <p style="color: var(--text-secondary); margin-bottom: 16px;">
            Rebuild your Hugo site to apply all content changes.
        </p>
        
        <?php if ($build_result): ?>
        <div style="background: <?= $build_result['success'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(225, 29, 72, 0.1)' ?>; 
                    border: 1px solid <?= $build_result['success'] ? '#10b981' : '#e11d48' ?>; 
                    border-radius: 8px; padding: 12px; margin-bottom: 16px;">
            <div style="font-weight: 600; margin-bottom: 8px; color: <?= $build_result['success'] ? '#10b981' : '#e11d48' ?>;">
                <?= $build_result['success'] ? '✓ Build Successful' : '✗ Build Failed' ?>
            </div>
            <pre style="font-size: 11px; color: var(--text-secondary); white-space: pre-wrap; margin: 0; font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($build_result['output']) ?></pre>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <button type="submit" name="build" value="1" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                    <path d="M23 4v6h-6"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Rebuild Site
            </button>
        </form>
    </div>
    
    <!-- Password Generator -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 16px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Password Hash Generator
        </h3>
        
        <p style="color: var(--text-secondary); margin-bottom: 16px;">
            Generate a secure password hash to use in <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px;">config.php</code>
        </p>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-input" placeholder="Enter new password" required>
            </div>
            
            <button type="submit" name="generate_hash" value="1" class="btn btn-secondary">
                Generate Hash
            </button>
        </form>
        
        <?php if ($new_hash): ?>
        <div style="margin-top: 16px; padding: 12px; background: var(--bg-tertiary); border-radius: 8px;">
            <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 4px;">Copy this hash to config.php:</div>
            <code style="font-size: 11px; word-break: break-all; font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($new_hash) ?></code>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Site Info -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 16px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            Site Information
        </h3>
        
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <div>
                <div style="font-size: 11px; color: var(--text-muted);">Site Name</div>
                <div><?= htmlspecialchars($config['site_name']) ?></div>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted);">Site URL</div>
                <div><a href="<?= htmlspecialchars($config['site_url']) ?>" target="_blank" style="color: var(--accent-primary);"><?= htmlspecialchars($config['site_url']) ?></a></div>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted);">Hugo Root</div>
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 13px;"><?= HUGO_ROOT ?></div>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--text-muted);">Content Directory</div>
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 13px;"><?= CONTENT_DIR ?></div>
            </div>
        </div>
    </div>
    
    <!-- Languages -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 16px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 8px;">
                <circle cx="12" cy="12" r="10"/>
                <line x1="2" y1="12" x2="22" y2="12"/>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
            </svg>
            Languages
        </h3>
        
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($config['languages'] as $lang => $lang_config): ?>
            <div style="background: var(--bg-tertiary); padding: 8px 16px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;"><?= $lang_config['flag'] ?></span>
                <span><?= $lang_config['name'] ?></span>
                <span style="font-size: 11px; color: var(--text-muted);">(<?= $lang ?>)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick Reference -->
<div class="card" style="margin-top: 24px;">
    <h3 class="card-title" style="margin-bottom: 16px;">Quick Reference</h3>
    
    <div class="grid grid-3" style="gap: 24px;">
        <div>
            <h4 style="font-size: 14px; margin-bottom: 8px;">Keyboard Shortcuts</h4>
            <div style="font-size: 13px; color: var(--text-secondary);">
                <div style="margin-bottom: 4px;"><kbd style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px; font-family: inherit;">Ctrl + S</kbd> Save article</div>
            </div>
        </div>
        
        <div>
            <h4 style="font-size: 14px; margin-bottom: 8px;">File Locations</h4>
            <div style="font-size: 13px; color: var(--text-secondary);">
                <div style="margin-bottom: 4px;">Content: <code>/content/</code></div>
                <div style="margin-bottom: 4px;">Images: <code>/static/images/</code></div>
                <div style="margin-bottom: 4px;">Data: <code>/data/</code></div>
            </div>
        </div>
        
        <div>
            <h4 style="font-size: 14px; margin-bottom: 8px;">Support</h4>
            <div style="font-size: 13px; color: var(--text-secondary);">
                <div style="margin-bottom: 4px;"><a href="https://gohugo.io/documentation/" target="_blank" style="color: var(--accent-primary);">Hugo Documentation</a></div>
                <div><a href="https://www.markdownguide.org/" target="_blank" style="color: var(--accent-primary);">Markdown Guide</a></div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

