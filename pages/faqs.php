<?php
/**
 * Pugo Core - FAQ Editor Page
 * 
 * This is an example of how simple it is to create a data editor
 * using the Pugo DataEditor system.
 * 
 * Total: ~35 lines (vs 800+ lines before!)
 */

define('HUGO_ADMIN', true);
define('PUGO_ROOT', dirname(__DIR__, 2));

$config = require PUGO_ROOT . '/config.php';
require PUGO_ROOT . '/core/bootstrap.php';
require PUGO_ROOT . '/core/includes/auth.php';
require_auth();

$page_title = 'FAQ Editor';

// Create the editor using the registered configuration
// OR customize it with overrides
$editor = pugo_create_editor('faqs', [
    'title' => 'FAQ Editor',
    'subtitle' => 'Manage frequently asked questions and their translations',
    'languages' => $config['languages'] ?? null,
]);

if (!$editor) {
    die('FAQ Editor not configured');
}

// Handle the request (load data, process POST)
$editor->handleRequest();

// Render the page
require PUGO_ROOT . '/core/includes/header.php';
$editor->render();
require PUGO_ROOT . '/core/includes/footer.php';

