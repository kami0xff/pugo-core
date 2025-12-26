<?php
/**
 * Simple Deploy Function
 * 
 * Pushes the built Hugo output (public/) to a git remote.
 * The public/ folder should have its own .git initialized pointing to your deploy repo.
 */

// Path to deploy SSH files (key + known_hosts in same folder)
define('DEPLOY_SSH_DIR', '/deploy/.ssh');
define('DEPLOY_SSH_KEY', DEPLOY_SSH_DIR . '/github_deploy_key');
define('DEPLOY_KNOWN_HOSTS', DEPLOY_SSH_DIR . '/known_hosts');

/**
 * Set up environment for git commands (using putenv for PHP exec compatibility)
 */
function setup_git_env() {
    putenv('HOME=/var/www');
    if (file_exists(DEPLOY_SSH_KEY)) {
        putenv('GIT_SSH_COMMAND=ssh -i ' . DEPLOY_SSH_KEY . ' -o UserKnownHostsFile=' . DEPLOY_KNOWN_HOSTS . ' -o StrictHostKeyChecking=no');
    }
}

/**
 * Get environment prefix (legacy, kept for compatibility but empty now)
 */
function get_git_env() {
    setup_git_env();
    return ''; // Environment is set via putenv now
}

/**
 * Deploy the built site to production
 * 
 * @param string $message Commit message
 * @return array ['success' => bool, 'output' => string]
 */
function deploy_site($message = null) {
    $public_dir = HUGO_ROOT . '/public';
    
    // Set up environment for SSH
    setup_git_env();
    
    if (!is_dir($public_dir)) {
        return ['success' => false, 'output' => 'Public directory does not exist. Run Hugo build first.'];
    }
    
    // Check if public/ has git initialized
    if (!is_dir($public_dir . '/.git')) {
        return [
            'success' => false, 
            'output' => "Git not initialized in public/ folder.\n\nTo set up deployment:\n" .
                       "1. cd " . $public_dir . "\n" .
                       "2. git init\n" .
                       "3. git remote add origin git@github.com:youruser/yoursite.git\n" .
                       "4. git push -u origin main"
        ];
    }
    
    $message = $message ?? 'Deploy: ' . date('Y-m-d H:i:s');
    $output = [];
    
    // Mark directory as safe
    exec("git config --global --add safe.directory " . escapeshellarg($public_dir) . " 2>&1");
    
    // Configure git user if not set
    exec("cd " . escapeshellarg($public_dir) . " && git config user.name 2>/dev/null", $nameCheck);
    if (empty($nameCheck)) {
        exec("cd " . escapeshellarg($public_dir) . " && git config user.name 'Pugo Deploy'");
        exec("cd " . escapeshellarg($public_dir) . " && git config user.email 'deploy@pugo.local'");
    }
    
    // Add all files
    exec("cd " . escapeshellarg($public_dir) . " && git add -A 2>&1", $addOutput, $addCode);
    $output = array_merge($output, $addOutput);
    
    // Check if there are changes
    exec("cd " . escapeshellarg($public_dir) . " && git status --porcelain 2>&1", $statusOutput);
    
    if (empty($statusOutput)) {
        return ['success' => true, 'output' => 'No changes to deploy. Site is up to date.'];
    }
    
    // Commit
    exec("cd " . escapeshellarg($public_dir) . " && git commit -m " . escapeshellarg($message) . " 2>&1", $commitOutput, $commitCode);
    $output = array_merge($output, $commitOutput);
    
    if ($commitCode !== 0 && !empty($statusOutput)) {
        return ['success' => false, 'output' => "Commit failed:\n" . implode("\n", $output)];
    }
    
    // Push with SSH key
    exec("cd " . escapeshellarg($public_dir) . " && git push origin HEAD 2>&1", $pushOutput, $pushCode);
    $output = array_merge($output, $pushOutput);
    
    if ($pushCode !== 0) {
        return ['success' => false, 'output' => "Push failed:\n" . implode("\n", $output)];
    }
    
    return ['success' => true, 'output' => implode("\n", $output)];
}

