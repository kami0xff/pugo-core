<?php
/**
 * Pugo Core 3.0 - Deployment Manager
 * 
 * Manages multiple deployment adapters and orchestrates deployments.
 */

namespace Pugo\Deployment;

use Pugo\Config\PugoConfig;

class DeploymentManager
{
    private array $adapters = [];
    private PugoConfig $config;
    private string $hugoRoot;
    
    public function __construct(?PugoConfig $config = null, ?string $hugoRoot = null)
    {
        $this->config = $config ?? PugoConfig::getInstance();
        $this->hugoRoot = $hugoRoot ?? (defined('HUGO_ROOT') ? HUGO_ROOT : getcwd());
        
        $this->registerDefaultAdapters();
    }
    
    /**
     * Register default deployment adapters
     */
    protected function registerDefaultAdapters(): void
    {
        $this->register(new Adapters\GitAdapter());
    }
    
    /**
     * Register a deployment adapter
     */
    public function register(DeploymentAdapter $adapter): self
    {
        $this->adapters[$adapter->getId()] = $adapter;
        
        // Configure adapter from config
        $adapterConfig = $this->config->get('deployment.' . $adapter->getId(), []);
        if (!empty($adapterConfig)) {
            $adapter->configure($adapterConfig);
        }
        
        return $this;
    }
    
    /**
     * Get all registered adapters
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }
    
    /**
     * Get a specific adapter
     */
    public function getAdapter(string $id): ?DeploymentAdapter
    {
        return $this->adapters[$id] ?? null;
    }
    
    /**
     * Get the active/primary deployment adapter
     */
    public function getActiveAdapter(): ?DeploymentAdapter
    {
        $method = $this->config->deploymentMethod();
        return $this->getAdapter($method);
    }
    
    /**
     * Get configured adapters (those that are set up and ready)
     */
    public function getConfiguredAdapters(): array
    {
        return array_filter($this->adapters, fn($a) => $a->isConfigured());
    }
    
    /**
     * Build the Hugo site
     */
    public function build(array $options = []): DeployResult
    {
        $publicDir = $this->hugoRoot . '/public';
        
        // Clean public directory if requested
        if ($options['clean'] ?? false) {
            $this->cleanDirectory($publicDir);
        }
        
        // Build Hugo
        $hugoCmd = 'hugo --minify';
        if (isset($options['baseURL'])) {
            $hugoCmd .= ' --baseURL ' . escapeshellarg($options['baseURL']);
        }
        
        $output = [];
        $code = 0;
        exec('cd ' . escapeshellarg($this->hugoRoot) . ' && ' . $hugoCmd . ' 2>&1', $output, $code);
        
        if ($code !== 0) {
            return DeployResult::failure('Hugo build failed', $output);
        }
        
        // Run Pagefind if enabled
        if ($options['pagefind'] ?? true) {
            $pfOutput = [];
            exec('cd ' . escapeshellarg($this->hugoRoot) . ' && pagefind --site public 2>&1', $pfOutput, $pfCode);
            $output = array_merge($output, ['', '--- Pagefind ---'], $pfOutput);
        }
        
        return DeployResult::success('Build completed', [
            'output' => implode("\n", $output),
            'public_dir' => $publicDir,
        ]);
    }
    
    /**
     * Deploy using the active adapter
     */
    public function deploy(array $options = []): DeployResult
    {
        $adapter = $this->getActiveAdapter();
        
        if (!$adapter) {
            return DeployResult::failure('No deployment adapter configured');
        }
        
        if (!$adapter->isConfigured()) {
            return DeployResult::failure("Deployment adapter '{$adapter->getName()}' is not configured");
        }
        
        // Build first if requested
        if ($options['build'] ?? false) {
            $buildResult = $this->build($options['build_options'] ?? []);
            if ($buildResult->isFailure()) {
                return $buildResult;
            }
        }
        
        $publicDir = $this->hugoRoot . '/public';
        
        return $adapter->deploy($publicDir, $options);
    }
    
    /**
     * Deploy to a specific adapter (for preview deploys)
     */
    public function deployTo(string $adapterId, array $options = []): DeployResult
    {
        $adapter = $this->getAdapter($adapterId);
        
        if (!$adapter) {
            return DeployResult::failure("Unknown deployment adapter: {$adapterId}");
        }
        
        if (!$adapter->isConfigured()) {
            return DeployResult::failure("Adapter '{$adapter->getName()}' is not configured");
        }
        
        $publicDir = $this->hugoRoot . '/public';
        
        return $adapter->deploy($publicDir, $options);
    }
    
    /**
     * Get deployment status from active adapter
     */
    public function getStatus(): ?array
    {
        $adapter = $this->getActiveAdapter();
        return $adapter?->getStatus();
    }
    
    /**
     * Clean a directory
     */
    protected function cleanDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
    }
}

