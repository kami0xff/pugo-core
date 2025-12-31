<?php
/**
 * Settings View
 */
?>
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
        Deploy to Production
    </h3>
    
    <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">
        Build Hugo and push the <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px;">public/</code> folder to your deployment repo.
        Your server pulls from this repo to go live.
    </p>
    
    <?php if ($deployStatus['configured']): ?>
    <!-- Configured: Show status and deploy button -->
    <div class="grid grid-2" style="gap: 24px;">
        <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 20px;">
            <h4 style="font-size: 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <?= pugo_icon('check-circle', 18) ?>
                <span style="color: var(--accent-green);">Deployment Configured</span>
            </h4>
            
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Remote</span>
                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; max-width: 250px; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($deployStatus['remote_url'] ?? '') ?>">
                        <?= htmlspecialchars($deployStatus['remote_url'] ?? 'Not set') ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Branch</span>
                    <span style="font-family: 'JetBrains Mono', monospace; color: var(--accent-primary);">
                        <?= htmlspecialchars($deployStatus['branch'] ?? 'main') ?>
                    </span>
                </div>
                
                <?php if ($deployStatus['last_commit']): ?>
                <div style="border-top: 1px solid var(--border-color); padding-top: 10px; margin-top: 4px;">
                    <div style="color: var(--text-muted); margin-bottom: 6px; font-size: 12px;">Last Deploy</div>
                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 11px;">
                        <span style="color: var(--accent-secondary);"><?= htmlspecialchars($deployStatus['last_commit']['hash']) ?></span>
                        <span style="color: var(--text-muted);"> · <?= htmlspecialchars($deployStatus['last_commit']['date']) ?></span>
                    </div>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                        <?= htmlspecialchars($deployStatus['last_commit']['message']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Connection Test -->
                <div style="border-top: 1px solid var(--border-color); padding-top: 10px; margin-top: 4px;">
                    <button type="button" onclick="testConnection()" class="btn btn-sm btn-secondary" style="width: 100%;">
                        <?= pugo_icon('link', 14) ?>
                        Test Connection
                    </button>
                    <div id="connectionResult" style="margin-top: 8px; font-size: 11px; display: none;"></div>
                </div>
            </div>
        </div>
        
        <div>
            <?php if ($deploy_result): ?>
            <div style="background: <?= $deploy_result['success'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(225, 29, 72, 0.1)' ?>; 
                        border: 1px solid <?= $deploy_result['success'] ? '#10b981' : '#e11d48' ?>; 
                        border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <div style="font-weight: 600; margin-bottom: 8px; color: <?= $deploy_result['success'] ? '#10b981' : '#e11d48' ?>; display: flex; align-items: center; gap: 8px;">
                    <?= pugo_icon($deploy_result['success'] ? 'check-circle' : 'x-circle', 16) ?>
                    <?= $deploy_result['success'] ? 'Deployed Successfully!' : 'Deployment Failed' ?>
                </div>
                <pre style="font-size: 11px; color: var(--text-secondary); white-space: pre-wrap; margin: 0; font-family: 'JetBrains Mono', monospace;"><?= htmlspecialchars($deploy_result['output']) ?></pre>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="deploy">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Commit Message</label>
                    <input type="text" name="commit_message" class="form-input" 
                           placeholder="Deploy: Update content" 
                           value="Deploy: <?= date('Y-m-d H:i') ?>">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px;">
                    <?= pugo_icon('upload-cloud', 18) ?>
                    Build &amp; Deploy to Production
                </button>
            </form>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Not configured: Show setup form -->
    <div style="background: var(--bg-tertiary); border-radius: 12px; padding: 24px; max-width: 500px;">
        <h4 style="font-size: 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <?= pugo_icon('git-branch', 18) ?>
            Setup Deployment
        </h4>
        
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
            Enter your deployment repository URL. This is where your built site will be pushed.
        </p>
        
        <?php if ($setup_result): ?>
        <div style="background: <?= $setup_result['success'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(225, 29, 72, 0.1)' ?>; 
                    padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;
                    color: <?= $setup_result['success'] ? '#10b981' : '#e11d48' ?>;">
            <?= htmlspecialchars($setup_result['output']) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="setup_deploy">
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label">Git Remote URL</label>
                <input type="text" name="remote_url" class="form-input" 
                       placeholder="git@github.com:user/mysite.git"
                       required>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                    SSH format: <code>git@github.com:user/repo.git</code><br>
                    HTTPS format: <code>https://github.com/user/repo.git</code>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <?= pugo_icon('check', 16) ?>
                Configure Deployment
            </button>
        </form>
    </div>
    <?php endif; ?>
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
        
        <?php if ($buildResult): ?>
        <div style="background: <?= $buildResult['success'] ? 'rgba(16, 185, 129, 0.1)' : 'rgba(225, 29, 72, 0.1)' ?>; 
                    border: 1px solid <?= $buildResult['success'] ? '#10b981' : '#e11d48' ?>; 
                    border-radius: 8px; padding: 12px; margin-bottom: 16px;">
            <div style="font-weight: 600; margin-bottom: 8px; color: <?= $buildResult['success'] ? '#10b981' : '#e11d48' ?>; display: flex; align-items: center; gap: 8px;">
                <?= pugo_icon($buildResult['success'] ? 'check-circle' : 'x-circle', 16) ?>
                <?= $buildResult['success'] ? 'Build Successful' : 'Build Failed' ?>
            </div>
            <pre style="font-size: 11px; color: var(--text-secondary); white-space: pre-wrap; margin: 0; font-family: 'JetBrains Mono', monospace; max-height: 150px; overflow-y: auto;"><?= htmlspecialchars($buildResult['output']) ?></pre>
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

<!-- Hugo Configuration Editor -->
<div class="card" style="margin-top: 24px;">
    <h3 class="card-title" style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <?= pugo_icon('settings', 20) ?>
        Hugo Configuration
        <span style="font-size: 12px; font-weight: normal; color: var(--text-muted); margin-left: auto;">
            <?= htmlspecialchars(basename($hugo_config_path)) ?>
        </span>
    </h3>
    
    <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 13px;">
        Edit your Hugo site configuration. Changes will automatically trigger a site rebuild.
        <a href="https://gohugo.io/getting-started/configuration/" target="_blank" style="color: var(--accent-primary);">
            <?= pugo_icon('external-link', 12) ?> Configuration docs
        </a>
    </p>
    
    <?php if (isset($_SESSION['config_success'])): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <?= pugo_icon('check-circle', 16) ?>
        <?= $_SESSION['config_success']; unset($_SESSION['config_success']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['config_error'])): ?>
    <div style="background: rgba(225, 29, 72, 0.1); border: 1px solid #e11d48; color: #e11d48; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
        <?= pugo_icon('x-circle', 16) ?>
        <?= $_SESSION['config_error']; unset($_SESSION['config_error']); ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" id="hugoConfigForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_hugo_config">
        
        <div style="position: relative;">
            <textarea name="hugo_config" id="hugoConfigEditor" 
                style="width: 100%; height: 400px; font-family: 'JetBrains Mono', monospace; font-size: 13px; 
                       background: var(--bg-tertiary); color: var(--text-primary); border: 1px solid var(--border-color);
                       border-radius: 8px; padding: 16px; resize: vertical; line-height: 1.5;"
            ><?= htmlspecialchars($hugo_config_content) ?></textarea>
        </div>
        
        <div style="display: flex; gap: 12px; margin-top: 16px; align-items: center;">
            <button type="submit" class="btn btn-primary">
                <?= pugo_icon('save', 16) ?>
                Save Configuration
            </button>
            
            <button type="button" class="btn btn-secondary" onclick="restoreBackup()">
                <?= pugo_icon('rotate-cw', 16) ?>
                Restore Backup
            </button>
            
            <span style="font-size: 12px; color: var(--text-muted); margin-left: auto;">
                Ctrl+S to save
            </span>
        </div>
    </form>
    
    <!-- Quick Config Reference -->
    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
        <h4 style="font-size: 14px; margin-bottom: 12px;">Common Configuration Options</h4>
        <div class="grid grid-3" style="gap: 16px;">
            <div>
                <div style="font-size: 12px; font-weight: 600; margin-bottom: 6px;">Basic</div>
                <div style="font-size: 11px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                    baseURL = "https://..."<br>
                    title = "Site Title"<br>
                    languageCode = "en"
                </div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 600; margin-bottom: 6px;">Build</div>
                <div style="font-size: 11px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                    buildDrafts = false<br>
                    buildFuture = false<br>
                    minify = true
                </div>
            </div>
            <div>
                <div style="font-size: 12px; font-weight: 600; margin-bottom: 6px;">Params</div>
                <div style="font-size: 11px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
                    [params]<br>
                    &nbsp;&nbsp;description = "..."<br>
                    &nbsp;&nbsp;author = "..."
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Keyboard shortcut for saving config
document.getElementById('hugoConfigEditor').addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('hugoConfigForm').submit();
    }
});

// Restore backup function
function restoreBackup() {
    if (confirm('This will restore the previous configuration. Continue?')) {
        fetch('<?= $hugo_config_path ?>.backup')
            .then(r => r.text())
            .then(content => {
                document.getElementById('hugoConfigEditor').value = content;
                alert('Backup restored. Click "Save Configuration" to apply.');
            })
            .catch(() => alert('No backup found.'));
    }
}

// Test GitHub/Git connection
function testConnection() {
    const resultEl = document.getElementById('connectionResult');
    resultEl.style.display = 'block';
    resultEl.innerHTML = '<span style="color: var(--text-muted);">Testing connection...</span>';
    
    fetch('api.php?action=test_deploy_connection')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                resultEl.innerHTML = '<span style="color: var(--accent-green);">✓ Connection successful!</span><br><span style="color: var(--text-muted);">' + (data.output || '') + '</span>';
            } else {
                resultEl.innerHTML = '<span style="color: var(--accent-primary);">✗ Connection failed</span><br><span style="color: var(--text-muted);">' + (data.output || 'Unknown error') + '</span>';
            }
        })
        .catch(err => {
            resultEl.innerHTML = '<span style="color: var(--accent-primary);">✗ Error: ' + err.message + '</span>';
        });
}
</script>

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

