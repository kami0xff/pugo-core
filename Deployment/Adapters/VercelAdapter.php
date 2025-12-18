<?php
/**
 * Pugo Core 3.0 - Vercel Deployment Adapter
 */

namespace Pugo\Deployment\Adapters;

use Pugo\Deployment\DeploymentAdapter;
use Pugo\Deployment\DeployResult;

class VercelAdapter implements DeploymentAdapter
{
    protected array $config = [];
    protected string $apiBase = 'https://api.vercel.com';
    
    public function getId(): string
    {
        return 'vercel';
    }
    
    public function getName(): string
    {
        return 'Vercel';
    }
    
    public function getIcon(): string
    {
        return 'triangle';
    }
    
    public function getDescription(): string
    {
        return 'Deploy to Vercel for instant previews and edge deployment';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['token']) && !empty($this->config['project_id']);
    }
    
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (empty($config['token'])) {
            $errors[] = 'Vercel token is required';
        }
        
        if (empty($config['project_id'])) {
            $errors[] = 'Project ID is required';
        }
        
        return $errors;
    }
    
    public function configure(array $config): void
    {
        $this->config = array_merge([
            'token' => '',
            'project_id' => '',
            'team_id' => '',
            'production' => false,
        ], $config);
    }
    
    public function deploy(string $sourceDir, array $options = []): DeployResult
    {
        $token = $this->config['token'];
        $projectId = $this->config['project_id'];
        $teamId = $this->config['team_id'] ?? null;
        $production = $options['production'] ?? $this->config['production'] ?? false;
        
        // Collect files
        $files = $this->collectFiles($sourceDir);
        
        if (empty($files)) {
            return DeployResult::failure('No files to deploy');
        }
        
        // Create deployment
        $url = "{$this->apiBase}/v13/deployments";
        if ($teamId) {
            $url .= '?teamId=' . $teamId;
        }
        
        $payload = [
            'name' => $this->config['project_name'] ?? 'pugo-site',
            'files' => $files,
            'project' => $projectId,
            'target' => $production ? 'production' : null,
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code >= 200 && $code < 300) {
            $data = json_decode($response, true);
            
            return DeployResult::success('Deployed to Vercel', [
                'id' => $data['id'] ?? null,
                'url' => $data['url'] ?? null,
                'ready_state' => $data['readyState'] ?? null,
            ]);
        }
        
        $error = json_decode($response, true);
        return DeployResult::failure(
            'Vercel deploy failed: ' . ($error['error']['message'] ?? 'Unknown error'),
            [$response]
        );
    }
    
    public function getStatus(): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        
        $token = $this->config['token'];
        $projectId = $this->config['project_id'];
        $teamId = $this->config['team_id'] ?? null;
        
        $url = "{$this->apiBase}/v6/deployments?projectId={$projectId}&limit=1";
        if ($teamId) {
            $url .= '&teamId=' . $teamId;
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        $deployments = $data['deployments'] ?? [];
        
        if (empty($deployments)) {
            return ['status' => 'no_deployments'];
        }
        
        $latest = $deployments[0];
        
        return [
            'id' => $latest['uid'],
            'url' => 'https://' . $latest['url'],
            'state' => $latest['readyState'] ?? $latest['state'],
            'created_at' => date('c', $latest['createdAt'] / 1000),
            'target' => $latest['target'] ?? 'preview',
        ];
    }
    
    public function getSettingsFields(): array
    {
        return [
            'token' => [
                'type' => 'password',
                'label' => 'Vercel Token',
                'help' => 'Generate at vercel.com/account/tokens',
            ],
            'project_id' => [
                'type' => 'text',
                'label' => 'Project ID',
                'help' => 'Found in Project Settings > General',
            ],
            'team_id' => [
                'type' => 'text',
                'label' => 'Team ID (optional)',
                'help' => 'Required for team projects',
            ],
            'project_name' => [
                'type' => 'text',
                'label' => 'Project Name',
                'placeholder' => 'my-site',
            ],
            'production' => [
                'type' => 'checkbox',
                'label' => 'Deploy to production by default',
                'default' => false,
            ],
        ];
    }
    
    public function testConnection(): DeployResult
    {
        if (empty($this->config['token'])) {
            return DeployResult::failure('Token not configured');
        }
        
        $ch = curl_init($this->apiBase . '/v2/user');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config['token'],
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code === 200) {
            $user = json_decode($response, true);
            return DeployResult::success('Connected as ' . ($user['user']['username'] ?? 'unknown'));
        }
        
        return DeployResult::failure('Invalid Vercel token');
    }
    
    protected function collectFiles(string $sourceDir): array
    {
        $files = [];
        $sourceDir = realpath($sourceDir);
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            
            // Vercel file format
            $files[] = [
                'file' => $relativePath,
                'data' => base64_encode(file_get_contents($filePath)),
                'encoding' => 'base64',
            ];
        }
        
        return $files;
    }
}

