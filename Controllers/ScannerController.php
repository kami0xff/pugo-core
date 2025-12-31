<?php
/**
 * ScannerController - Project content scanner
 */

namespace Pugo\Controllers;

class ScannerController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        require_once dirname(__DIR__) . '/includes/functions.php';
    }

    /**
     * Scanner results page
     */
    public function index(): void
    {
        $this->requireAuth();

        // Determine default language for scanning
        $defaultLang = $this->config['default_language'] ?? 'en';
        $defaultContentDir = get_content_dir_for_lang($defaultLang);

        // Discover sections dynamically
        $discoveredSections = array_keys(discover_sections());

        $rules = [
            'required_frontmatter' => ['title', 'description'],
            'recommended_frontmatter' => ['date', 'author', 'translationKey'],
            'valid_sections' => $discoveredSections,
            'image_base_path' => '/static/images/articles',
            'max_description_length' => 160,
            'max_image_size' => 500 * 1024,
        ];

        // Run scans
        $issues = [];
        $warnings = [];
        $info = [];
        $stats = [
            'articles_scanned' => 0,
            'images_scanned' => 0,
            'data_files_scanned' => 0,
        ];

        // Pass data to legacy scan functions by making them global
        $GLOBALS['issues'] = &$issues;
        $GLOBALS['warnings'] = &$warnings;
        $GLOBALS['info'] = &$info;
        $GLOBALS['stats'] = &$stats;
        $GLOBALS['RULES'] = $rules;
        $GLOBALS['default_content_dir'] = $defaultContentDir;
        $GLOBALS['default_lang'] = $defaultLang;
        $GLOBALS['config'] = $this->config;

        // Include scanner functions if needed
        // The scan functions are defined in scanner.php - we'll render the results view
        // which has embedded scan results

        $this->render('scanner/index', [
            'pageTitle' => 'Project Scanner',
            'rules' => $rules,
            'defaultLang' => $defaultLang,
            'defaultContentDir' => $defaultContentDir,
            'discoveredSections' => $discoveredSections,
        ]);
    }
}

