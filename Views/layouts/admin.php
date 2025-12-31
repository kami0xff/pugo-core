<?php
/**
 * Admin Layout
 * 
 * This layout wraps views with the standard header/footer.
 * 
 * Variables available:
 * - $contentView: Path to the content view to include
 * - $pageTitle: Page title for the header
 * - All other extracted data from the controller
 */

// Set page_title for header (it expects this variable name)
$page_title = $pageTitle ?? 'Admin';

// Include the header (sidebar, styles, etc.)
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<?php 
// Include the actual content view
require $contentView; 
?>

<?php
// Include the footer (scripts, closing tags)
require_once dirname(__DIR__, 2) . '/includes/footer.php';

