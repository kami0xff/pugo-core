<?php
/**
 * Pugo Dynamic Field Renderer
 * 
 * Renders form fields dynamically based on content type definitions.
 * Supports: text, textarea, number, select, checkbox, date, datetime, 
 *           list, image, image_list, tags, url
 */

/**
 * Render a single form field based on field definition
 * 
 * @param string $name Field name (for form submission)
 * @param array $field Field definition from content type
 * @param mixed $value Current value
 * @return string HTML output
 */
function render_field($name, $field, $value = null) {
    $type = $field['type'] ?? 'text';
    $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
    $required = !empty($field['required']);
    $placeholder = $field['placeholder'] ?? '';
    $id = 'field_' . $name;
    
    $html = '<div class="form-group dynamic-field" data-field="' . htmlspecialchars($name) . '">';
    $html .= '<label class="form-label" for="' . $id . '">' . htmlspecialchars($label);
    if ($required) $html .= ' <span class="required">*</span>';
    $html .= '</label>';
    
    switch ($type) {
        case 'text':
        case 'url':
            $html .= sprintf(
                '<input type="%s" name="%s" id="%s" class="form-input" value="%s" placeholder="%s" %s>',
                $type === 'url' ? 'url' : 'text',
                htmlspecialchars($name),
                $id,
                htmlspecialchars($value ?? ''),
                htmlspecialchars($placeholder),
                $required ? 'required' : ''
            );
            break;
            
        case 'textarea':
            $html .= sprintf(
                '<textarea name="%s" id="%s" class="form-input" rows="3" placeholder="%s" %s>%s</textarea>',
                htmlspecialchars($name),
                $id,
                htmlspecialchars($placeholder),
                $required ? 'required' : '',
                htmlspecialchars($value ?? '')
            );
            break;
            
        case 'number':
            $min = isset($field['min']) ? 'min="' . (int)$field['min'] . '"' : '';
            $max = isset($field['max']) ? 'max="' . (int)$field['max'] . '"' : '';
            $step = isset($field['step']) ? 'step="' . $field['step'] . '"' : '';
            $html .= sprintf(
                '<input type="number" name="%s" id="%s" class="form-input" value="%s" %s %s %s %s style="max-width: 120px;">',
                htmlspecialchars($name),
                $id,
                htmlspecialchars($value ?? ''),
                $min, $max, $step,
                $required ? 'required' : ''
            );
            break;
            
        case 'select':
            $options = $field['options'] ?? [];
            $html .= sprintf('<select name="%s" id="%s" class="form-input" style="max-width: 200px;" %s>',
                htmlspecialchars($name), $id, $required ? 'required' : '');
            $html .= '<option value="">Select...</option>';
            foreach ($options as $opt_key => $opt_val) {
                // Handle both ['opt1', 'opt2'] and ['key' => 'Label'] formats
                $opt_value = is_numeric($opt_key) ? $opt_val : $opt_key;
                $opt_label = is_numeric($opt_key) ? ucfirst($opt_val) : $opt_val;
                $selected = ($value === $opt_value) ? 'selected' : '';
                $html .= sprintf('<option value="%s" %s>%s</option>',
                    htmlspecialchars($opt_value), $selected, htmlspecialchars($opt_label));
            }
            $html .= '</select>';
            break;
            
        case 'checkbox':
            $checked = !empty($value) ? 'checked' : '';
            $html .= sprintf(
                '<label class="checkbox-label"><input type="checkbox" name="%s" id="%s" value="1" %s> %s</label>',
                htmlspecialchars($name), $id, $checked, htmlspecialchars($field['checkbox_label'] ?? 'Yes')
            );
            break;
            
        case 'date':
            $html .= sprintf(
                '<input type="date" name="%s" id="%s" class="form-input" value="%s" %s style="max-width: 200px;">',
                htmlspecialchars($name), $id,
                htmlspecialchars($value ? date('Y-m-d', strtotime($value)) : ''),
                $required ? 'required' : ''
            );
            break;
            
        case 'datetime':
            $html .= sprintf(
                '<input type="datetime-local" name="%s" id="%s" class="form-input" value="%s" %s style="max-width: 250px;">',
                htmlspecialchars($name), $id,
                htmlspecialchars($value ? date('Y-m-d\TH:i', strtotime($value)) : ''),
                $required ? 'required' : ''
            );
            break;
            
        case 'list':
            $items = is_array($value) ? $value : [];
            $html .= '<div class="list-field-container" data-name="' . htmlspecialchars($name) . '">';
            $html .= '<div class="list-items">';
            foreach ($items as $i => $item) {
                $html .= sprintf(
                    '<div class="list-item"><input type="text" name="%s[]" class="form-input" value="%s"><button type="button" class="btn-remove-item" onclick="removeListItem(this)">×</button></div>',
                    htmlspecialchars($name), htmlspecialchars($item)
                );
            }
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-sm btn-secondary add-list-item" onclick="addListItem(this, \'' . htmlspecialchars($name) . '\')">+ Add Item</button>';
            $html .= '</div>';
            break;
            
        case 'image':
            $html .= '<div class="image-field-container">';
            $html .= sprintf(
                '<input type="text" name="%s" id="%s" class="form-input" value="%s" placeholder="/images/...">',
                htmlspecialchars($name), $id, htmlspecialchars($value ?? '')
            );
            $html .= '<button type="button" class="btn btn-secondary btn-sm" onclick="openMediaPicker(\'' . $id . '\')" style="margin-top: 8px;">Browse Media</button>';
            if ($value) {
                $html .= '<div class="image-preview" style="margin-top: 8px;"><img src="' . htmlspecialchars($value) . '" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 4px;"></div>';
            }
            $html .= '</div>';
            break;
            
        case 'image_list':
            $images = is_array($value) ? $value : [];
            $html .= '<div class="image-list-container" data-name="' . htmlspecialchars($name) . '">';
            $html .= '<div class="image-list-items" style="display: flex; flex-wrap: wrap; gap: 10px;">';
            foreach ($images as $i => $img) {
                $html .= '<div class="image-list-item" style="position: relative;">';
                $html .= sprintf('<input type="hidden" name="%s[]" value="%s">', htmlspecialchars($name), htmlspecialchars($img));
                $html .= '<img src="' . htmlspecialchars($img) . '" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;">';
                $html .= '<button type="button" class="btn-remove-image" onclick="removeImageListItem(this)" style="position: absolute; top: -5px; right: -5px; background: #e11d48; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer;">×</button>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-secondary btn-sm" onclick="addImageListItem(this, \'' . htmlspecialchars($name) . '\')" style="margin-top: 8px;">+ Add Image</button>';
            $html .= '</div>';
            break;
            
        case 'tags':
            $tags = is_array($value) ? implode(', ', $value) : ($value ?? '');
            $html .= sprintf(
                '<input type="text" name="%s" id="%s" class="form-input" value="%s" placeholder="tag1, tag2, tag3">',
                htmlspecialchars($name), $id, htmlspecialchars($tags)
            );
            $html .= '<div class="field-hint">Separate tags with commas</div>';
            break;
            
        case 'color':
            $html .= sprintf(
                '<input type="color" name="%s" id="%s" value="%s" style="width: 60px; height: 36px; padding: 2px;">',
                htmlspecialchars($name), $id, htmlspecialchars($value ?? '#000000')
            );
            break;
            
        case 'range':
            $min = $field['min'] ?? 0;
            $max = $field['max'] ?? 100;
            $html .= '<div style="display: flex; align-items: center; gap: 12px;">';
            $html .= sprintf(
                '<input type="range" name="%s" id="%s" min="%d" max="%d" value="%s" style="flex: 1;" oninput="document.getElementById(\'%s_val\').textContent = this.value">',
                htmlspecialchars($name), $id, $min, $max, htmlspecialchars($value ?? $min), $id
            );
            $html .= '<span id="' . $id . '_val">' . htmlspecialchars($value ?? $min) . '</span>';
            $html .= '</div>';
            break;
            
        default:
            $html .= sprintf(
                '<input type="text" name="%s" id="%s" class="form-input" value="%s">',
                htmlspecialchars($name), $id, htmlspecialchars($value ?? '')
            );
    }
    
    // Add help text if provided
    if (!empty($field['help'])) {
        $html .= '<div class="field-hint">' . htmlspecialchars($field['help']) . '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Render all custom fields for a content type
 * 
 * @param string $content_type Content type key
 * @param array $frontmatter Current frontmatter values
 * @return string HTML output
 */
function render_content_type_fields($content_type, $frontmatter = []) {
    $type_info = get_content_type($content_type);
    $fields = $type_info['fields'] ?? [];
    
    if (empty($fields)) {
        return '';
    }
    
    $html = '<div class="content-type-fields card" style="margin-bottom: 24px;">';
    $html .= '<div class="card-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border-color);">';
    $html .= '<h3 style="margin: 0; font-size: 14px; display: flex; align-items: center; gap: 8px;">';
    $html .= pugo_icon($type_info['icon'], 16);
    $html .= '<span>' . htmlspecialchars($type_info['name']) . ' Fields</span>';
    $html .= '<span style="font-weight: normal; color: var(--text-muted); font-size: 12px;">Custom fields for this content type</span>';
    $html .= '</h3></div>';
    $html .= '<div style="padding: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">';
    
    foreach ($fields as $name => $field) {
        $value = $frontmatter[$name] ?? null;
        $html .= render_field($name, $field, $value);
    }
    
    $html .= '</div></div>';
    
    return $html;
}

/**
 * CSS styles for dynamic fields
 */
function render_dynamic_field_styles() {
    return <<<CSS
<style>
/* Dynamic Fields */
.dynamic-field {
    margin-bottom: 0;
}

.dynamic-field .required {
    color: var(--accent-primary);
}

.dynamic-field .field-hint {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* List fields */
.list-field-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.list-items {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.list-item {
    display: flex;
    gap: 8px;
    align-items: center;
}

.list-item .form-input {
    flex: 1;
}

.btn-remove-item {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    width: 28px;
    height: 28px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}

.btn-remove-item:hover {
    background: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

.add-list-item {
    align-self: flex-start;
}

/* Image fields */
.image-field-container {
    display: flex;
    flex-direction: column;
}

/* Content type fields card */
.content-type-fields {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
}

.content-type-fields .card-header {
    background: var(--bg-tertiary);
}
</style>
CSS;
}

/**
 * JavaScript for dynamic fields
 */
function render_dynamic_field_scripts() {
    return <<<JS
<script>
// Add item to list field
function addListItem(btn, fieldName) {
    const container = btn.closest('.list-field-container').querySelector('.list-items');
    const item = document.createElement('div');
    item.className = 'list-item';
    item.innerHTML = '<input type="text" name="' + fieldName + '[]" class="form-input" placeholder="Enter value..."><button type="button" class="btn-remove-item" onclick="removeListItem(this)">×</button>';
    container.appendChild(item);
    item.querySelector('input').focus();
}

// Remove item from list field
function removeListItem(btn) {
    btn.closest('.list-item').remove();
}

// Add image to image list field
function addImageListItem(btn, fieldName) {
    // Open media picker, then add the selected image
    openMediaPickerForList(fieldName, btn.closest('.image-list-container'));
}

// Remove image from image list
function removeImageListItem(btn) {
    btn.closest('.image-list-item').remove();
}

// Media picker for single image field
function openMediaPicker(inputId) {
    // Use existing media browser if available
    if (typeof openMediaBrowser === 'function') {
        window._mediaPickerTarget = inputId;
        openMediaBrowser();
    } else {
        alert('Media browser not available. Please enter the path manually.');
    }
}

// Override media selection for image fields
if (typeof window.originalSelectMedia === 'undefined' && typeof selectMedia === 'function') {
    window.originalSelectMedia = selectMedia;
    window.selectMedia = function(url) {
        if (window._mediaPickerTarget) {
            document.getElementById(window._mediaPickerTarget).value = url;
            // Update preview if exists
            const container = document.getElementById(window._mediaPickerTarget).closest('.image-field-container');
            if (container) {
                let preview = container.querySelector('.image-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'image-preview';
                    preview.style.marginTop = '8px';
                    container.appendChild(preview);
                }
                preview.innerHTML = '<img src="' + url + '" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 4px;">';
            }
            closeMediaBrowser();
            window._mediaPickerTarget = null;
        } else if (window.originalSelectMedia) {
            window.originalSelectMedia(url);
        }
    };
}

// Media picker for image list
function openMediaPickerForList(fieldName, container) {
    if (typeof openMediaBrowser === 'function') {
        window._imageListContainer = container;
        window._imageListFieldName = fieldName;
        openMediaBrowser();
        
        // Override selectMedia temporarily
        const origSelect = window.selectMedia;
        window.selectMedia = function(url) {
            if (window._imageListContainer) {
                const itemsContainer = window._imageListContainer.querySelector('.image-list-items');
                const item = document.createElement('div');
                item.className = 'image-list-item';
                item.style.position = 'relative';
                item.innerHTML = '<input type="hidden" name="' + window._imageListFieldName + '[]" value="' + url + '">' +
                    '<img src="' + url + '" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;">' +
                    '<button type="button" class="btn-remove-image" onclick="removeImageListItem(this)" style="position: absolute; top: -5px; right: -5px; background: #e11d48; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer;">×</button>';
                itemsContainer.appendChild(item);
                closeMediaBrowser();
                window._imageListContainer = null;
                window._imageListFieldName = null;
                window.selectMedia = origSelect;
            }
        };
    } else {
        alert('Media browser not available.');
    }
}
</script>
JS;
}

/**
 * Parse submitted form data for content type fields
 * Returns array of field values to merge into frontmatter
 */
function parse_content_type_form_data($content_type, $post_data) {
    $type_info = get_content_type($content_type);
    $fields = $type_info['fields'] ?? [];
    $result = [];
    
    foreach ($fields as $name => $field) {
        $type = $field['type'] ?? 'text';
        
        if (!isset($post_data[$name])) {
            continue;
        }
        
        $value = $post_data[$name];
        
        switch ($type) {
            case 'checkbox':
                $result[$name] = !empty($value);
                break;
                
            case 'number':
                $result[$name] = is_numeric($value) ? (float)$value : null;
                break;
                
            case 'list':
            case 'image_list':
                // Already an array from form
                $result[$name] = is_array($value) ? array_filter($value) : [];
                break;
                
            case 'tags':
                // Convert comma-separated to array
                if (is_string($value)) {
                    $result[$name] = array_map('trim', explode(',', $value));
                    $result[$name] = array_filter($result[$name]);
                }
                break;
                
            case 'date':
            case 'datetime':
                $result[$name] = !empty($value) ? $value : null;
                break;
                
            default:
                $result[$name] = $value;
        }
    }
    
    return $result;
}

