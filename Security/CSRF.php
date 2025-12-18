<?php
/**
 * Pugo Core 3.0 - CSRF Protection
 * 
 * Token-based Cross-Site Request Forgery protection.
 */

namespace Pugo\Security;

class CSRF
{
    private const TOKEN_NAME = 'pugo_csrf_token';
    private const TOKEN_LENGTH = 32;
    
    /**
     * Generate a new CSRF token
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = $token;
        $_SESSION[self::TOKEN_NAME . '_time'] = time();
        
        return $token;
    }
    
    /**
     * Get the current token or generate new one
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists and is not expired (1 hour)
        if (isset($_SESSION[self::TOKEN_NAME]) && isset($_SESSION[self::TOKEN_NAME . '_time'])) {
            $age = time() - $_SESSION[self::TOKEN_NAME . '_time'];
            if ($age < 3600) {
                return $_SESSION[self::TOKEN_NAME];
            }
        }
        
        return self::generateToken();
    }
    
    /**
     * Validate a CSRF token
     */
    public static function validateToken(?string $token): bool
    {
        if ($token === null || empty($token)) {
            return false;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        
        // Timing-safe comparison
        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }
    
    /**
     * Validate token from POST request
     */
    public static function validate(): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return self::validateToken($token);
    }
    
    /**
     * Require valid CSRF token or die
     */
    public static function require(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !self::validate()) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
    
    /**
     * Get hidden input field HTML
     */
    public static function field(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Get meta tag for JavaScript usage
     */
    public static function meta(): string
    {
        $token = self::getToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
}

/**
 * Global helpers
 */
function csrf_token(): string
{
    return CSRF::getToken();
}

function csrf_field(): string
{
    return CSRF::field();
}

function csrf_validate(): bool
{
    return CSRF::validate();
}

