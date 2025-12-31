<?php
/**
 * AuthController - Authentication handling
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
     * Login page
     */
    public function login(): void
    {
        // Already logged in?
        if (is_authenticated()) {
            $this->redirect('index.php');
            return;
        }

        $error = '';

        if ($this->isPost()) {
            $username = $this->post('username', '');
            $password = $this->post('password', '');

            if (authenticate($username, $password)) {
                $this->redirect('index.php');
                return;
            } else {
                $error = 'Invalid username or password';
            }
        }

        // Render login page (custom layout without header/footer)
        $this->renderLogin($error);
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        logout();
        $this->redirect('login.php');
    }

    /**
     * Render login page with custom layout
     */
    private function renderLogin(string $error = ''): void
    {
        $siteName = $this->config['site_name'] ?? 'Pugo CMS';
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($siteName) ?></title>
    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #141414;
            --bg-tertiary: #1a1a1a;
            --text-primary: #fafafa;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --border-color: #27272a;
            --accent-primary: #e11d48;
            --radius-md: 12px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        
        .login-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .login-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent-primary), #f43f5e);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        
        .login-logo svg {
            width: 32px;
            height: 32px;
            color: white;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .login-subtitle {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--accent-primary);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #be123c;
        }
        
        .error {
            background: rgba(225, 29, 72, 0.1);
            border: 1px solid var(--accent-primary);
            color: var(--accent-primary);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <h1 class="login-title">Pugo Admin</h1>
                <p class="login-subtitle"><?= htmlspecialchars($siteName) ?></p>
            </div>
            
            <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                
                <button type="submit" class="btn">Sign In</button>
            </form>
            
            <p class="login-footer">Secure admin access for content management</p>
        </div>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
