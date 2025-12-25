<?php
/**
 * Pugo Core - Base Form Field
 * 
 * All form fields extend this.
 */

namespace Pugo\Components\FormFields;

use Pugo\Components\Component;

abstract class Field extends Component
{
    protected function getDefaultProps(): array
    {
        return [
            'name' => '',
            'label' => '',
            'value' => '',
            'placeholder' => '',
            'required' => false,
            'disabled' => false,
            'readonly' => false,
            'help' => '',
            'error' => '',
            'size' => 'default',  // small, default, large
            'autofocus' => false,
        ];
    }
    
    /**
     * Render the label
     */
    protected function renderLabel(): string
    {
        $label = $this->props['label'];
        $required = $this->props['required'];
        $help = $this->props['help'];
        
        if (!$label) {
            return '';
        }
        
        $html = '<label class="pugo-field-label">';
        $html .= $this->e($label);
        
        if ($required) {
            $html .= '<span class="pugo-field-required">*</span>';
        }
        
        if ($help) {
            $html .= '<span class="pugo-field-help" title="' . $this->e($help) . '">?</span>';
        }
        
        $html .= '</label>';
        
        return $html;
    }
    
    /**
     * Render error message
     */
    protected function renderError(): string
    {
        $error = $this->props['error'];
        
        if (!$error) {
            return '';
        }
        
        return '<div class="pugo-field-error">' . $this->e($error) . '</div>';
    }
    
    /**
     * Build common input attributes
     */
    protected function getInputAttributes(): array
    {
        $attrs = [
            'name' => $this->props['name'],
            'placeholder' => $this->props['placeholder'],
        ];
        
        if ($this->props['required']) {
            $attrs['required'] = true;
        }
        if ($this->props['disabled']) {
            $attrs['disabled'] = true;
        }
        if ($this->props['readonly']) {
            $attrs['readonly'] = true;
        }
        if ($this->props['autofocus']) {
            $attrs['autofocus'] = true;
        }
        
        return $attrs;
    }
    
    /**
     * Wrap field in container
     */
    protected function wrapField(string $input): string
    {
        $class = 'pugo-field pugo-field--' . $this->props['size'];
        
        if ($this->props['error']) {
            $class .= ' pugo-field--error';
        }
        
        $html = '<div class="' . $class . '">';
        $html .= $this->renderLabel();
        $html .= $input;
        $html .= $this->renderError();
        $html .= '</div>';
        
        return $html;
    }
}

