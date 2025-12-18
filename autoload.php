<?php
/**
 * Pugo Core - Autoloader
 * 
 * PSR-4 compatible autoloader for Pugo namespace.
 */

spl_autoload_register(function ($class) {
    // Only handle Pugo namespace
    $prefix = 'Pugo\\';
    $baseDir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

