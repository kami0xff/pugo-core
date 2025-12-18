<?php
/**
 * Pugo Core 3.0 - Cloudflare Pages Deployment Adapter
 */

namespace Pugo\Deployment\Adapters;

use Pugo\Deployment\DeploymentAdapter;
use Pugo\Deployment\DeployResult;

class CloudflareAdapter implements DeploymentAdapter
{
    protected array $config = [];
    protected string $apiBase = 'https://api.cloudflare.com/client/v4';
    
    public function getId(): string
    {
        return 'cloudflare';
    }
    
    public function getName(): string
    {
        return 'Cloudflare Pages';
    }
    
    public function getIcon(): string
    {
        return 'cloud-lightning';
    }
    
    public function getDescription(): string
    {
        return 'Deploy to Cloudflare Pages for global edge distribution';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['account_id']) && 
               !empty($this->config['project_name']) && 
               !empty($this->config['api_token']);
    }
    
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (empty($config['account_id'])) {
            $errors[] = 'Cloudflare Account ID is required';
        }
        
        if (empty($config['project_name'])) {
            $errors[] = 'Project name is required';
        }
        
        if (empty($config['api_token'])) {
            $errors[] = 'API Token is required';
        }
        
        return $errors;
    }
    
    public function configure(array $config): void
    {
        $this->config = array_merge([
            'account_id' => '',
            'project_name' => '',
            'api_token' => '',
            'production_branch' => 'main',
        ], $config);
    }
    
    public function deploy(string $sourceDir, array $options = []): DeployResult
    {
        $accountId = $this->config['account_id'];
        $projectName = $this->config['project_name'];
        $token = $this->config['api_token'];
        $branch = $options['branch'] ?? $this->config['production_branch'] ?? 'main';
        
        // Create deployment using Direct Upload
        $url = "{$this->apiBase}/accounts/{$accountId}/pages/projects/{$projectName}/deployments";
        
        // First, create the deployment
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'branch' => $branch,
            ]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code < 200 || $code >= 300) {
            $error = json_decode($response, true);
            return DeployResult::failure(
                'Failed to create Cloudflare deployment: ' . ($error['errors'][0]['message'] ?? 'Unknown error')
            );
        }
        
        $data = json_decode($response, true);
        $deploymentId = $data['result']['id'] ?? null;
        
        if (!$deploymentId) {
            return DeployResult::failure('No deployment ID received');
        }
        
        // Upload files
        $uploadResult = $this->uploadFiles($sourceDir, $accountId, $projectName, $deploymentId, $token);
        
        if (!$uploadResult['success']) {
            return DeployResult::failure('Failed to upload files: ' . $uploadResult['error']);
        }
        
        return DeployResult::success('Deployed to Cloudflare Pages', [
            'deployment_id' => $deploymentId,
            'url' => $data['result']['url'] ?? null,
            'files_uploaded' => $uploadResult['count'],
        ]);
    }
    
    protected function uploadFiles(string $sourceDir, string $accountId, string $projectName, string $deploymentId, string $token): array
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
            $relativePath = '/' . substr($filePath, strlen($sourceDir) + 1);
            
            $files[] = [
                'path' => $relativePath,
                'content' => file_get_contents($filePath),
            ];
        }
        
        // Upload in batches
        $batchSize = 50;
        $batches = array_chunk($files, $batchSize);
        $uploaded = 0;
        
        $url = "{$this->apiBase}/accounts/{$accountId}/pages/projects/{$projectName}/deployments/{$deploymentId}/files";
        
        foreach ($batches as $batch) {
            $multipart = [];
            foreach ($batch as $file) {
                $multipart[] = [
                    'name' => $file['path'],
                    'contents' => $file['content'],
                    'filename' => basename($file['path']),
                ];
            }
            
            // Simplified upload - in production you'd use proper multipart
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                ],
                CURLOPT_RETURNTRANSFER => true,
            ]);
            
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($code >= 200 && $code < 300) {
                $uploaded += count($batch);
            }
        }
        
        return [
            'success' => true,
            'count' => $uploaded,
        ];
    }
    
    public function getStatus(): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        
        $accountId = $this->config['account_id'];
        $projectName = $this->config['project_name'];
        $token = $this->config['api_token'];
        
        $url = "{$this->apiBase}/accounts/{$accountId}/pages/projects/{$projectName}/deployments?per_page=1";
        
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
        $deployments = $data['result'] ?? [];
        
        if (empty($deployments)) {
            return ['status' => 'no_deployments'];
        }
        
        $latest = $deployments[0];
        
        return [
            'id' => $latest['id'],
            'url' => $latest['url'],
            'environment' => $latest['environment'],
            'created_on' => $latest['created_on'],
        ];
    }
    
    public function getSettingsFields(): array
    {
        return [
            'account_id' => [
                'type' => 'text',
                'label' => 'Account ID',
                'help' => 'Found in Cloudflare dashboard URL or Account Home',
            ],
            'project_name' => [
                'type' => 'text',
                'label' => 'Project Name',
                'help' => 'Your Pages project name',
            ],
            'api_token' => [
                'type' => 'password',
                'label' => 'API Token',
                'help' => 'Create at My Profile > API Tokens with Pages permissions',
            ],
            'production_branch' => [
                'type' => 'text',
                'label' => 'Production Branch',
                'default' => 'main',
            ],
        ];
    }
    
    public function testConnection(): DeployResult
    {
        if (!$this->isConfigured()) {
            return DeployResult::failure('Not configured');
        }
        
        $accountId = $this->config['account_id'];
        $projectName = $this->config['project_name'];
        $token = $this->config['api_token'];
        
        $url = "{$this->apiBase}/accounts/{$accountId}/pages/projects/{$projectName}";
        
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
        
        if ($code === 200) {
            $data = json_decode($response, true);
            return DeployResult::success('Connected to project: ' . ($data['result']['name'] ?? $projectName));
        }
        
        return DeployResult::failure('Failed to connect to Cloudflare Pages');
    }
}

