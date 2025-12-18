<?php
/**
 * Pugo Core - Checkbox Field Component
 */

namespace Pugo\Components\FormFields;

class CheckboxField extends Field
{
    protected function getDefaultProps(): array
    {
        return array_merge(parent::getDefaultProps(), [
            'checked' => false,
            'value' => '1',
            'inline_label' => '',  // Label displayed next to checkbox
        ]);
    }
    
    public function render(): string
    {
        $name = $this->props['name'];
        $value = $this->props['value'];
        $checked = $this->props['checked'];
        $label = $this->props['label'];
        $inlineLabel = $this->props['inline_label'] ?: $label;
        $disabled = $this->props['disabled'];
        
        $attrs = [
            'type' => 'checkbox',
            'name' => $name,
            'value' => $value,
            'class' => 'pugo-checkbox',
        ];
        
        if ($checked) {
            $attrs['checked'] = true;
        }
        if ($disabled) {
            $attrs['disabled'] = true;
        }
        
        $id = 'checkbox_' . md5($name . $value);
        $attrs['id'] = $id;
        
        $html = '<div class="pugo-field pugo-field--checkbox">';
        $html .= '<label class="pugo-checkbox-label" for="' . $id . '">';
        $html .= '<input ' . $this->buildAttributes($attrs) . '>';
        $html .= '<span class="pugo-checkbox-text">' . $this->e($inlineLabel) . '</span>';
        $html .= '</label>';
        $html .= $this->renderError();
        $html .= '</div>';
        
        return $html;
    }
}

