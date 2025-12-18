<?php
/**
 * Pugo Core - Text Field Component
 */

namespace Pugo\Components\FormFields;

class TextField extends Field
{
    protected function getDefaultProps(): array
    {
        return array_merge(parent::getDefaultProps(), [
            'type' => 'text',  // text, email, url, password, number
            'max_length' => null,
            'min' => null,
            'max' => null,
            'step' => null,
            'pattern' => null,
            'autocomplete' => null,
            'oninput' => null,
        ]);
    }
    
    public function render(): string
    {
        $type = $this->props['type'];
        $value = $this->props['value'];
        
        $attrs = $this->getInputAttributes();
        $attrs['type'] = $type;
        $attrs['value'] = $value;
        $attrs['class'] = 'pugo-input';
        
        if ($this->props['max_length']) {
            $attrs['maxlength'] = $this->props['max_length'];
        }
        if ($this->props['min'] !== null) {
            $attrs['min'] = $this->props['min'];
        }
        if ($this->props['max'] !== null) {
            $attrs['max'] = $this->props['max'];
        }
        if ($this->props['step']) {
            $attrs['step'] = $this->props['step'];
        }
        if ($this->props['pattern']) {
            $attrs['pattern'] = $this->props['pattern'];
        }
        if ($this->props['autocomplete']) {
            $attrs['autocomplete'] = $this->props['autocomplete'];
        }
        if ($this->props['oninput']) {
            $attrs['oninput'] = $this->props['oninput'];
        }
        
        $input = '<input ' . $this->buildAttributes($attrs) . '>';
        
        return $this->wrapField($input);
    }
}

