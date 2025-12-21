<?php
/**
 * Hugo Admin - Authentication & Security
 */

session_start();

// Load CSRF protection
require_once __DIR__ . '/../Security/CSRF.php';
use Pugo\Security\CSRF;

// Load Validator
require_once __DIR__ . '/../Validation/Validator.php';
use Pugo\Validation\Validator;

/**
 * Get CSRF hidden field for forms
 */
function csrf_field(): string {
    return CSRF::field();
}

/**
 * Get CSRF token value
 */
function csrf_token(): string {
    return CSRF::getToken();
}

/**
 * Validate CSRF token - returns true if valid, dies with error if invalid
 * Call this at the START of POST handlers
 */
function csrf_check(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    if (!CSRF::validate()) {
        http_response_code(403);
        die('Security token invalid. Please refresh the page and try again.');
    }
    return true;
}

/**
 * Create a new Validator instance
 */
function validate(array $data, array $rules): Validator {
    return new Validator($data, $rules);
}

/**
 * Check if user is authenticated
 */
function is_authenticated() {
    global $config;
    
    if (!$config['auth']['enabled']) {
        return true;
    }
    
    if (isset($_SESSION['hugo_admin_auth']) && 
        isset($_SESSION['hugo_admin_auth_time']) &&
        (time() - $_SESSION['hugo_admin_auth_time']) < $config['auth']['session_lifetime']) {
        return true;
    }
    
    return false;
}

/**
 * Authenticate user
 */
function authenticate($username, $password) {
    global $config;
    
    if ($username === $config['auth']['username'] && 
        password_verify($password, $config['auth']['password_hash'])) {
        $_SESSION['hugo_admin_auth'] = true;
        $_SESSION['hugo_admin_auth_time'] = time();
        $_SESSION['hugo_admin_user'] = $username;
        return true;
    }
    
    return false;
}

/**
 * Logout user
 */
function logout() {
    unset($_SESSION['hugo_admin_auth']);
    unset($_SESSION['hugo_admin_auth_time']);
    unset($_SESSION['hugo_admin_user']);
    session_destroy();
}

/**
 * Require authentication (redirect if not authenticated)
 */
function require_auth() {
    if (!is_authenticated()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current user
 */
function get_current_user_name() {
    return $_SESSION['hugo_admin_user'] ?? 'Guest';
}

