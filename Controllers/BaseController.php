<?php
/**
 * BaseController - Foundation for all admin controllers
 * 
 * Provides common functionality:
 * - Request/response handling
 * - View rendering
 * - Flash messages
 * - Authentication check
 */

namespace Pugo\Controllers;

abstract class BaseController
{
    protected array $config;
    protected string $currentLang;
    protected array $viewData = [];

    public function __construct()
    {
        // Load config
        $this->config = require dirname(__DIR__, 2) . '/config.php';

        // Set current language
        $this->currentLang = $_GET['lang'] ?? $this->config['default_language'] ?? 'en';

        // Common view data
        $this->viewData = [
            'config' => $this->config,
            'currentLang' => $this->currentLang,
        ];
    }

    /**
     * Require authentication before proceeding
     */
    protected function requireAuth(): void
    {
        require_once dirname(__DIR__) . '/includes/auth.php';
        require_auth();
    }

    /**
     * Render a view with data
     * 
     * @param string $view View path relative to Views/ (e.g., 'articles/edit')
     * @param array $data Data to pass to view
     */
    protected function render(string $view, array $data = []): void
    {
        // Merge with common view data
        $data = array_merge($this->viewData, $data);

        // Extract variables for the view
        extract($data);

        // Build view path
        $viewPath = dirname(__DIR__) . '/Views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        // Include the layout which will include the view
        $contentView = $viewPath;
        require dirname(__DIR__) . '/Views/layouts/admin.php';
    }

    /**
     * Render a view without layout (for partials/AJAX)
     */
    protected function renderPartial(string $view, array $data = []): void
    {
        $data = array_merge($this->viewData, $data);
        extract($data);

        $viewPath = dirname(__DIR__) . '/Views/' . $view . '.php';
        require $viewPath;
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect to another URL
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Set a flash message
     */
    protected function flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[$type] = $message;
    }

    /**
     * Get and clear a flash message
     */
    protected function getFlash(string $type): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $message = $_SESSION[$type] ?? null;
        unset($_SESSION[$type]);
        return $message;
    }

    /**
     * Get POST data with optional default
     */
    protected function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get GET data with optional default
     */
    protected function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Check if request is POST
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrf(): void
    {
        csrf_check();
    }

    /**
     * Get content directory for current language
     */
    protected function getContentDir(?string $lang = null): string
    {
        $lang = $lang ?? $this->currentLang;
        return pugo_get_content_dir_for_lang($lang);
    }
}

