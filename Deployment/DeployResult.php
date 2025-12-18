<?php
/**
 * Pugo Core 3.0 - Deployment Result
 */

namespace Pugo\Deployment;

class DeployResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILURE = 'failure';
    
    private function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly array $data = [],
        public readonly ?string $error = null
    ) {}
    
    public static function success(string $message, array $data = []): self
    {
        return new self(self::STATUS_SUCCESS, $message, $data);
    }
    
    public static function pending(string $message, array $data = []): self
    {
        return new self(self::STATUS_PENDING, $message, $data);
    }
    
    public static function failure(string $message, ?array $output = null): self
    {
        return new self(
            self::STATUS_FAILURE,
            $message,
            [],
            is_array($output) ? implode("\n", $output) : $output
        );
    }
    
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
    
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    
    public function isFailure(): bool
    {
        return $this->status === self::STATUS_FAILURE;
    }
    
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}

