<?php
/**
 * Pugo Core - Field Factory
 * 
 * Creates form fields from configuration arrays.
 */

namespace Pugo\Components\FormFields;

class FieldFactory
{
    /**
     * Create a field from type and config
     */
    public static function create(string $type, array $config = []): Field
    {
        return match ($type) {
            'text', 'email', 'url', 'password' => new TextField(array_merge($config, ['type' => $type])),
            'number' => new TextField(array_merge($config, ['type' => 'number'])),
            'textarea' => new TextareaField($config),
            'select' => (new SelectField($config))->options($config['options'] ?? []),
            'checkbox' => new CheckboxField($config),
            default => new TextField($config),
        };
    }
    
    /**
     * Create multiple fields from a schema
     * 
     * @param array $schema ['field_name' => ['type' => 'text', 'label' => 'Field', ...]]
     * @param array $values ['field_name' => 'value']
     * @param string $namePrefix Prefix for field names (e.g., 'items[0]')
     * @return Field[]
     */
    public static function fromSchema(array $schema, array $values = [], string $namePrefix = ''): array
    {
        $fields = [];
        
        foreach ($schema as $name => $config) {
            $type = $config['type'] ?? 'text';
            $fieldConfig = array_merge($config, [
                'name' => $namePrefix ? "{$namePrefix}[{$name}]" : $name,
                'value' => $values[$name] ?? ($config['default'] ?? ''),
            ]);
            
            // Handle checkbox 'checked' state
            if ($type === 'checkbox') {
                $fieldConfig['checked'] = !empty($values[$name]);
            }
            
            $fields[$name] = self::create($type, $fieldConfig);
        }
        
        return $fields;
    }
    
    /**
     * Render all fields from schema
     */
    public static function renderFromSchema(array $schema, array $values = [], string $namePrefix = ''): string
    {
        $fields = self::fromSchema($schema, $values, $namePrefix);
        
        $html = '';
        foreach ($fields as $field) {
            $html .= $field->render();
        }
        
        return $html;
    }
}

