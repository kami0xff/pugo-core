<?php
/**
 * Pugo Core 3.0 - Input Validator
 * 
 * Validates form inputs against field definitions.
 */

namespace Pugo\Validation;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $validated = [];
    
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }
    
    /**
     * Run validation
     */
    public function validate(): bool
    {
        $this->errors = [];
        $this->validated = [];
        
        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->getValue($field);
            $rules = $this->parseRules($fieldRules);
            
            foreach ($rules as $rule => $params) {
                if (!$this->validateRule($field, $value, $rule, $params)) {
                    break; // Stop on first error for this field
                }
            }
            
            // Store validated value
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get first error for a field
     */
    public function error(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
    
    /**
     * Get all validated data
     */
    public function validated(): array
    {
        return $this->validated;
    }
    
    /**
     * Check if field has error
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
    
    /**
     * Get value from data (supports dot notation)
     */
    protected function getValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        
        return $value;
    }
    
    /**
     * Parse rules from string or array
     */
    protected function parseRules(array|string $rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        $parsed = [];
        
        foreach ($rules as $key => $rule) {
            if (is_numeric($key)) {
                // String rule like "required" or "max:255"
                if (str_contains($rule, ':')) {
                    [$name, $param] = explode(':', $rule, 2);
                    $parsed[$name] = $param;
                } else {
                    $parsed[$rule] = true;
                }
            } else {
                // Array rule like ['required' => true]
                $parsed[$key] = $rule;
            }
        }
        
        return $parsed;
    }
    
    /**
     * Validate a single rule
     */
    protected function validateRule(string $field, mixed $value, string $rule, mixed $params): bool
    {
        $method = 'validate' . ucfirst($rule);
        
        if (method_exists($this, $method)) {
            return $this->$method($field, $value, $params);
        }
        
        // Unknown rule - skip
        return true;
    }
    
    /**
     * Add an error
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    // ========== Validation Rules ==========
    
    protected function validateRequired(string $field, mixed $value, mixed $params): bool
    {
        if ($params === false) {
            return true;
        }
        
        if ($value === null || $value === '' || $value === []) {
            $this->addError($field, "The {$field} field is required.");
            return false;
        }
        
        return true;
    }
    
    protected function validateString(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null) return true;
        
        if (!is_string($value)) {
            $this->addError($field, "The {$field} must be a string.");
            return false;
        }
        
        return true;
    }
    
    protected function validateNumeric(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        if (!is_numeric($value)) {
            $this->addError($field, "The {$field} must be a number.");
            return false;
        }
        
        return true;
    }
    
    protected function validateEmail(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "The {$field} must be a valid email address.");
            return false;
        }
        
        return true;
    }
    
    protected function validateUrl(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "The {$field} must be a valid URL.");
            return false;
        }
        
        return true;
    }
    
    protected function validateMin(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        $min = (int) $params;
        
        if (is_string($value) && strlen($value) < $min) {
            $this->addError($field, "The {$field} must be at least {$min} characters.");
            return false;
        }
        
        if (is_numeric($value) && $value < $min) {
            $this->addError($field, "The {$field} must be at least {$min}.");
            return false;
        }
        
        return true;
    }
    
    protected function validateMax(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        $max = (int) $params;
        
        if (is_string($value) && strlen($value) > $max) {
            $this->addError($field, "The {$field} must not exceed {$max} characters.");
            return false;
        }
        
        if (is_numeric($value) && $value > $max) {
            $this->addError($field, "The {$field} must not exceed {$max}.");
            return false;
        }
        
        return true;
    }
    
    protected function validateIn(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        $options = is_array($params) ? $params : explode(',', $params);
        
        if (!in_array($value, $options)) {
            $optionsStr = implode(', ', $options);
            $this->addError($field, "The {$field} must be one of: {$optionsStr}.");
            return false;
        }
        
        return true;
    }
    
    protected function validateRegex(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        if (!preg_match($params, $value)) {
            $this->addError($field, "The {$field} format is invalid.");
            return false;
        }
        
        return true;
    }
    
    protected function validateDate(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        $format = $params === true ? 'Y-m-d' : $params;
        $date = \DateTime::createFromFormat($format, $value);
        
        if (!$date || $date->format($format) !== $value) {
            $this->addError($field, "The {$field} must be a valid date.");
            return false;
        }
        
        return true;
    }
    
    protected function validateBoolean(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null) return true;
        
        $valid = [true, false, 0, 1, '0', '1', 'true', 'false'];
        
        if (!in_array($value, $valid, true)) {
            $this->addError($field, "The {$field} must be true or false.");
            return false;
        }
        
        return true;
    }
    
    protected function validateArray(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null) return true;
        
        if (!is_array($value)) {
            $this->addError($field, "The {$field} must be an array.");
            return false;
        }
        
        return true;
    }
    
    protected function validateSlug(string $field, mixed $value, mixed $params): bool
    {
        if ($value === null || $value === '') return true;
        
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $this->addError($field, "The {$field} must be a valid URL slug (lowercase letters, numbers, and hyphens only).");
            return false;
        }
        
        return true;
    }
}

/**
 * Global helper
 */
function pugo_validate(array $data, array $rules): Validator
{
    $validator = new Validator($data, $rules);
    $validator->validate();
    return $validator;
}

