<?php
/**
 * Pugo Core 3.0 - Git Deployment Adapter
 * 
 * Deploys via Git push → CI/CD pipeline
 */

namespace Pugo\Deployment\Adapters;

use Pugo\Deployment\DeploymentAdapter;
use Pugo\Deployment\DeployResult;

class GitAdapter implements DeploymentAdapter
{
    protected array $config = [];
    protected string $hugoRoot;
    
    public function __construct(?string $hugoRoot = null)
    {
        $this->hugoRoot = $hugoRoot ?? (defined('HUGO_ROOT') ? HUGO_ROOT : getcwd());
    }
    
    public function getId(): string
    {
        return 'git';
    }
    
    public function getName(): string
    {
        return 'Git CI/CD';
    }
    
    public function getIcon(): string
    {
        return 'git-branch';
    }
    
    public function getDescription(): string
    {
        return 'Deploy via Git push to trigger CI/CD pipeline (GitLab, GitHub, etc.)';
    }
    
    public function isConfigured(): bool
    {
        // Check if git is available and repo is initialized
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git status 2>&1', $output, $code);
        return $code === 0;
    }
    
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (empty($config['branch'])) {
            $errors[] = 'Branch is required';
        }
        
        return $errors;
    }
    
    public function configure(array $config): void
    {
        $this->config = array_merge([
            'branch' => 'main',
            'remote' => 'origin',
            'auto_commit' => true,
            'commit_template' => 'content: Update - {date}',
            'platform' => 'gitlab',
            'trigger_pipeline' => false,
        ], $config);
    }
    
    public function deploy(string $sourceDir, array $options = []): DeployResult
    {
        $branch = $this->config['branch'] ?? 'main';
        $remote = $this->config['remote'] ?? 'origin';
        $message = $options['message'] ?? $this->generateCommitMessage();
        
        $output = [];
        
        // Configure git user if needed
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git config user.name 2>/dev/null', $nameCheck);
        if (empty($nameCheck)) {
            exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git config user.name "Pugo Admin" 2>&1');
            exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git config user.email "admin@pugo.local" 2>&1');
        }
        
        // Stage all changes
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git add -A 2>&1', $addOutput, $addCode);
        $output = array_merge($output, $addOutput);
        
        if ($addCode !== 0) {
            return DeployResult::failure('Git add failed', $output);
        }
        
        // Check if there are changes to commit
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git status --porcelain 2>&1', $statusOutput);
        
        if (empty($statusOutput)) {
            return DeployResult::success('No changes to deploy', ['output' => 'Working tree clean']);
        }
        
        // Commit
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git commit -m ' . escapeshellarg($message) . ' 2>&1', $commitOutput, $commitCode);
        $output = array_merge($output, $commitOutput);
        
        if ($commitCode !== 0 && !$this->isNothingToCommit($commitOutput)) {
            return DeployResult::failure('Git commit failed', $output);
        }
        
        // Push
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git push ' . escapeshellarg($remote) . ' ' . escapeshellarg($branch) . ' 2>&1', $pushOutput, $pushCode);
        $output = array_merge($output, $pushOutput);
        
        if ($pushCode !== 0) {
            return DeployResult::failure('Git push failed', $output);
        }
        
        // Trigger pipeline if configured
        $pipelineInfo = null;
        if ($this->config['trigger_pipeline'] ?? false) {
            $pipelineInfo = $this->triggerPipeline();
        }
        
        return DeployResult::success('Pushed to ' . $branch, [
            'output' => implode("\n", $output),
            'branch' => $branch,
            'remote' => $remote,
            'commit' => $this->getLastCommit(),
            'pipeline' => $pipelineInfo,
        ]);
    }
    
    public function getStatus(): ?array
    {
        $status = [
            'configured' => $this->isConfigured(),
            'branch' => null,
            'remote_url' => null,
            'last_commit' => null,
            'pending_changes' => [],
        ];
        
        if (!$status['configured']) {
            return $status;
        }
        
        // Get current branch
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git branch --show-current 2>&1', $branchOutput);
        $status['branch'] = trim($branchOutput[0] ?? '');
        
        // Get remote URL
        $remote = $this->config['remote'] ?? 'origin';
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git remote get-url ' . escapeshellarg($remote) . ' 2>&1', $urlOutput);
        $status['remote_url'] = trim($urlOutput[0] ?? '');
        
        // Get last commit
        $status['last_commit'] = $this->getLastCommit();
        
        // Get pending changes
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git status --porcelain 2>&1', $changesOutput);
        foreach ($changesOutput as $line) {
            if (preg_match('/^(.{2})\s+(.+)$/', $line, $m)) {
                $status['pending_changes'][] = [
                    'status' => trim($m[1]),
                    'file' => $m[2],
                ];
            }
        }
        
        return $status;
    }
    
    public function getSettingsFields(): array
    {
        return [
            'platform' => [
                'type' => 'select',
                'label' => 'Git Platform',
                'options' => [
                    'gitlab' => 'GitLab',
                    'github' => 'GitHub',
                    'gitea' => 'Gitea',
                    'bitbucket' => 'Bitbucket',
                ],
                'default' => 'gitlab',
            ],
            'remote' => [
                'type' => 'text',
                'label' => 'Remote Name',
                'default' => 'origin',
                'placeholder' => 'origin',
            ],
            'branch' => [
                'type' => 'text',
                'label' => 'Branch',
                'default' => 'main',
                'placeholder' => 'main',
            ],
            'auto_commit' => [
                'type' => 'checkbox',
                'label' => 'Auto-commit on save',
                'default' => false,
            ],
            'trigger_pipeline' => [
                'type' => 'checkbox',
                'label' => 'Trigger CI/CD pipeline on push',
                'default' => false,
            ],
            'commit_template' => [
                'type' => 'text',
                'label' => 'Commit Message Template',
                'default' => 'content: Update - {date}',
                'help' => 'Use {date}, {time}, {action} placeholders',
            ],
        ];
    }
    
    public function testConnection(): DeployResult
    {
        $remote = $this->config['remote'] ?? 'origin';
        
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git ls-remote ' . escapeshellarg($remote) . ' HEAD 2>&1', $output, $code);
        
        if ($code === 0) {
            return DeployResult::success('Git remote connection successful');
        }
        
        return DeployResult::failure('Cannot connect to Git remote', $output);
    }
    
    protected function generateCommitMessage(): string
    {
        $template = $this->config['commit_template'] ?? 'content: Update - {date}';
        
        return str_replace(
            ['{date}', '{time}', '{action}'],
            [date('Y-m-d'), date('H:i'), 'update'],
            $template
        );
    }
    
    protected function getLastCommit(): ?array
    {
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && git log -1 --format="%H|%s|%ai|%an" 2>&1', $output);
        
        if (empty($output[0])) {
            return null;
        }
        
        $parts = explode('|', $output[0]);
        
        return [
            'hash' => $parts[0] ?? '',
            'message' => $parts[1] ?? '',
            'date' => $parts[2] ?? '',
            'author' => $parts[3] ?? '',
        ];
    }
    
    protected function isNothingToCommit(array $output): bool
    {
        $text = implode(' ', $output);
        return str_contains($text, 'nothing to commit');
    }
    
    protected function triggerPipeline(): ?array
    {
        $platform = $this->config['platform'] ?? 'gitlab';
        
        if ($platform === 'gitlab' && !empty($this->config['gitlab'])) {
            return $this->triggerGitLabPipeline();
        }
        
        if ($platform === 'github' && !empty($this->config['github'])) {
            return $this->triggerGitHubWorkflow();
        }
        
        return null;
    }
    
    protected function triggerGitLabPipeline(): ?array
    {
        $gitlab = $this->config['gitlab'] ?? [];
        $url = $gitlab['url'] ?? '';
        $projectId = $gitlab['project_id'] ?? '';
        $token = $gitlab['trigger_token'] ?? '';
        $ref = $gitlab['ref'] ?? $this->config['branch'] ?? 'main';
        
        if (!$url || !$projectId || !$token) {
            return null;
        }
        
        $apiUrl = rtrim($url, '/') . "/api/v4/projects/{$projectId}/trigger/pipeline";
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'token' => $token,
                'ref' => $ref,
            ]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code >= 200 && $code < 300) {
            $data = json_decode($response, true);
            return [
                'id' => $data['id'] ?? null,
                'status' => $data['status'] ?? 'triggered',
                'web_url' => $data['web_url'] ?? null,
            ];
        }
        
        return ['error' => 'Failed to trigger pipeline'];
    }
    
    protected function triggerGitHubWorkflow(): ?array
    {
        // GitHub Actions workflow dispatch
        $github = $this->config['github'] ?? [];
        $repo = $github['repo'] ?? '';
        $token = $github['token'] ?? '';
        $workflow = $github['workflow'] ?? 'deploy.yml';
        $ref = $this->config['branch'] ?? 'main';
        
        if (!$repo || !$token) {
            return null;
        }
        
        $apiUrl = "https://api.github.com/repos/{$repo}/actions/workflows/{$workflow}/dispatches";
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/vnd.github.v3+json',
                'User-Agent: Pugo-Admin',
            ],
            CURLOPT_POSTFIELDS => json_encode(['ref' => $ref]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code === 204) {
            return ['status' => 'triggered', 'workflow' => $workflow];
        }
        
        return ['error' => 'Failed to trigger workflow'];
    }
}

