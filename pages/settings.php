<?php
/**
 * Hugo Admin - Settings
 * 
 * Site configuration, Hugo builds, and deployment management.
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_auth();

// Load deployment system
require_once __DIR__ . '/../Deployment/DeployResult.php';
require_once __DIR__ . '/../Deployment/DeploymentAdapter.php';
require_once __DIR__ . '/../Deployment/DeploymentManager.php';
require_once __DIR__ . '/../Deployment/Adapters/GitAdapter.php';
require_once __DIR__ . '/../Config/PugoConfig.php';

use Pugo\Deployment\DeploymentManager;
use Pugo\Config\PugoConfig;

$page_title = 'Settings';

// Initialize deployment manager
$pugoConfig = PugoConfig::getInstance(HUGO_ROOT);
$deployManager = new DeploymentManager($pugoConfig, HUGO_ROOT);
$gitAdapter = $deployManager->getAdapter('git');
$gitStatus = $gitAdapter?->getStatus();

// Handle form submissions
$build_result = null;
$deploy_result = null;
$new_hash = null;
$test_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); // Validate CSRF token
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'build':
                $build_result = build_hugo();
                break;
                
            case 'deploy':
                // Validate deployment input
                $validator = validate($_POST, [
                    'commit_message' => 'required|min:3|max:200',
                ]);
                
                if ($validator->validate()) {
                    $message = trim($_POST['commit_message']);
                    
                    // First build, then deploy
                    $build_result = build_hugo();
                    
                    if ($build_result['success']) {
                        $gitAdapter->configure([
                            'branch' => $gitStatus['branch'] ?? 'main',
                            'remote' => 'origin',
                        ]);
                        $deploy_result = $gitAdapter->deploy(HUGO_ROOT . '/public', [
                            'message' => $message,
                        ]);
                        
                        // Refresh git status after deploy
                        $gitStatus = $gitAdapter->getStatus();
                    }
                } else {
                    $deploy_result = (object)[
                        'isSuccess' => fn() => false,
                        'isFailure' => fn() => true,
                        'getMessage' => fn() => $validator->error('commit_message'),
                    ];
                }
                break;
                
            case 'test_connection':
                $gitAdapter->configure([
                    'branch' => $gitStatus['branch'] ?? 'main',
                    'remote' => 'origin',
                ]);
                $test_result = $gitAdapter->testConnection();
                break;
                
            case 'generate_hash':
                $validator = validate($_POST, [
                    'password' => 'required|min:4',
                ]);
                
                if ($validator->validate()) {
                    $new_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                break;
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">
            Configure Hugo builds, deployment, and admin settings
        </p>
    </div>
</div>

<!-- Deployment Section -->
<div class="card" style="margin-bottom: 24px;">
    <h3 class="card-title" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <?= pugo_icon('upload-cloud', 24) ?>
        Deployment
    </h3>
    
    <div class="grid grid-2" style="gap: 24px;">
        <!-- Git Status -->
        <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 20px;">
            <h4 style="font-size: 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <?= pugo_icon('git-branch', 18) ?>
                Git Repository Status
            </h4>
            
            <?php if ($gitStatus && $gitStatus['configured']): ?>
                <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Branch</span>
                        <span style="font-family: 'JetBrains Mono', monospace; color: var(--accent-primary);">
                            <?= htmlspecialchars($gitStatus['branch']) ?>
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Remote</span>
                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($gitStatus['remote_url'] ?? 'Not set') ?>
                        </span>
                    </div>
                    
                    <?php if ($gitStatus['last_commit']): ?>
                    <div style="border-top: 1px solid var(--border-primary); padding-top: 12px; margin-top: 4px;">
                        <div style="color: var(--text-muted); margin-bottom: 8px;">Last Commit</div>
                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 11px;">
                            <div style="color: var(--accent-secondary);">
                                <?= htmlspecialchars(substr($gitStatus['last_commit']['hash'], 0, 8)) ?>
                            </div>
                            <div style="margin-top: 4px; color: var(--text-secondary);">
                                <?= htmlspecialchars($gitStatus['last_commit']['message']) ?>
                            </div>
                            <div style="margin-top: 4px; color: var(--text-muted);">
                                <?= htmlspecialchars($gitStatus['last_commit']['date']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($gitStatus['pending_changes'])): ?>
                    <div style="border-top: 1px solid var(--border-primary); padding-top: 12px; margin-top: 4px;">
                        <div style="color: var(--accent-warning); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                            <?= pugo_icon('alert-circle', 14) ?>
                            <?= count($gitStatus['pending_changes']) ?> pending change<?= count($gitStatus['pending_changes']) > 1 ? 's' : '' ?>
                        </div>
                        <div style="max-height: 100px; overflow-y: auto; font-family: 'JetBrains Mono', monospace; font-size: 11px;">
                            <?php foreach (array_slice($gitStatus['pending_changes'], 0, 5) as $change): ?>
                            <div style="color: var(--text-muted); padding: 2px 0;">
                                <span style="color: <?= $change['status'] === 'M' ? 'var(--accent-warning)' : ($change['status'] === 'A' ? 'var(--accent-success)' : 'var(--accent-error)') ?>;">
                                    <?= htmlspecialchars($change['status']) ?>
                                </span>
                                <?= htmlspecialchars(basename($change['file'])) ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($gitStatus['pending_changes']) > 5): ?>
                            <div style="color: var(--text-muted); font-style: italic;">
                                ...and <?= count($gitStatus['pending_changes']) - 5 ?> more
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="border-top: 1px solid var(--border-primary); padding-top: 12px; margin-top: 4px;">
                        <div style="color: var(--accent-success); display: flex; align-items: center; gap: 6px;">
                            <?= pugo_icon('check-circle', 14) ?>
                            Working tree clean
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" style="margin-top: 16px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="test_connection">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <?= pugo_icon('wifi', 14) ?>
                        Test Connection
                    </button>
                </form>
                
                <?php if ($test_result): ?>
                <div style="margin-top: 12px; padding: 10px; border-radius: 8px; font-size: 12px;
                            background: <?= $test_result->isSuccess() ? 'rgba(16, 185, 129, 0.1)' : 'rgba(225, 29, 72, 0.1)' ?>;
                            color: <?= $test_result->isSuccess() ? '#10b981' : '#e11d48' ?>;">
                    <?= pugo_icon($test_result->isSuccess() ? 'check' : 'x', 14) ?>
                    <?= htmlspecialchars($test_result->getMessage()) ?>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div style="color: var(--text-muted); font-size: 13px;">
                    <div style="display: flex; align-items: center; gap: 8px; color: var(--accent-warning);">
                        <?= pugo_icon('alert-triangle', 16) ?>
                        Git not initialized
                    </div>
                    <p style="margin-top: 8px;">
                        Run <code style="background: var(--bg-secondary); padding: 2px 6px; border-radius: 4px;">git init</code> in your project root to enable Git deployment.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Deploy Form -->
        <div>
            <h4 style="font-size: 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <?= pugo_icon('send', 18) ?>
                Deploy to Production
            </h4>
            
            <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
                Build Hugo site and push to Git remote. Netlify/Vercel/GitLab Pages will automatically deploy.
            </p>
            
            <?php if ($deploy_result): ?>
            <div style="background: <?= $deploy_result->isSuccess() ? 'rgba(16, 185, 129, 0.1)' : 'rgba(225, 29, 72, 0.1)' ?>; 
                        border: 1px solid <?= $deploy_result->isSuccess() ? '#10b981' : '#e11d48' ?>; 
                        border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <div style="font-weight: 600; margin-bottom: 8px; color: <?= $deploy_result->isSuccess() ? '#10b981' : '#e11d48' ?>; display: flex; align-items: center; gap: 8px;">
                    <?= pugo_icon($deploy_result->isSuccess() ? 'check-circle' : 'x-circle', 16) ?>
                    <?= $deploy_result->isSuccess() ? 'Deployment Successful' : 'Deployment Failed' ?>
                </div>
                <pre style="font-size: 11px; color: var(--text-secondary); white-space: pre-wrap; margin: 0; font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($deploy_result->getMessage()) ?></pre>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="deploy">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Commit Message</label>
                    <input type="text" name="commit_message" class="form-input" 
                           placeholder="content: Update articles" 
                           value="content: Update - <?= date('Y-m-d H:i') ?>"
                           required minlength="3" maxlength="200">
                </div>
                
                <button type="submit" class="btn btn-primary" <?= !($gitStatus['configured'] ?? false) ? 'disabled' : '' ?>>
                    <?= pugo_icon('upload-cloud', 16) ?>
                    Build &amp; Deploy
                </button>
            </form>
        </div>
    </div>
</div>

<div class="grid grid-2" style="gap: 24px;">
    <!-- Hugo Build -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <?= pugo_icon('layers', 20) ?>
            Hugo Site Build
        </h3>
        
        <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 13px;">
            Rebuild your Hugo site to apply all content changes without deploying.
        </p>
        
        <?php if ($build_result): ?>
        <div style="background: <?= $build_result['success'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(225, 29, 72, 0.1)' ?>; 
                    border: 1px solid <?= $build_result['success'] ? '#10b981' : '#e11d48' ?>; 
                    border-radius: 8px; padding: 12px; margin-bottom: 16px;">
            <div style="font-weight: 600; margin-bottom: 8px; color: <?= $build_result['success'] ? '#10b981' : '#e11d48' ?>; display: flex; align-items: center; gap: 8px;">
                <?= pugo_icon($build_result['success'] ? 'check-circle' : 'x-circle', 16) ?>
                <?= $build_result['success'] ? 'Build Successful' : 'Build Failed' ?>
            </div>
            <pre style="font-size: 11px; color: var(--text-secondary); white-space: pre-wrap; margin: 0; font-family: 'JetBrains Mono', monospace; max-height: 150px; overflow-y: auto;"><?= htmlspecialchars($build_result['output']) ?></pre>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="build">
            <button type="submit" class="btn btn-secondary">
                <?= pugo_icon('refresh-cw', 16) ?>
                Rebuild Site
            </button>
        </form>
    </div>
    
    <!-- Password Generator -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <?= pugo_icon('lock', 20) ?>
            Password Hash Generator
        </h3>
        
        <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 13px;">
            Generate a secure password hash to use in <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px;">config.php</code>
        </p>
        
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="generate_hash">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-input" placeholder="Enter new password" required minlength="4">
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <?= pugo_icon('key', 16) ?>
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
        <h3 class="card-title" style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <?= pugo_icon('info', 20) ?>
            Site Information
        </h3>
        
        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Site Name</span>
                <span><?= htmlspecialchars($config['site_name']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Site URL</span>
                <a href="<?= htmlspecialchars($config['site_url']) ?>" target="_blank" style="color: var(--accent-primary);">
                    <?= htmlspecialchars($config['site_url']) ?>
                </a>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Hugo Root</span>
                <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px;"><?= HUGO_ROOT ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Content Directory</span>
                <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px;"><?= CONTENT_DIR ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Pugo Version</span>
                <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px;">3.0.0</span>
            </div>
        </div>
    </div>
    
    <!-- Languages -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <?= pugo_icon('globe', 20) ?>
            Languages
        </h3>
        
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach ($config['languages'] as $lang => $lang_config): ?>
            <div style="background: var(--bg-tertiary); padding: 8px 16px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;"><?= $lang_config['flag'] ?></span>
                <span style="font-size: 13px;"><?= $lang_config['name'] ?></span>
                <span style="font-size: 11px; color: var(--text-muted);">(<?= $lang ?>)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick Reference -->
<div class="card" style="margin-top: 24px;">
    <h3 class="card-title" style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <?= pugo_icon('book-open', 20) ?>
        Quick Reference
    </h3>
    
    <div class="grid grid-3" style="gap: 24px;">
        <div>
            <h4 style="font-size: 14px; margin-bottom: 8px;">Keyboard Shortcuts</h4>
            <div style="font-size: 13px; color: var(--text-secondary);">
                <div style="margin-bottom: 4px;">
                    <kbd style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px; font-family: inherit;">Ctrl + S</kbd> 
                    Save article
                </div>
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
            <h4 style="font-size: 14px; margin-bottom: 8px;">Documentation</h4>
            <div style="font-size: 13px; color: var(--text-secondary);">
                <div style="margin-bottom: 4px;">
                    <a href="https://gohugo.io/documentation/" target="_blank" style="color: var(--accent-primary); display: flex; align-items: center; gap: 4px;">
                        <?= pugo_icon('external-link', 12) ?> Hugo Docs
                    </a>
                </div>
                <div>
                    <a href="https://www.markdownguide.org/" target="_blank" style="color: var(--accent-primary); display: flex; align-items: center; gap: 4px;">
                        <?= pugo_icon('external-link', 12) ?> Markdown Guide
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}
.btn-sm svg {
    width: 14px;
    height: 14px;
}
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>
