<?php
/**
 * Pugo Core 3.0 - Rsync/SSH Deployment Adapter
 */

namespace Pugo\Deployment\Adapters;

use Pugo\Deployment\DeploymentAdapter;
use Pugo\Deployment\DeployResult;

class RsyncAdapter implements DeploymentAdapter
{
    protected array $config = [];
    
    public function getId(): string
    {
        return 'rsync';
    }
    
    public function getName(): string
    {
        return 'Rsync/SSH';
    }
    
    public function getIcon(): string
    {
        return 'server';
    }
    
    public function getDescription(): string
    {
        return 'Deploy via rsync over SSH to any server';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['host']) && 
               !empty($this->config['user']) && 
               !empty($this->config['path']);
    }
    
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (empty($config['host'])) {
            $errors[] = 'Host is required';
        }
        
        if (empty($config['user'])) {
            $errors[] = 'User is required';
        }
        
        if (empty($config['path'])) {
            $errors[] = 'Remote path is required';
        }
        
        return $errors;
    }
    
    public function configure(array $config): void
    {
        $this->config = array_merge([
            'host' => '',
            'user' => '',
            'path' => '',
            'port' => 22,
            'key_path' => '',
            'options' => '-avz --delete',
            'exclude' => [],
        ], $config);
    }
    
    public function deploy(string $sourceDir, array $options = []): DeployResult
    {
        $host = $this->config['host'];
        $user = $this->config['user'];
        $path = rtrim($this->config['path'], '/') . '/';
        $port = $this->config['port'] ?? 22;
        $keyPath = $this->config['key_path'] ?? '';
        $rsyncOptions = $this->config['options'] ?? '-avz --delete';
        $excludes = $this->config['exclude'] ?? [];
        
        // Build rsync command
        $cmd = 'rsync ' . $rsyncOptions;
        
        // SSH options
        $sshOpts = "-p {$port}";
        if ($keyPath && file_exists($keyPath)) {
            $sshOpts .= " -i " . escapeshellarg($keyPath);
        }
        $cmd .= ' -e "ssh ' . $sshOpts . '"';
        
        // Excludes
        foreach ($excludes as $exclude) {
            $cmd .= ' --exclude=' . escapeshellarg($exclude);
        }
        
        // Source and destination
        $sourceDir = rtrim($sourceDir, '/') . '/';
        $cmd .= ' ' . escapeshellarg($sourceDir);
        $cmd .= ' ' . escapeshellarg("{$user}@{$host}:{$path}");
        
        // Execute
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        
        if ($code === 0) {
            return DeployResult::success('Deployed via rsync', [
                'host' => $host,
                'path' => $path,
                'output' => implode("\n", array_slice($output, -20)),
            ]);
        }
        
        return DeployResult::failure('Rsync failed', $output);
    }
    
    public function getStatus(): ?array
    {
        return [
            'configured' => $this->isConfigured(),
            'host' => $this->config['host'] ?? null,
            'path' => $this->config['path'] ?? null,
        ];
    }
    
    public function getSettingsFields(): array
    {
        return [
            'host' => [
                'type' => 'text',
                'label' => 'Host',
                'placeholder' => 'server.example.com',
                'required' => true,
            ],
            'user' => [
                'type' => 'text',
                'label' => 'Username',
                'placeholder' => 'deploy',
                'required' => true,
            ],
            'path' => [
                'type' => 'text',
                'label' => 'Remote Path',
                'placeholder' => '/var/www/mysite',
                'required' => true,
            ],
            'port' => [
                'type' => 'number',
                'label' => 'SSH Port',
                'default' => 22,
            ],
            'key_path' => [
                'type' => 'text',
                'label' => 'SSH Key Path',
                'placeholder' => '~/.ssh/id_rsa',
                'help' => 'Path to private key file',
            ],
            'options' => [
                'type' => 'text',
                'label' => 'Rsync Options',
                'default' => '-avz --delete',
            ],
        ];
    }
    
    public function testConnection(): DeployResult
    {
        if (!$this->isConfigured()) {
            return DeployResult::failure('Not configured');
        }
        
        $host = $this->config['host'];
        $user = $this->config['user'];
        $port = $this->config['port'] ?? 22;
        $keyPath = $this->config['key_path'] ?? '';
        
        // Build SSH test command
        $cmd = 'ssh -o ConnectTimeout=5 -o BatchMode=yes';
        $cmd .= ' -p ' . intval($port);
        
        if ($keyPath && file_exists($keyPath)) {
            $cmd .= ' -i ' . escapeshellarg($keyPath);
        }
        
        $cmd .= ' ' . escapeshellarg("{$user}@{$host}") . ' echo "OK" 2>&1';
        
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        
        if ($code === 0 && trim($output[0] ?? '') === 'OK') {
            return DeployResult::success("Connected to {$host}");
        }
        
        return DeployResult::failure('SSH connection failed', $output);
    }
}

