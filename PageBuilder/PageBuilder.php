<?php
/**
 * Pugo Core 3.0 - Page Builder
 * 
 * Visual page builder that allows drag-and-drop composition of blocks.
 * Stores page layouts in YAML and generates Hugo-compatible output.
 */

namespace Pugo\PageBuilder;

use Pugo\Blocks\BlockRegistry;
use Pugo\Config\PugoConfig;

class PageBuilder
{
    private BlockRegistry $blocks;
    private PugoConfig $config;
    private string $layoutsDir;
    
    public function __construct()
    {
        $this->blocks = BlockRegistry::getInstance();
        $this->config = PugoConfig::getInstance();
        $this->layoutsDir = (defined('DATA_DIR') ? DATA_DIR : getcwd() . '/data') . '/page-layouts';
        
        if (!is_dir($this->layoutsDir)) {
            mkdir($this->layoutsDir, 0755, true);
        }
    }
    
    /**
     * Get page layout by ID
     */
    public function getLayout(string $pageId): ?PageLayout
    {
        $file = $this->layoutsDir . '/' . $this->sanitizeId($pageId) . '.yaml';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        $data = $this->parseYaml($content);
        
        return new PageLayout($pageId, $data);
    }
    
    /**
     * Save page layout
     */
    public function saveLayout(PageLayout $layout): bool
    {
        $file = $this->layoutsDir . '/' . $this->sanitizeId($layout->getId()) . '.yaml';
        $yaml = $this->generateYaml($layout->toArray());
        
        return file_put_contents($file, $yaml) !== false;
    }
    
    /**
     * Create new page layout
     */
    public function createLayout(string $pageId, array $meta = []): PageLayout
    {
        $layout = new PageLayout($pageId, [
            'meta' => array_merge([
                'title' => ucfirst(str_replace('-', ' ', $pageId)),
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ], $meta),
            'sections' => [],
        ]);
        
        $this->saveLayout($layout);
        
        return $layout;
    }
    
    /**
     * Delete a page layout
     */
    public function deleteLayout(string $pageId): bool
    {
        $file = $this->layoutsDir . '/' . $this->sanitizeId($pageId) . '.yaml';
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * List all page layouts
     */
    public function listLayouts(): array
    {
        $layouts = [];
        
        if (!is_dir($this->layoutsDir)) {
            return $layouts;
        }
        
        $files = glob($this->layoutsDir . '/*.yaml');
        
        foreach ($files as $file) {
            $pageId = basename($file, '.yaml');
            $content = file_get_contents($file);
            $data = $this->parseYaml($content);
            
            $layouts[] = [
                'id' => $pageId,
                'title' => $data['meta']['title'] ?? $pageId,
                'updated_at' => $data['meta']['updated_at'] ?? null,
                'sections_count' => count($data['sections'] ?? []),
            ];
        }
        
        return $layouts;
    }
    
    /**
     * Generate Hugo shortcode for a page layout
     */
    public function generateHugoShortcode(PageLayout $layout): string
    {
        $sections = $layout->getSections();
        $output = '';
        
        foreach ($sections as $section) {
            $blockId = $section['block'] ?? '';
            $block = $this->blocks->get($blockId);
            
            if (!$block) continue;
            
            $data = $section['data'] ?? [];
            $partial = $block['partial'];
            
            // Generate Hugo partial call with data
            $output .= "{{/* Section: {$section['id']} - {$block['name']} */}}\n";
            $output .= "{{ partial \"{$partial}\" (dict ";
            
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $output .= "\"{$key}\" (slice ";
                    foreach ($value as $item) {
                        $output .= "(dict ";
                        foreach ($item as $k => $v) {
                            $output .= "\"{$k}\" \"" . addslashes($v) . "\" ";
                        }
                        $output .= ") ";
                    }
                    $output .= ") ";
                } else {
                    $output .= "\"{$key}\" \"" . addslashes($value) . "\" ";
                }
            }
            
            $output .= ") }}\n\n";
        }
        
