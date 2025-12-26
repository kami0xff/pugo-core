<?php
/**
 * Pugo Configuration Loader
 * 
 * This file just loads your pugo.yaml configuration.
 * ALL settings should be in pugo.yaml - not here!
 * 
 * Copy this to your project's admin/config.php
 */

// Prevent direct access
if (!defined('HUGO_ADMIN')) {
    define('HUGO_ADMIN', true);
}

// Load the Pugo config loader
require_once __DIR__ . '/core/Config/PugoConfig.php';

// Load and return configuration from pugo.yaml
// The pugo.yaml file should be in your project root (same level as content/, static/, etc.)
return \Pugo\Config\PugoConfig::load();

