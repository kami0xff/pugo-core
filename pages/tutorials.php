<?php
/**
 * Pugo Core - Tutorials Editor Page
 * 
 * Edit video tutorials with categories.
 * 
 * Total: ~60 lines (vs 1244 lines before!)
 */

define('HUGO_ADMIN', true);
define('PUGO_ROOT', dirname(__DIR__, 2));

$config = require PUGO_ROOT . '/config.php';
require PUGO_ROOT . '/core/bootstrap.php';
require PUGO_ROOT . '/core/includes/auth.php';
require_auth();

use Pugo\DataEditors\SimpleListEditor;

$page_title = 'Tutorials Editor';

// Tutorial categories
$categories = [
    'getting-started' => ['label' => 'Getting Started', 'icon' => 'user', 'color' => '#1e3a5f'],
    'streaming' => ['label' => 'Streaming', 'icon' => 'video', 'color' => '#5c1a1a'],
    'payments' => ['label' => 'Payments', 'icon' => 'coins', 'color' => '#5c5c1a'],
    'security' => ['label' => 'Security', 'icon' => 'shield', 'color' => '#1a1a5c'],
];

// Build category options for select field
$categoryOptions = [];
foreach ($categories as $key => $cat) {
    $categoryOptions[$key] = $cat['label'];
}

// Create the editor
$editor = new SimpleListEditor([
    'title' => 'Tutorials Editor',
    'subtitle' => 'Manage video tutorials with categories and translations',
    'data_file' => 'all_tutorials',
    'data_format' => 'multi_file',
    'languages' => $config['languages'] ?? null,
    
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Title', 'required' => true, 'placeholder' => 'Tutorial title...'],
        'description' => ['type' => 'textarea', 'label' => 'Description', 'placeholder' => 'Brief description...'],
        'duration' => ['type' => 'text', 'label' => 'Duration', 'placeholder' => '3:45'],
        'video' => ['type' => 'url', 'label' => 'Video URL', 'placeholder' => 'https://...'],
        'category' => ['type' => 'select', 'label' => 'Category', 'options' => $categoryOptions],
        'color' => ['type' => 'text', 'label' => 'Background Gradient', 'placeholder' => 'linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%)'],
        'featured' => ['type' => 'checkbox', 'label' => 'Featured', 'inline_label' => 'Show on homepage (featured)'],
    ],
    
    'item_name' => 'tutorial',
    'item_name_plural' => 'tutorials',
    'preview_type' => 'card',
]);

// Handle the request
$editor->handleRequest();

// Render
require PUGO_ROOT . '/core/includes/header.php';
$editor->render();
require PUGO_ROOT . '/core/includes/footer.php';