        return $output;
    }
    
    /**
     * Generate JSON data for frontend editor
     */
    public function toEditorJson(PageLayout $layout): string
    {
        $sections = [];
        
        foreach ($layout->getSections() as $section) {
            $blockId = $section['block'] ?? '';
            $block = $this->blocks->get($blockId);
            
            $sections[] = [
                'id' => $section['id'],
                'block' => $blockId,
                'block_info' => $block ? [
                    'name' => $block['name'],
                    'icon' => $block['icon'],
                    'fields' => $block['fields'],
                ] : null,
                'data' => $section['data'] ?? [],
                'settings' => $section['settings'] ?? [],
            ];
        }
        
        return json_encode([
            'page_id' => $layout->getId(),
            'meta' => $layout->getMeta(),
            'sections' => $sections,
            'available_blocks' => $this->blocks->byCategory(),
        ], JSON_PRETTY_PRINT);
    }
    
    /**
     * Update layout from editor JSON
     */
    public function updateFromEditorJson(string $pageId, string $json): ?PageLayout
    {
        $data = json_decode($json, true);
        
        if (!$data) {
            return null;
        }
        
        $layout = $this->getLayout($pageId);
        
        if (!$layout) {
            $layout = $this->createLayout($pageId, $data['meta'] ?? []);
        }
        
        // Update meta
        if (isset($data['meta'])) {
            $layout->setMeta(array_merge($layout->getMeta(), $data['meta']));
        }
        
        // Update sections
        if (isset($data['sections'])) {
            $layout->setSections($data['sections']);
        }
        
        $layout->touch();
        $this->saveLayout($layout);
        
        return $layout;
    }
    
    /**
     * Duplicate a page layout
     */
    public function duplicate(string $sourceId, string $newId): ?PageLayout
    {
        $source = $this->getLayout($sourceId);
        
        if (!$source) {
            return null;
        }
        
        $data = $source->toArray();
        $data['meta']['title'] .= ' (Copy)';
        $data['meta']['created_at'] = date('c');
        $data['meta']['updated_at'] = date('c');
        
        $newLayout = new PageLayout($newId, $data);
        $this->saveLayout($newLayout);
        
        return $newLayout;
    }
    
    /**
     * Sanitize page ID for file system
     */
    private function sanitizeId(string $id): string
    {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower($id));
    }
    
    /**
     * Simple YAML parser
     */
    private function parseYaml(string $content): array
    {
        if (function_exists('yaml_parse')) {
            return yaml_parse($content) ?: [];
        }
        
        // Basic parser - in production use Symfony YAML
        $result = [];
        $lines = explode("\n", $content);
        $stack = [&$result];
        $indentStack = [-1];
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*#/', $line) || trim($line) === '') {
                continue;
            }
            
            preg_match('/^(\s*)/', $line, $m);
            $indent = strlen($m[1]);
            $line = trim($line);
            
            while (count($indentStack) > 1 && $indent <= end($indentStack)) {
                array_pop($stack);
                array_pop($indentStack);
            }
            
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.*)$/', $line, $m)) {
                $key = $m[1];
                $value = trim($m[2], '"\'');
                $current = &$stack[count($stack) - 1];
                
                if ($value === '' || $value === '|' || $value === '>') {
                    $current[$key] = [];
                    $stack[] = &$current[$key];
                    $indentStack[] = $indent;
                } else {
                    if ($value === 'true') $value = true;
                    elseif ($value === 'false') $value = false;
                    elseif (is_numeric($value)) $value = $value + 0;
                    $current[$key] = $value;
                }
            } elseif (preg_match('/^-\s*(.+)$/', $line, $m)) {
                $current = &$stack[count($stack) - 1];
                if (!is_array($current)) $current = [];
                
                if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(.*)$/', $m[1], $kv)) {
                    $item = [$kv[1] => trim($kv[2], '"\'')];
                    $current[] = $item;
                    $stack[] = &$current[count($current) - 1];
                    $indentStack[] = $indent;
                } else {
                    $current[] = trim($m[1], '"\'');
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Generate YAML from array
     */
    private function generateYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_int($key)) {
                if (is_array($value)) {
                    $yaml .= $prefix . "-\n" . $this->generateYaml($value, $indent + 1);
                } else {
                    $yaml .= $prefix . "- " . $this->formatValue($value) . "\n";
                }
            } else {
                if (is_array($value) && !empty($value)) {
                    $yaml .= $prefix . "{$key}:\n" . $this->generateYaml($value, $indent + 1);
                } else {
                    $yaml .= $prefix . "{$key}: " . $this->formatValue($value) . "\n";
                }
            }
        }
        
        return $yaml;
    }
    
    private function formatValue(mixed $value): string
    {
        if ($value === true) return 'true';
        if ($value === false) return 'false';
        if ($value === null || (is_array($value) && empty($value))) return '';
        if (is_numeric($value)) return (string) $value;
        if (is_string($value) && (str_contains($value, ':') || str_contains($value, '#') || str_contains($value, '"'))) {
            return '"' . addslashes($value) . '"';
        }
        return (string) $value;
    }
}

