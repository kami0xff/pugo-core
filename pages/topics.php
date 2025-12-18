<?php
/**
 * Pugo Core - Topics Editor Page
 * 
 * Edit quick access topics grouped by sections.
 * 
 * Total: ~45 lines (vs 960+ lines before!)
 */

define('HUGO_ADMIN', true);
define('PUGO_ROOT', dirname(__DIR__, 2));

$config = require PUGO_ROOT . '/config.php';
require PUGO_ROOT . '/core/bootstrap.php';
require PUGO_ROOT . '/core/includes/auth.php';
require_auth();

use Pugo\DataEditors\GroupedListEditor;

$page_title = 'Topics Editor';

// Create the editor with section configuration
$editor = new GroupedListEditor([
    'title' => 'Topics Editor',
    'subtitle' => 'Manage quick access topics for Users, Models, and Studios across all languages',
    'data_file' => 'topics',
    'languages' => $config['languages'] ?? null,
    
    // Define sections for grouping
    'sections' => $config['sections'] ?? [
        'users' => ['name' => 'Users', 'color' => '#3b82f6'],
        'models' => ['name' => 'Models', 'color' => '#ec4899'],
        'studios' => ['name' => 'Studios', 'color' => '#8b5cf6'],
    ],
    
    // Define fields for each topic
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Title', 'required' => true, 'placeholder' => 'Topic title...'],
        'desc' => ['type' => 'text', 'label' => 'Description', 'placeholder' => 'Short description...'],
        'url' => ['type' => 'text', 'label' => 'URL', 'placeholder' => '/section/page/'],
    ],
    
    'item_name' => 'topic',
    'item_name_plural' => 'topics',
]);

// Handle the request
$editor->handleRequest();

// Render
require PUGO_ROOT . '/core/includes/header.php';
$editor->render();
require PUGO_ROOT . '/core/includes/footer.php';

