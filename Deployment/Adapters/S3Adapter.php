<?php
/**
 * Pugo Core 3.0 - AWS S3 Deployment Adapter
 */

namespace Pugo\Deployment\Adapters;

use Pugo\Deployment\DeploymentAdapter;
use Pugo\Deployment\DeployResult;

class S3Adapter implements DeploymentAdapter
{
    protected array $config = [];
    
    public function getId(): string
    {
        return 's3';
    }
    
    public function getName(): string
    {
        return 'AWS S3';
    }
    
    public function getIcon(): string
    {
        return 'cloud';
    }
    
    public function getDescription(): string
    {
        return 'Deploy to AWS S3 bucket (optionally with CloudFront invalidation)';
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->config['bucket']) && 
               !empty($this->config['region']) &&
               (!empty($this->config['access_key']) || $this->hasAwsCli());
    }
    
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (empty($config['bucket'])) {
            $errors[] = 'S3 bucket name is required';
        }
        
        if (empty($config['region'])) {
            $errors[] = 'AWS region is required';
        }
        
        return $errors;
    }
    
    public function configure(array $config): void
    {
        $this->config = array_merge([
            'bucket' => '',
            'region' => 'us-east-1',
            'access_key' => '',
            'secret_key' => '',
            'cloudfront_id' => '',
            'cache_control' => 'max-age=31536000',
            'delete_removed' => true,
        ], $config);
    }
    
    public function deploy(string $sourceDir, array $options = []): DeployResult
    {
        // Check for AWS CLI
        if (!$this->hasAwsCli()) {
            return DeployResult::failure('AWS CLI not installed');
        }
        
        $bucket = $this->config['bucket'];
        $region = $this->config['region'];
        $deleteRemoved = $this->config['delete_removed'] ?? true;
        
        // Set AWS credentials if provided
        $env = '';
        if (!empty($this->config['access_key'])) {
            $env = 'AWS_ACCESS_KEY_ID=' . escapeshellarg($this->config['access_key']) . ' ';
            $env .= 'AWS_SECRET_ACCESS_KEY=' . escapeshellarg($this->config['secret_key']) . ' ';
        }
        
        // Build sync command
        $cmd = $env . 'aws s3 sync';
        $cmd .= ' ' . escapeshellarg($sourceDir);
        $cmd .= ' s3://' . escapeshellarg($bucket);
        $cmd .= ' --region ' . escapeshellarg($region);
        
        if ($deleteRemoved) {
            $cmd .= ' --delete';
        }
        
        // Execute
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        
        if ($code !== 0) {
            return DeployResult::failure('S3 sync failed', $output);
        }
        
        // Invalidate CloudFront if configured
        $cfInvalidation = null;
        if (!empty($this->config['cloudfront_id'])) {
            $cfInvalidation = $this->invalidateCloudFront();
        }
        
        return DeployResult::success('Deployed to S3', [
            'bucket' => $bucket,
            'region' => $region,
            'cloudfront_invalidation' => $cfInvalidation,
            'output' => implode("\n", $output),
        ]);
    }
    
    protected function invalidateCloudFront(): ?array
    {
        $distributionId = $this->config['cloudfront_id'];
        
        $env = '';
        if (!empty($this->config['access_key'])) {
            $env = 'AWS_ACCESS_KEY_ID=' . escapeshellarg($this->config['access_key']) . ' ';
            $env .= 'AWS_SECRET_ACCESS_KEY=' . escapeshellarg($this->config['secret_key']) . ' ';
        }
        
        $cmd = $env . 'aws cloudfront create-invalidation';
        $cmd .= ' --distribution-id ' . escapeshellarg($distributionId);
        $cmd .= ' --paths "/*"';
        $cmd .= ' --region ' . escapeshellarg($this->config['region']);
        
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        
        if ($code === 0) {
            return ['status' => 'created', 'distribution' => $distributionId];
        }
        
        return ['status' => 'failed', 'error' => implode("\n", $output)];
    }
    
    public function getStatus(): ?array
    {
        return [
            'configured' => $this->isConfigured(),
            'bucket' => $this->config['bucket'] ?? null,
            'region' => $this->config['region'] ?? null,
            'cloudfront' => !empty($this->config['cloudfront_id']),
        ];
    }
    
    public function getSettingsFields(): array
    {
        return [
            'bucket' => [
                'type' => 'text',
                'label' => 'S3 Bucket Name',
                'required' => true,
            ],
            'region' => [
                'type' => 'select',
                'label' => 'AWS Region',
                'options' => [
                    'us-east-1' => 'US East (N. Virginia)',
                    'us-east-2' => 'US East (Ohio)',
                    'us-west-1' => 'US West (N. California)',
                    'us-west-2' => 'US West (Oregon)',
                    'eu-west-1' => 'EU (Ireland)',
                    'eu-west-2' => 'EU (London)',
                    'eu-west-3' => 'EU (Paris)',
                    'eu-central-1' => 'EU (Frankfurt)',
                    'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                    'ap-southeast-1' => 'Asia Pacific (Singapore)',
                ],
                'default' => 'us-east-1',
            ],
            'access_key' => [
                'type' => 'text',
                'label' => 'AWS Access Key ID',
                'help' => 'Leave empty to use AWS CLI credentials or IAM role',
            ],
            'secret_key' => [
                'type' => 'password',
                'label' => 'AWS Secret Access Key',
            ],
            'cloudfront_id' => [
                'type' => 'text',
                'label' => 'CloudFront Distribution ID (optional)',
                'help' => 'For cache invalidation after deploy',
            ],
            'delete_removed' => [
                'type' => 'checkbox',
                'label' => 'Delete removed files from S3',
                'default' => true,
            ],
        ];
    }
    
    public function testConnection(): DeployResult
    {
        if (!$this->hasAwsCli()) {
            return DeployResult::failure('AWS CLI not installed');
        }
        
        $bucket = $this->config['bucket'];
        $region = $this->config['region'];
        
        $env = '';
        if (!empty($this->config['access_key'])) {
            $env = 'AWS_ACCESS_KEY_ID=' . escapeshellarg($this->config['access_key']) . ' ';
            $env .= 'AWS_SECRET_ACCESS_KEY=' . escapeshellarg($this->config['secret_key']) . ' ';
        }
        
        $cmd = $env . 'aws s3 ls s3://' . escapeshellarg($bucket);
        $cmd .= ' --region ' . escapeshellarg($region) . ' 2>&1';
        
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        
        if ($code === 0) {
            return DeployResult::success("Connected to bucket: {$bucket}");
        }
        
        return DeployResult::failure('Cannot access S3 bucket', $output);
    }
    
    protected function hasAwsCli(): bool
    {
        exec('which aws 2>/dev/null', $output, $code);
        return $code === 0;
    }
}

