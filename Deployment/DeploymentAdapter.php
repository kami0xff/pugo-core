<?php
/**
 * Pugo Core 3.0 - Deployment Adapter Interface
 */

namespace Pugo\Deployment;

interface DeploymentAdapter
{
    /**
     * Get adapter identifier
     */
    public function getId(): string;
    
    /**
     * Get display name
     */
    public function getName(): string;
    
    /**
     * Get icon name for UI
     */
    public function getIcon(): string;
    
    /**
     * Get adapter description
     */
    public function getDescription(): string;
    
    /**
     * Check if adapter is properly configured
     */
    public function isConfigured(): bool;
    
    /**
     * Validate configuration
     * @return array List of validation errors (empty if valid)
     */
    public function validateConfig(array $config): array;
    
    /**
     * Configure the adapter
     */
    public function configure(array $config): void;
    
    /**
     * Deploy the site
     */
    public function deploy(string $sourceDir, array $options = []): DeployResult;
    
    /**
     * Get current deployment status
     */
    public function getStatus(): ?array;
    
    /**
     * Get settings fields for admin UI
     */
    public function getSettingsFields(): array;
    
    /**
     * Test the connection/configuration
     */
    public function testConnection(): DeployResult;
}

