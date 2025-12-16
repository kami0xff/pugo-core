<?php
/**
 * Hugo Admin - Logout
 */
define('HUGO_ADMIN', true);

$config = require dirname(__DIR__, 2) . '/config.php';
require __DIR__ . '/../includes/auth.php';

logout();
header('Location: login.php');
exit;

