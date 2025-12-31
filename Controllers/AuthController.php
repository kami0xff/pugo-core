<?php
/**
 * AuthController - Authentication management
 * 
 * Handles login/logout. Simple operations stay in controller.
 */

namespace Pugo\Controllers;

class AuthController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        require_once dirname(__DIR__) . '/includes/auth.php';
    }

    /**
     * Show login form or process login
     */
    public function login(): void
    {
        // Redirect if already logged in
        if (is_authenticated()) {
            $this->redirect('index.php');
        }

        if ($this->isPost()) {
            $this->handleLogin();
            return;
        }

        // Show login form (no layout, standalone page)
        $this->renderWithoutLayout('auth/login', [
            'pageTitle' => 'Login',
            'error' => $this->getFlash('error'),
            'siteName' => $this->config['site_name'] ?? 'Pugo Admin'
        ]);
    }

    /**
     * Process login attempt
     */
    private function handleLogin(): void
    {
        $username = $this->post('username', '');
        $password = $this->post('password', '');

        if (!$username || !$password) {
            $this->flash('error', 'Username and password required');
            $this->redirect('login.php');
        }

        // Verify credentials
        $configUser = $this->config['admin_user'] ?? 'admin';
        $configHash = $this->config['admin_password_hash'] ?? '';

        if ($username === $configUser && password_verify($password, $configHash)) {
            // Start session and set authenticated
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['authenticated'] = true;
            $_SESSION['user'] = $username;
            $_SESSION['login_time'] = time();

            // Regenerate session ID for security
            session_regenerate_id(true);

            $this->redirect('index.php');
        } else {
            // Invalid credentials
            $this->flash('error', 'Invalid username or password');
            $this->redirect('login.php');
        }
    }

    /**
     * Logout and destroy session
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear session data
        $_SESSION = [];

        // Destroy the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy session
        session_destroy();

        $this->redirect('login.php');
    }

    /**
     * Render without admin layout (for login page)
     */
    private function renderWithoutLayout(string $view, array $data = []): void
    {
        $data = array_merge($this->viewData, $data);
        extract($data);

        $viewPath = dirname(__DIR__) . '/Views/' . $view . '.php';
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            // Fallback to legacy login page
            require dirname(__DIR__) . '/pages/login.php';
        }
    }
}