/**
 * Get deployment status
 */
function get_deploy_status() {
    $public_dir = HUGO_ROOT . '/public';
    
    if (!is_dir($public_dir . '/.git')) {
        return [
            'configured' => false,
            'message' => 'Not configured'
        ];
    }
    
    // Get remote URL
    exec("cd " . escapeshellarg($public_dir) . " && git remote get-url origin 2>&1", $remoteOutput, $remoteCode);
    $remote_url = ($remoteCode === 0 && !empty($remoteOutput)) ? $remoteOutput[0] : null;
    
    // Get branch
    exec("cd " . escapeshellarg($public_dir) . " && git branch --show-current 2>&1", $branchOutput);
    $branch = !empty($branchOutput) ? $branchOutput[0] : 'main';
    
    // Get last commit
    exec("cd " . escapeshellarg($public_dir) . " && git log -1 --pretty=format:'%h|%s|%ar' 2>&1", $logOutput);
    $last_commit = null;
    if (!empty($logOutput[0]) && strpos($logOutput[0], '|') !== false) {
        $parts = explode('|', $logOutput[0]);
        $last_commit = [
            'hash' => $parts[0] ?? '',
            'message' => $parts[1] ?? '',
            'date' => $parts[2] ?? ''
        ];
    }
    
    // Check for uncommitted changes
    exec("cd " . escapeshellarg($public_dir) . " && git status --porcelain 2>&1", $statusOutput);
    $has_changes = !empty($statusOutput);
    
    return [
        'configured' => true,
        'remote_url' => $remote_url,
        'branch' => $branch,
        'last_commit' => $last_commit,
        'has_changes' => $has_changes,
        'changes_count' => count($statusOutput)
    ];
}

/**
 * Initialize deployment repo in public/
 */
function init_deploy_repo($remote_url) {
    $public_dir = HUGO_ROOT . '/public';
    
    // Set up environment for SSH
    setup_git_env();
    
    if (!is_dir($public_dir)) {
        mkdir($public_dir, 0755, true);
    }
    
    $output = [];
    
    // Mark directory as safe
    exec("git config --global --add safe.directory " . escapeshellarg($public_dir) . " 2>&1");
    
    // Init if not already
    if (!is_dir($public_dir . '/.git')) {
        exec("cd " . escapeshellarg($public_dir) . " && git init 2>&1", $initOutput);
        $output = array_merge($output, $initOutput);
        exec("cd " . escapeshellarg($public_dir) . " && git branch -M main 2>&1");
    }
    
    // Configure git user
    exec("cd " . escapeshellarg($public_dir) . " && git config user.name 'Pugo Deploy'");
    exec("cd " . escapeshellarg($public_dir) . " && git config user.email 'deploy@pugo.local'");
    
    // Add remote
    exec("cd " . escapeshellarg($public_dir) . " && git remote remove origin 2>&1");
    exec("cd " . escapeshellarg($public_dir) . " && git remote add origin " . escapeshellarg($remote_url) . " 2>&1", $remoteOutput, $remoteCode);
    $output = array_merge($output, $remoteOutput);
    
    if ($remoteCode !== 0) {
        return ['success' => false, 'output' => implode("\n", $output)];
    }
    
    // Test SSH connection if using SSH URL
    if (strpos($remote_url, 'git@') === 0) {
        exec("ssh -T git@github.com 2>&1", $sshTest, $sshCode);
        if ($sshCode !== 1) { // GitHub returns 1 on success with "successfully authenticated" message
            $output[] = "\n⚠️ SSH key may need setup. Check /deploy/.ssh/github_deploy_key";
        }
    }
    
    return ['success' => true, 'output' => "Deployment configured!\nRemote: $remote_url"];
}

