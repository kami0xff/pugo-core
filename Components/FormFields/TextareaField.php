<?php
/**
 * Pugo Core - Textarea Field Component
 */

namespace Pugo\Components\FormFields;

class TextareaField extends Field
{
    protected function getDefaultProps(): array
    {
        return array_merge(parent::getDefaultProps(), [
            'rows' => 4,
            'max_length' => null,
            'resize' => 'vertical',  // none, vertical, horizontal, both
            'oninput' => null,
        ]);
    }
    
    public function render(): string
    {
        $value = $this->props['value'];
        $rows = $this->props['rows'];
        $resize = $this->props['resize'];
        
        $attrs = $this->getInputAttributes();
        $attrs['rows'] = $rows;
        $attrs['class'] = 'pugo-textarea';
        $attrs['style'] = 'resize: ' . $resize;
        
        if ($this->props['max_length']) {
            $attrs['maxlength'] = $this->props['max_length'];
        }
        if ($this->props['oninput']) {
            $attrs['oninput'] = $this->props['oninput'];
        }
        
        $input = '<textarea ' . $this->buildAttributes($attrs) . '>' . $this->e($value) . '</textarea>';
        
        return $this->wrapField($input);
    }
}

