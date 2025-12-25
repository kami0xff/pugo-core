<?php
/**
 * Pugo Core - Select Field Component
 */

namespace Pugo\Components\FormFields;

class SelectField extends Field
{
    protected function getDefaultProps(): array
    {
        return array_merge(parent::getDefaultProps(), [
            'options' => [],
            'empty_option' => null,  // null = no empty option, string = label for empty
            'multiple' => false,
            'onchange' => null,
        ]);
    }
    
    /**
     * Set options from array
     * Supports: ['a', 'b'] or ['key' => 'Label'] or [['value' => 'a', 'label' => 'A']]
     */
    public function options(array $options): static
    {
        $normalized = [];
        
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $normalized[] = $option;
            } elseif (is_int($key)) {
                $normalized[] = ['value' => $option, 'label' => $option];
            } else {
                $normalized[] = ['value' => $key, 'label' => $option];
            }
        }
        
        $this->props['options'] = $normalized;
        return $this;
    }
    
    public function render(): string
    {
        $value = $this->props['value'];
        $options = $this->props['options'];
        $emptyOption = $this->props['empty_option'];
        $multiple = $this->props['multiple'];
        
        $attrs = $this->getInputAttributes();
        $attrs['class'] = 'pugo-select';
        
        if ($multiple) {
            $attrs['multiple'] = true;
        }
        if ($this->props['onchange']) {
            $attrs['onchange'] = $this->props['onchange'];
        }
        
        $html = '<select ' . $this->buildAttributes($attrs) . '>';
        
        // Empty option
        if ($emptyOption !== null) {
            $html .= '<option value="">' . $this->e($emptyOption) . '</option>';
        }
        
        // Options
        foreach ($options as $opt) {
            $optValue = $opt['value'] ?? '';
            $optLabel = $opt['label'] ?? $optValue;
            $optDisabled = $opt['disabled'] ?? false;
            $optGroup = $opt['group'] ?? null;
            
            $selected = $multiple 
                ? (is_array($value) && in_array($optValue, $value))
                : ($optValue == $value);
            
            $optAttrs = 'value="' . $this->e($optValue) . '"';
            if ($selected) {
                $optAttrs .= ' selected';
            }
            if ($optDisabled) {
                $optAttrs .= ' disabled';
            }
            
            $html .= '<option ' . $optAttrs . '>' . $this->e($optLabel) . '</option>';
        }
        
        $html .= '</select>';
        
        return $this->wrapField($html);
    }
}

