<?php
/**
 * Pugo Content Type Manager
 * 
 * Dynamically loads and manages content types, allowing Pugo to handle
 * any kind of Hugo site: help centers, blogs, review sites, galleries, etc.
 */

class ContentTypeManager {
    private static $instance = null;
    private $types = [];
    private $sectionMap = [];
    
    private function __construct() {
        $this->loadContentTypes();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load all content type definitions from the content_types directory
     * content_types/ is at admin root level, not inside core/
     */
    private function loadContentTypes(): void {
        // Go up 2 levels from includes/ to admin/, then into content_types/
        $typesDir = dirname(__DIR__, 2) . '/content_types';
        
        if (!is_dir($typesDir)) {
            return;
        }
        
        foreach (glob($typesDir . '/*.php') as $file) {
            $typeName = basename($file, '.php');
            $typeConfig = require $file;
            
            if (is_array($typeConfig)) {
                $this->types[$typeName] = $typeConfig;
                
                // Map sections to content types
                $sections = $typeConfig['sections'] ?? [];
                foreach ($sections as $section) {
                    if ($section === '*') {
                        $this->types[$typeName]['is_default'] = true;
                    } else {
                        $this->sectionMap[$section] = $typeName;
                    }
                }
            }
        }
    }
    
    /**
     * Get all available content types
     */
    public function getTypes(): array {
        return $this->types;
    }
    
    /**
     * Get a specific content type by name
     */
    public function getType(string $name): ?array {
        return $this->types[$name] ?? null;
    }
    
    /**
     * Get the content type for a given section
     */
    public function getTypeForSection(string $section): array {
        // Check if section has a specific type
        if (isset($this->sectionMap[$section])) {
            return $this->types[$this->sectionMap[$section]];
        }
        
        // Return default type (article)
        foreach ($this->types as $type) {
            if (!empty($type['is_default'])) {
                return $type;
            }
        }
        
        // Fallback to first available type
        return reset($this->types) ?: [];
    }
    
    /**
     * Get content type name for a section
     */
    public function getTypeNameForSection(string $section): string {
        if (isset($this->sectionMap[$section])) {
            return $this->sectionMap[$section];
        }
        
        foreach ($this->types as $name => $type) {
            if (!empty($type['is_default'])) {
                return $name;
            }
        }
        
        return 'article';
    }
    
    /**
     * Get frontmatter fields for a content type
     */
    public function getFields(string $typeName): array {
        $type = $this->getType($typeName);
        return $type['fields'] ?? [];
    }
    
    /**
     * Get fields for a section
     */
    public function getFieldsForSection(string $section): array {
        $type = $this->getTypeForSection($section);
        return $type['fields'] ?? [];
    }
    
    /**
     * Get shortcodes for a content type
     */
    public function getShortcodes(string $typeName): array {
        $type = $this->getType($typeName);
        return $type['shortcodes'] ?? [];
    }
    
    /**
     * Get editor layout for a content type
     */
    public function getLayout(string $typeName): array {
        $type = $this->getType($typeName);
        return $type['layout'] ?? [
            'sidebar' => [],
            'main' => ['title', 'description', 'content'],
            'meta' => []
        ];
    }
    
    /**
     * Validate content against its type
     */
    public function validate(string $typeName, array $frontmatter, string $body): array {
        $errors = [];
        $type = $this->getType($typeName);
        
        if (!$type) {
            return ['Unknown content type: ' . $typeName];
        }
        
        $fields = $type['fields'] ?? [];
        $validation = $type['validation'] ?? [];
        
        foreach ($fields as $fieldName => $fieldConfig) {
            $value = $frontmatter[$fieldName] ?? null;
            
            // Check required fields
            if (!empty($fieldConfig['required']) && empty($value)) {
                $errors[] = "Field '{$fieldConfig['label']}' is required";
            }
            
            // Check field-specific validation
            if (isset($validation[$fieldName]) && !empty($value)) {
                $rules = $validation[$fieldName];
                
                if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                    $errors[] = "{$fieldConfig['label']} must be at least {$rules['min_length']} characters";
                }
                
                if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                    $errors[] = "{$fieldConfig['label']} must be less than {$rules['max_length']} characters";
                }
                
                if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
                    $errors[] = "{$fieldConfig['label']} must be at least {$rules['min']}";
                }
                
                if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
                    $errors[] = "{$fieldConfig['label']} must be at most {$rules['max']}";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Get default values for a content type
     */
    public function getDefaults(string $typeName): array {
        $type = $this->getType($typeName);
        $defaults = [];
        
        foreach ($type['fields'] ?? [] as $fieldName => $fieldConfig) {
            if (isset($fieldConfig['default'])) {
                $defaults[$fieldName] = $fieldConfig['default'];
            }
        }
        
        // Always set date to now
        if (!isset($defaults['date'])) {
            $defaults['date'] = date('Y-m-d');
        }
        
        return $defaults;
    }
    
    /**
     * Render a field input based on its type
     */
    public function renderField(string $fieldName, array $fieldConfig, $value = null): string {
        $type = $fieldConfig['type'] ?? 'text';
        $label = $fieldConfig['label'] ?? ucfirst($fieldName);
        $required = !empty($fieldConfig['required']) ? 'required' : '';
        $placeholder = $fieldConfig['placeholder'] ?? '';
        $help = $fieldConfig['help'] ?? '';
        $value = $value ?? $fieldConfig['default'] ?? '';
        
        $html = '<div class="form-group">';
        $html .= '<label for="' . $fieldName . '">' . htmlspecialchars($label);
        if ($required) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</label>';
        
        switch ($type) {
            case 'text':
            case 'url':
                $inputType = $type === 'url' ? 'url' : 'text';
                $html .= '<input type="' . $inputType . '" id="' . $fieldName . '" name="' . $fieldName . '" 
                          value="' . htmlspecialchars($value) . '" placeholder="' . htmlspecialchars($placeholder) . '" 
                          class="form-control" ' . $required . '>';
                break;
                
            case 'textarea':
                $maxLength = $fieldConfig['max_length'] ?? '';
                $html .= '<textarea id="' . $fieldName . '" name="' . $fieldName . '" 
                          placeholder="' . htmlspecialchars($placeholder) . '" class="form-control" 
                          ' . ($maxLength ? 'maxlength="' . $maxLength . '"' : '') . ' ' . $required . '>' 
                          . htmlspecialchars($value) . '</textarea>';
                break;
                
            case 'date':
                $html .= '<input type="date" id="' . $fieldName . '" name="' . $fieldName . '" 
                          value="' . htmlspecialchars($value) . '" class="form-control" ' . $required . '>';
                break;
                
            case 'number':
                $html .= '<input type="number" id="' . $fieldName . '" name="' . $fieldName . '" 
                          value="' . htmlspecialchars($value) . '" class="form-control" ' . $required . '>';
                break;
                
            case 'checkbox':
                $checked = $value ? 'checked' : '';
                $html .= '<input type="checkbox" id="' . $fieldName . '" name="' . $fieldName . '" 
                          value="1" class="form-checkbox" ' . $checked . '>';
                break;
                
            case 'select':
                $html .= '<select id="' . $fieldName . '" name="' . $fieldName . '" class="form-control" ' . $required . '>';
                foreach ($fieldConfig['options'] ?? [] as $optValue => $optLabel) {
                    $selected = $value == $optValue ? 'selected' : '';
                    $html .= '<option value="' . htmlspecialchars($optValue) . '" ' . $selected . '>' 
                             . htmlspecialchars($optLabel) . '</option>';
                }
                $html .= '</select>';
                break;
                
            case 'rating':
                $max = $fieldConfig['max'] ?? 5;
                $step = $fieldConfig['step'] ?? 1;
                $html .= '<div class="rating-input" data-max="' . $max . '" data-step="' . $step . '">';
                $html .= '<input type="hidden" id="' . $fieldName . '" name="' . $fieldName . '" value="' . htmlspecialchars($value) . '">';
                for ($i = 1; $i <= $max; $i++) {
                    $active = $value >= $i ? 'active' : '';
                    $html .= '<span class="star ' . $active . '" data-value="' . $i . '">★</span>';
                }
                $html .= '<span class="rating-value">' . ($value ?: '0') . '/' . $max . '</span>';
                $html .= '</div>';
                break;
                
            case 'tags':
                $tags = is_array($value) ? implode(', ', $value) : $value;
                $html .= '<input type="text" id="' . $fieldName . '" name="' . $fieldName . '" 
                          value="' . htmlspecialchars($tags) . '" placeholder="tag1, tag2, tag3" 
                          class="form-control tags-input">';
                break;
                
            case 'image':
                $html .= '<div class="image-picker">';
                $html .= '<input type="hidden" id="' . $fieldName . '" name="' . $fieldName . '" value="' . htmlspecialchars($value) . '">';
                if ($value) {
                    $html .= '<img src="' . htmlspecialchars($value) . '" class="preview-image">';
                }
                $html .= '<button type="button" class="btn btn-secondary" onclick="pickImage(\'' . $fieldName . '\')">Choose Image</button>';
                $html .= '</div>';
                break;
                
            case 'list':
                $items = is_array($value) ? $value : [];
                $html .= '<div class="list-input" data-field="' . $fieldName . '">';
                $html .= '<div class="list-items">';
                foreach ($items as $item) {
                    $html .= '<div class="list-item"><input type="text" value="' . htmlspecialchars($item) . '"><button type="button" class="remove-item">×</button></div>';
                }
                $html .= '</div>';
                $html .= '<button type="button" class="btn btn-secondary add-item">+ Add Item</button>';
                $html .= '<input type="hidden" id="' . $fieldName . '" name="' . $fieldName . '" value="">';
                $html .= '</div>';
                break;
                
            default:
                $html .= '<input type="text" id="' . $fieldName . '" name="' . $fieldName . '" 
                          value="' . htmlspecialchars($value) . '" class="form-control">';
        }
        
        if ($help) {
            $html .= '<small class="form-help">' . htmlspecialchars($help) . '</small>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

/**
 * Helper function to get the content type manager
 */
function pugo_content_types(): ContentTypeManager {
    return ContentTypeManager::getInstance();
}

