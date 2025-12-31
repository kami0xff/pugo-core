<?php
/**
 * DataController - Hugo data files management
 * 
 * Manages YAML/JSON/TOML data files in Hugo's data/ directory.
 * Simple file operations stay in controller.
 */

namespace Pugo\Controllers;

class DataController extends BaseController
{
    private string $dataDir;

    public function __construct()
    {
        parent::__construct();
        $this->dataDir = HUGO_ROOT . '/data';
        require_once dirname(__DIR__) . '/includes/functions.php';
    }

    /**
     * List data files
     */
    public function index(): void
    {
        $this->requireAuth();

        $dataFiles = $this->listDataFiles();

        $this->render('data/index', [
            'pageTitle' => 'Data Files',
            'dataFiles' => $dataFiles,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    /**
     * Edit a data file
     */
    public function edit(): void
    {
        $this->requireAuth();

        $file = $this->get('file', '');

        if (!$file) {
            $this->redirect('data.php');
        }

        $fullPath = $this->dataDir . '/' . $file;

        if (!file_exists($fullPath)) {
            $this->flash('error', 'Data file not found');
            $this->redirect('data.php');
        }

        if ($this->isPost()) {
            $this->handleUpdate($fullPath, $file);
            return;
        }

        $content = file_get_contents($fullPath);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        // Parse content based on type
        $parsedData = $this->parseDataFile($content, $extension);

        $this->render('data/edit', [
            'pageTitle' => 'Edit: ' . basename($file),
            'file' => $file,
            'content' => $content,
            'parsedData' => $parsedData,
            'extension' => $extension,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    /**
     * Handle data file update
     */
    private function handleUpdate(string $fullPath, string $file): void
    {
        $this->validateCsrf();

        $content = $this->post('content', '');
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        // Validate syntax
        $validation = $this->validateDataSyntax($content, $extension);

        if (!$validation['valid']) {
            $this->flash('error', 'Invalid syntax: ' . $validation['error']);
            $this->redirect('data.php?action=edit&file=' . urlencode($file));
        }

        // Save file
        if (file_put_contents($fullPath, $content)) {
            $this->flash('success', 'Data file saved successfully');
        } else {
            $this->flash('error', 'Failed to save data file');
        }

        $this->redirect('data.php?action=edit&file=' . urlencode($file));
    }

    /**
     * Create new data file
     */
    public function create(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->render('data/create', [
                'pageTitle' => 'New Data File',
            ]);
            return;
        }

        $this->validateCsrf();

        $filename = $this->post('filename', '');
        $extension = $this->post('extension', 'yaml');
        $content = $this->post('content', '');

        if (!$filename) {
            $this->flash('error', 'Filename is required');
            $this->redirect('data.php?action=create');
        }

        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
        $fullFilename = $filename . '.' . $extension;
        $fullPath = $this->dataDir . '/' . $fullFilename;

        if (file_exists($fullPath)) {
            $this->flash('error', 'File already exists');
            $this->redirect('data.php?action=create');
        }

        // Create with default content if empty
        if (!$content) {
            $content = $this->getDefaultContent($extension);
        }

        if (file_put_contents($fullPath, $content)) {
            $this->flash('success', 'Data file created');
            $this->redirect('data.php?action=edit&file=' . urlencode($fullFilename));
        } else {
            $this->flash('error', 'Failed to create data file');
            $this->redirect('data.php?action=create');
        }
    }

    /**
     * Delete data file
     */
    public function delete(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->json(['success' => false, 'error' => 'POST required'], 400);
        }

        $this->validateCsrf();

        $file = $this->post('file', '');

        if (!$file) {
            $this->json(['success' => false, 'error' => 'File required'], 400);
        }

        $fullPath = $this->dataDir . '/' . $file;

        // Security: ensure file is within data directory
        $realPath = realpath($fullPath);
        $realDataDir = realpath($this->dataDir);

        if (!$realPath || strpos($realPath, $realDataDir) !== 0) {
            $this->json(['success' => false, 'error' => 'Invalid file path'], 400);
        }

        if (unlink($fullPath)) {
            $this->json(['success' => true, 'message' => 'File deleted']);
        } else {
            $this->json(['success' => false, 'error' => 'Failed to delete file'], 500);
        }
    }

    /**
     * List all data files
     */
    private function listDataFiles(): array
    {
        $files = [];

        if (!is_dir($this->dataDir)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dataDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $ext = $file->getExtension();
            if (!in_array($ext, ['yaml', 'yml', 'json', 'toml'])) continue;

            $relativePath = str_replace($this->dataDir . '/', '', $file->getPathname());

            $files[] = [
                'path' => $relativePath,
                'name' => $file->getFilename(),
                'extension' => $ext,
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
            ];
        }

        // Sort by name
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $files;
    }

    /**
     * Parse data file content
     */
    private function parseDataFile(string $content, string $extension): mixed
    {
        switch ($extension) {
            case 'json':
                return json_decode($content, true);
            case 'yaml':
            case 'yml':
                if (function_exists('yaml_parse')) {
                    return yaml_parse($content);
                }
                // Fallback: return raw content if no YAML extension
                return null;
            case 'toml':
                // TOML parsing requires external library
                return null;
            default:
                return null;
        }
    }

    /**
     * Validate data file syntax
     */
    private function validateDataSyntax(string $content, string $extension): array
    {
        switch ($extension) {
            case 'json':
                json_decode($content);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return ['valid' => false, 'error' => json_last_error_msg()];
                }
                return ['valid' => true];
            case 'yaml':
            case 'yml':
                // Basic YAML validation
                if (function_exists('yaml_parse')) {
                    $result = @yaml_parse($content);
                    if ($result === false) {
                        return ['valid' => false, 'error' => 'Invalid YAML syntax'];
                    }
                }
                return ['valid' => true];
            default:
                return ['valid' => true]; // Accept other formats
        }
    }

    /**
     * Get default content for new data file
     */
    private function getDefaultContent(string $extension): string
    {
        switch ($extension) {
            case 'json':
                return "{\n  \n}";
            case 'yaml':
            case 'yml':
                return "# Data file\n";
            case 'toml':
                return "# Data file\n";
            default:
                return '';
        }
    }
}

