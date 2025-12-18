<?php
/**
 * Pugo Core 3.0 - Netlify Deployment Adapter
 */

namespace Pugo\Deployment\Adapters;

use Pugo\Deployment\DeploymentAdapter;
use Pugo\Deployment\DeployResult;

class NetlifyAdapter implements DeploymentAdapter
{
    protected array $config = [];
    protected string $apiBase = 'https://api.netlify.com/api/v1';
    
    public function getId(): string
    {
        return 'netlify';
    }
    
    public function getName(): string
    {
        return 'Netlify';
    }
    
    public function getIcon(): string
    {
        return 'cloud';
    }
    
    public function getDescription(): string
    {
        return 'Deploy directly to Netlify via API or deploy hook';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['site_id']) && 
               (!empty($this->config['auth_token']) || !empty($this->config['deploy_hook']));
    }
    
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (empty($config['site_id']) && empty($config['deploy_hook'])) {
            $errors[] = 'Either Site ID or Deploy Hook URL is required';
        }
        
        if (!empty($config['site_id']) && empty($config['auth_token']) && empty($config['deploy_hook'])) {
            $errors[] = 'Auth Token is required when using Site ID';
        }
        
        return $errors;
    }
    
    public function configure(array $config): void
    {
        $this->config = array_merge([
            'site_id' => '',
            'auth_token' => '',
            'deploy_hook' => '',
            'production' => true,
        ], $config);
    }
    
    public function deploy(string $sourceDir, array $options = []): DeployResult
    {
        // Prefer deploy hook for simplicity
        if (!empty($this->config['deploy_hook'])) {
            return $this->triggerDeployHook();
        }
        
        // Direct API deploy
        return $this->directDeploy($sourceDir, $options);
    }
    
    protected function triggerDeployHook(): DeployResult
    {
        $hook = $this->config['deploy_hook'];
        
        $ch = curl_init($hook);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code === 200 || $code === 201) {
            return DeployResult::pending('Build triggered on Netlify', [
                'response' => $response,
            ]);
        }
        
        return DeployResult::failure('Failed to trigger Netlify build (HTTP ' . $code . ')');
    }
    
    protected function directDeploy(string $sourceDir, array $options = []): DeployResult
    {
        $siteId = $this->config['site_id'];
        $token = $this->config['auth_token'];
        
        // Create a zip of the source directory
        $zipPath = sys_get_temp_dir() . '/pugo-deploy-' . uniqid() . '.zip';
        
        if (!$this->createZip($sourceDir, $zipPath)) {
            return DeployResult::failure('Failed to create deployment archive');
        }
        
        // Upload to Netlify
        $url = "{$this->apiBase}/sites/{$siteId}/deploys";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/zip',
            ],
            CURLOPT_POSTFIELDS => file_get_contents($zipPath),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Clean up
        unlink($zipPath);
        
        if ($code >= 200 && $code < 300) {
            $data = json_decode($response, true);
            
            return DeployResult::success('Deployed to Netlify', [
                'deploy_id' => $data['id'] ?? null,
                'url' => $data['deploy_ssl_url'] ?? $data['deploy_url'] ?? null,
                'state' => $data['state'] ?? 'processing',
            ]);
        }
        
        return DeployResult::failure('Netlify deploy failed (HTTP ' . $code . ')', [$response]);
    }
    
    public function getStatus(): ?array
    {
        if (empty($this->config['site_id']) || empty($this->config['auth_token'])) {
            return null;
        }
        
        $siteId = $this->config['site_id'];
        $token = $this->config['auth_token'];
        
        // Get latest deploy
        $url = "{$this->apiBase}/sites/{$siteId}/deploys?per_page=1";
        
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
        
        $deploys = json_decode($response, true);
        
        if (empty($deploys)) {
            return ['status' => 'no_deploys'];
        }
        
        $latest = $deploys[0];
        
        return [
            'deploy_id' => $latest['id'],
            'state' => $latest['state'],
            'url' => $latest['deploy_ssl_url'] ?? $latest['deploy_url'],
            'created_at' => $latest['created_at'],
            'published_at' => $latest['published_at'] ?? null,
            'branch' => $latest['branch'] ?? null,
        ];
    }
    
    public function getSettingsFields(): array
    {
        return [
            'site_id' => [
                'type' => 'text',
                'label' => 'Site ID',
                'help' => 'Found in Site settings > General > Site details',
                'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
            ],
            'auth_token' => [
                'type' => 'password',
                'label' => 'Personal Access Token',
                'help' => 'Generate at User settings > Applications > Personal access tokens',
            ],
            'deploy_hook' => [
                'type' => 'text',
                'label' => 'Deploy Hook URL (alternative)',
                'help' => 'Found in Site settings > Build & deploy > Build hooks',
                'placeholder' => 'https://api.netlify.com/build_hooks/...',
            ],
            'production' => [
                'type' => 'checkbox',
                'label' => 'Deploy to production',
                'default' => true,
            ],
        ];
    }
    
    public function testConnection(): DeployResult
    {
        if (!empty($this->config['deploy_hook'])) {
            // Can't really test deploy hook without triggering
            return DeployResult::success('Deploy hook configured');
        }
        
        if (empty($this->config['auth_token'])) {
            return DeployResult::failure('Auth token not configured');
        }
        
        // Test API access
        $ch = curl_init($this->apiBase . '/user');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config['auth_token'],
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code === 200) {
            $user = json_decode($response, true);
            return DeployResult::success('Connected as ' . ($user['email'] ?? 'unknown'));
        }
        
        return DeployResult::failure('Invalid Netlify token');
    }
    
    protected function createZip(string $sourceDir, string $zipPath): bool
    {
        $zip = new \ZipArchive();
        
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        
        $sourceDir = realpath($sourceDir);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        return $zip->close();
    }
}

