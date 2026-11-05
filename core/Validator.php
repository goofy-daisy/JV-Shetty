<?php
/**
 * Input Validation Class
 * Handles all input validation and sanitization
 */

class Validator {
    private array $errors = [];
    private array $data = [];
    
    /**
     * Validate data against rules
     */
    public function validate(array $data, array $rules): bool {
        $this->data = $data;
        $this->errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            
            foreach ($rulesList as $rule) {
                $this->applyRule($field, $value, trim($rule));
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply a single validation rule
     */
    private function applyRule(string $field, $value, string $rule): void {
        // Parse rule with parameters
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->errors[$field][] = "{$field} is required";
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "{$field} must be a valid email address";
                }
                break;
                
            case 'campus_email':
                if (!empty($value) && !str_ends_with(strtolower($value), '@goa.bits-pilani.ac.in')) {
                    $this->errors[$field][] = "Only BITS Goa campus emails (@goa.bits-pilani.ac.in) are allowed";
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < (int)$param) {
                    $this->errors[$field][] = "{$field} must be at least {$param} characters";
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > (int)$param) {
                    $this->errors[$field][] = "{$field} must not exceed {$param} characters";
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->errors[$field][] = "{$field} must be numeric";
                }
                break;
                
            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->errors[$field][] = "{$field} must be an integer";
                }
                break;
                
            case 'positive':
                if (!empty($value) && (!is_numeric($value) || (float)$value <= 0)) {
                    $this->errors[$field][] = "{$field} must be a positive number";
                }
                break;
                
            case 'phone':
                if (!empty($value) && !preg_match('/^[0-9]{10}$/', preg_replace('/[^0-9]/', '', $value))) {
                    $this->errors[$field][] = "{$field} must be a valid 10-digit phone number";
                }
                break;
                
            case 'password':
                if (!empty($value)) {
                    if (strlen($value) < 8) {
                        $this->errors[$field][] = "{$field} must be at least 8 characters";
                    }
                    if (!preg_match('/[A-Z]/', $value)) {
                        $this->errors[$field][] = "{$field} must contain at least one uppercase letter";
                    }
                    if (!preg_match('/[a-z]/', $value)) {
                        $this->errors[$field][] = "{$field} must contain at least one lowercase letter";
                    }
                    if (!preg_match('/[0-9]/', $value)) {
                        $this->errors[$field][] = "{$field} must contain at least one number";
                    }
                }
                break;
                
            case 'match':
                if ($value !== ($this->data[$param] ?? null)) {
                    $this->errors[$field][] = "{$field} must match {$param}";
                }
                break;
                
            case 'in':
                $allowed = explode(',', $param);
                if (!empty($value) && !in_array($value, $allowed)) {
                    $this->errors[$field][] = "{$field} must be one of: " . implode(', ', $allowed);
                }
                break;
                
            case 'date':
                if (!empty($value)) {
                    $d = DateTime::createFromFormat('Y-m-d', $value);
                    if (!$d || $d->format('Y-m-d') !== $value) {
                        $this->errors[$field][] = "{$field} must be a valid date (YYYY-MM-DD)";
                    }
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->errors[$field][] = "{$field} must be a valid URL";
                }
                break;
        }
    }
    
    /**
     * Get validation errors
     */
    public function errors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error message
     */
    public function firstError(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        $first = reset($this->errors);
        return is_array($first) ? reset($first) : $first;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitize(array $data): array {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCsrf(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCsrf(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
