<?php
/**
 * Hugo Admin - Authentication
 */

session_start();

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

