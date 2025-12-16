<?php
/**
 * Pugo - Action Result
 * 
 * Immutable result object returned by all actions.
 * Provides a consistent interface for success/failure responses.
 */

namespace Pugo\Actions;

final readonly class ActionResult
{
    private function __construct(
        public bool $success,
        public string $message,
        public mixed $data = null,
        public ?string $error = null
    ) {}

    /**
     * Create a successful result
     */
    public static function success(string $message = 'Operation completed successfully', mixed $data = null): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            error: null
        );
    }

    /**
     * Create a failure result
     */
    public static function failure(string $error, mixed $data = null): self
    {
        return new self(
            success: false,
            message: $error,
            data: $data,
            error: $error
        );
    }

    /**
     * Convert to array (for JSON responses)
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}

