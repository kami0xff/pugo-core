<?php
/**
 * Hugo Admin - Header Template
 * 
 * Note: CSRF functions (csrf_field, csrf_check) are loaded in auth.php
 */
// Get current page - use router's value if available, otherwise fallback to PHP_SELF
$current_page = $GLOBALS['pugo_current_page'] ?? basename($_SERVER['PHP_SELF'], '.php');

// Handle cases where router detected the page but PHP_SELF is still index
if ($current_page === 'index' && isset($_SERVER['REQUEST_URI'])) {
    $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri_path = trim($uri_path, '/');
    $uri_path = preg_replace('#^admin/?#', '', $uri_path);
    $uri_path = preg_replace('#\.php$#', '', $uri_path);
    if ($uri_path && $uri_path !== 'index') {
        $current_page = $uri_path;
    }
}

// Load centralized Icons (if not already loaded)
if (!class_exists('Pugo\Assets\Icons')) {
    require_once __DIR__ . '/../assets/Icons.php';
}
use Pugo\Assets\Icons;

/**
 * Helper function to render an icon in the header/sidebar
 */
if (!function_exists('pugo_icon')) {
    function pugo_icon(string $name, int $size = 18): string {
        return \Pugo\Assets\Icons::render($name, $size);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= $page_title ?? 'Dashboard' ?> - Hugo Admin</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- EasyMDE for Markdown editing -->
    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    
    <style>
        :root {
            --bg-primary: #0f0f0f;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #252525;
            --bg-hover: #2a2a2a;
            --border-color: #333;
            --text-primary: #f5f5f5;
            --text-secondary: #a0a0a0;
            --text-muted: #666;
            --accent-primary: #e11d48;
            --accent-secondary: #be123c;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-yellow: #f59e0b;
            --accent-purple: #8b5cf6;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            padding: 24px 16px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px 24px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 24px;
        }
        
        .sidebar-logo svg {
            width: 32px;
            height: 32px;
            color: var(--accent-primary);
        }
        
        .sidebar-logo span {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary);
        }
        
        .sidebar-logo small {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 400;
        }
        
        .nav-section {
            margin-bottom: 24px;
        }
        
        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            padding: 0 12px;
            margin-bottom: 8px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s ease;
        }
        
        .nav-item:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .nav-item.active {
            background: var(--accent-primary);
            color: white;
        }
        
        .nav-item svg {
            width: 18px;
            height: 18px;
            opacity: 0.7;
        }
        
        .nav-item.active svg {
            opacity: 1;
        }
        
        .nav-item .count {
            margin-left: auto;
            background: var(--bg-tertiary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: var(--text-muted);
        }
        
        .nav-item.active .count {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .sidebar-footer {
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 32px 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .main-content > * {
            width: 100%;
            max-width: 1200px;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 4px;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--accent-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--accent-secondary);
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn svg {
            width: 16px;
            height: 16px;
        }
        
        /* Cards */
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
        }
        
        /* Grid */
        .grid {
            display: grid;
            gap: 20px;
        }
        
        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }
        
        @media (max-width: 1200px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 1400px) {
            .main-content {
                padding: 32px;
            }
        }
        
        @media (max-width: 768px) {
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .main-content { 
                margin-left: 0; 
                padding: 20px;
            }
        }
        
        /* Stats Cards */
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        /* Language Tabs */
        .lang-tabs {
            display: flex;
            gap: 4px;
            background: var(--bg-tertiary);
            padding: 4px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
        }
        
        .lang-tab {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        
        .lang-tab:hover {
            color: var(--text-primary);
            background: var(--bg-hover);
        }
        
        .lang-tab.active {
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .lang-tab .flag {
            font-size: 16px;
        }
        
        /* Article List */
        .article-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .article-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            text-decoration: none;
            transition: all 0.15s ease;
        }
        
        .article-item:hover {
            border-color: var(--accent-primary);
            background: var(--bg-tertiary);
        }
        
        .article-section-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .article-title {
            flex: 1;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .article-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--text-muted);
            font-size: 13px;
        }
        
        .article-langs {
            display: flex;
            gap: 4px;
        }
        
        .article-langs span {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            background: var(--bg-tertiary);
        }
        
        .article-langs span.missing {
            opacity: 0.3;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.15s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
        }
        
        .form-input::placeholder {
            color: var(--text-muted);
        }
        
        textarea.form-input {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        /* Tags Input */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            min-height: 44px;
        }
        
        .tag {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: var(--accent-primary);
            color: white;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .tag button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
            opacity: 0.7;
        }
        
        .tag button:hover {
            opacity: 1;
        }
        
        .tags-input {
            border: none;
            background: transparent;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            flex: 1;
            min-width: 100px;
        }
        
        /* Media Browser */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
        }
        
        .media-item {
            aspect-ratio: 1;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            overflow: hidden;
            cursor: pointer;
            position: relative;
            transition: all 0.15s ease;
        }
        
        .media-item:hover {
            border-color: var(--accent-primary);
        }
        
        .media-item.selected {
            border-color: var(--accent-primary);
            border-width: 2px;
        }
        
        .media-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .media-item-name {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 8px;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            font-size: 11px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .media-folder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
        }
        
        .media-folder svg {
            width: 48px;
            height: 48px;
            opacity: 0.5;
        }
        
        .media-folder:hover {
            color: var(--text-primary);
        }
        
        .media-folder:hover svg {
            opacity: 0.8;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: var(--text-secondary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            color: var(--text-primary);
        }
        
        .breadcrumb svg {
            width: 16px;
            height: 16px;
            opacity: 0.5;
        }
        
        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }
        
        .toast.success {
            border-color: var(--accent-green);
        }
        
        .toast.error {
            border-color: var(--accent-primary);
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            opacity: 0.3;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        /* Loading */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid var(--border-color);
            border-top-color: var(--accent-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* EasyMDE Custom Theme */
        .EasyMDEContainer {
            background: var(--bg-tertiary);
            border-radius: var(--radius-sm);
        }
        
        .EasyMDEContainer .CodeMirror {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-family: 'JetBrains Mono', monospace;
        }
        
        .EasyMDEContainer .editor-toolbar {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-bottom: none;
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
        }
        
        .EasyMDEContainer .editor-toolbar button {
            color: var(--text-secondary) !important;
        }
        
        .EasyMDEContainer .editor-toolbar button:hover {
            background: var(--bg-hover);
        }
        
        .EasyMDEContainer .editor-toolbar button.active {
            background: var(--bg-tertiary);
        }
        
        .EasyMDEContainer .CodeMirror-cursor {
            border-left-color: var(--accent-primary);
        }
        
        .EasyMDEContainer .editor-preview {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .EasyMDEContainer .editor-preview h1,
        .EasyMDEContainer .editor-preview h2,
        .EasyMDEContainer .editor-preview h3 {
            color: var(--text-primary);
        }
        
        .EasyMDEContainer .CodeMirror-selected {
            background: rgba(225, 29, 72, 0.2) !important;
        }
        
        /* Translation Status */
        .translation-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
        }
        
        .translation-item {
            padding: 12px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            text-align: center;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        
        .translation-item:hover {
            border-color: var(--accent-primary);
        }
        
        .translation-item.exists {
            border-color: var(--accent-green);
        }
        
        .translation-item.missing {
            opacity: 0.6;
        }
        
        .translation-item .flag {
            font-size: 24px;
            display: block;
            margin-bottom: 4px;
        }
        
        .translation-item .lang-name {
            font-size: 11px;
            color: var(--text-secondary);
        }
        
        .translation-item .status {
            font-size: 10px;
            margin-top: 4px;
        }
        
        .translation-item.exists .status {
            color: var(--accent-green);
        }
        
        .translation-item.missing .status {
            color: var(--text-muted);
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            /* Override .main-content > * constraints */
            max-width: none !important;
            width: 100% !important;
            margin-left: 0 !important;
            padding: 0 !important;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: transform 0.2s ease;
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
        }
        
        .modal-close:hover {
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <?= pugo_icon('layers', 32) ?>
                <div>
                    <span>Hugo Admin</span>
                    <small><?= htmlspecialchars($config['site_name'] ?? 'Pugo CMS') ?></small>
                </div>
            </div>
            
            <nav class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="index.php" class="nav-item <?= $current_page === 'index' || $current_page === 'dashboard' ? 'active' : '' ?>">
                    <?= pugo_icon('grid') ?>
                    Dashboard
                </a>
                <a href="articles.php" class="nav-item <?= in_array($current_page, ['articles', 'edit', 'new']) ? 'active' : '' ?>">
                    <?= pugo_icon('layers') ?>
                    Content
                </a>
                <a href="pages.php" class="nav-item <?= in_array($current_page, ['pages', 'page-edit']) ? 'active' : '' ?>">
                    <?= pugo_icon('layout') ?>
                    Pages
                </a>
                <a href="media.php" class="nav-item <?= $current_page === 'media' ? 'active' : '' ?>">
                    <?= pugo_icon('image') ?>
                    Media
                </a>
                <a href="data.php" class="nav-item <?= $current_page === 'data' ? 'active' : '' ?>">
                    <?= pugo_icon('database') ?>
                    Data Files
                </a>
                <a href="components.php" class="nav-item <?= $current_page === 'components' ? 'active' : '' ?>">
                    <?= pugo_icon('box') ?>
                    Site Components
                </a>
                <a href="templates.php" class="nav-item <?= in_array($current_page, ['templates', 'template-edit']) ? 'active' : '' ?>">
                    <?= pugo_icon('code') ?>
                    Templates
                </a>
            </nav>
            
            <nav class="nav-section">
                <div class="nav-section-title">Tools</div>
                <a href="taxonomy.php" class="nav-item <?= $current_page === 'taxonomy' ? 'active' : '' ?>">
                    <?= pugo_icon('tags') ?>
                    Taxonomy
                </a>
                <a href="scanner.php" class="nav-item <?= $current_page === 'scanner' ? 'active' : '' ?>">
                    <?= pugo_icon('search') ?>
                    Scanner
                </a>
                <a href="help.php" class="nav-item <?= $current_page === 'help' ? 'active' : '' ?>">
                    <?= pugo_icon('help-circle') ?>
                    Help & Docs
                </a>
            </nav>
            
            <nav class="nav-section">
                <div class="nav-section-title">Sections</div>
                <?php 
                $nav_sections = get_sections_with_counts($_GET['lang'] ?? 'en');
                foreach ($nav_sections as $nav_key => $nav_sect): 
                ?>
                <a href="articles.php?section=<?= $nav_key ?>" class="nav-item">
                    <span style="width: 8px; height: 8px; background: <?= $nav_sect['color'] ?>; border-radius: 50%;"></span>
                    <?= $nav_sect['name'] ?>
                    <span class="count"><?= $nav_sect['count'] ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
            
            <div class="sidebar-footer">
                <!-- Quick Deploy Button -->
                <button type="button" onclick="quickDeploy()" class="nav-item" style="width: 100%; text-align: left; border: 1px solid var(--accent-green); background: rgba(16, 185, 129, 0.1); cursor: pointer; font-family: inherit;">
                    <?= pugo_icon('upload-cloud') ?>
                    <span>Build & Deploy</span>
                </button>
                
                <a href="settings.php" class="nav-item" style="margin-top: 8px;">
                    <?= pugo_icon('settings') ?>
                    Settings
                </a>
                <a href="logout.php" class="nav-item">
                    <?= pugo_icon('log-out') ?>
                    Logout
                </a>
            </div>
        </aside>
        
        <!-- Deploy Modal -->
        <div id="deployModal" class="modal-overlay" onclick="if(event.target === this) closeDeployModal()">
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h3 class="modal-title"><?= pugo_icon('upload-cloud', 20) ?> Build & Deploy</h3>
                    <button type="button" class="modal-close" onclick="closeDeployModal()">
                        <?= pugo_icon('x', 20) ?>
                    </button>
                </div>
                
                <div id="deployStatus" style="margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--bg-tertiary); border-radius: 8px;">
                        <div id="deployStatusIcon"><?= pugo_icon('git-branch', 24) ?></div>
                        <div>
                            <div id="deployStatusText" style="font-weight: 600;">Ready to deploy</div>
                            <div id="deployRemoteUrl" style="font-size: 12px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">Loading...</div>
                        </div>
                    </div>
                </div>
                
                <form id="deployForm" onsubmit="executeDeploy(event)">
                    <div class="form-group">
                        <label class="form-label">Commit Message</label>
                        <input type="text" id="deployMessage" class="form-input" 
                               placeholder="Deploy: Update content" 
                               value="Deploy: <?= date('Y-m-d H:i') ?>">
                    </div>
                    
                    <div id="deployOutput" style="display: none; margin-bottom: 16px;">
                        <pre style="background: var(--bg-tertiary); padding: 12px; border-radius: 8px; font-size: 11px; font-family: 'JetBrains Mono', monospace; max-height: 200px; overflow-y: auto; white-space: pre-wrap;"></pre>
                    </div>
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" id="deployBtn" class="btn btn-primary" style="flex: 1;">
                            <?= pugo_icon('upload-cloud', 16) ?>
                            <span>Build & Deploy</span>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeployModal()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function quickDeploy() {
            document.getElementById('deployModal').classList.add('active');
            checkDeployStatus();
        }
        
        function closeDeployModal() {
            document.getElementById('deployModal').classList.remove('active');
        }
        
        function checkDeployStatus() {
            fetch('api.php?action=deploy_status')
                .then(r => r.json())
                .then(data => {
                    const urlEl = document.getElementById('deployRemoteUrl');
                    const textEl = document.getElementById('deployStatusText');
                    
                    if (data.configured) {
                        urlEl.textContent = data.remote_url || 'Remote configured';
                        textEl.textContent = data.last_commit ? 
                            'Last: ' + data.last_commit.message + ' (' + data.last_commit.date + ')' : 
                            'Ready to deploy';
                    } else {
                        urlEl.textContent = 'Not configured';
                        textEl.textContent = 'Go to Settings to configure';
                        textEl.style.color = 'var(--accent-yellow)';
                    }
                })
                .catch(() => {
                    document.getElementById('deployRemoteUrl').textContent = 'Unable to check status';
                });
        }
        
        function executeDeploy(e) {
            e.preventDefault();
            
            const btn = document.getElementById('deployBtn');
            const output = document.getElementById('deployOutput');
            const pre = output.querySelector('pre');
            const message = document.getElementById('deployMessage').value;
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></span> Deploying...';
            output.style.display = 'block';
            pre.textContent = 'Building Hugo...\n';
            
            fetch('api.php?action=deploy', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(r => r.json())
            .then(data => {
                pre.textContent = data.output || 'Deployed!';
                
                if (data.success) {
                    btn.innerHTML = '<?= pugo_icon('check', 16) ?> Deployed!';
                    btn.style.background = 'var(--accent-green)';
                    setTimeout(() => {
                        closeDeployModal();
                        btn.disabled = false;
                        btn.innerHTML = '<?= pugo_icon('upload-cloud', 16) ?> <span>Build & Deploy</span>';
                        btn.style.background = '';
                    }, 2000);
                } else {
                    btn.innerHTML = '<?= pugo_icon('x', 16) ?> Failed';
                    btn.style.background = 'var(--accent-primary)';
                    btn.disabled = false;
                    setTimeout(() => {
                        btn.innerHTML = '<?= pugo_icon('upload-cloud', 16) ?> <span>Build & Deploy</span>';
                        btn.style.background = '';
                    }, 3000);
                }
            })
            .catch(err => {
                pre.textContent = 'Error: ' + err.message;
                btn.disabled = false;
                btn.innerHTML = '<?= pugo_icon('upload-cloud', 16) ?> <span>Build & Deploy</span>';
            });
        }
        </script>
        
        <!-- Main Content -->
        <main class="main-content">

