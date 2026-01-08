<?php

declare(strict_types=1);

namespace Ghidar\Config;

/**
 * Configuration Validator
 * Validates all required environment variables on application startup.
 * Fails fast with clear error messages for missing or invalid configuration.
 */
class ConfigValidator
{
    /**
     * Required environment variables and their descriptions.
     */
    private const REQUIRED_VARS = [
        'DB_DATABASE' => 'Database name',
        'DB_USERNAME' => 'Database username',
        'TELEGRAM_BOT_TOKEN' => 'Telegram bot token from @BotFather',
    ];
    
    /**
     * Required environment variables for production only.
     */
    private const PRODUCTION_REQUIRED_VARS = [
        'VERIFICATION_ENCRYPTION_KEY' => 'Encryption key for verification data (32 bytes hex)',
        'COMPLIANCE_ENCRYPTION_KEY' => 'Encryption key for compliance data (32 bytes hex)',
        'PAYMENTS_CALLBACK_TOKEN' => 'Token for payment callbacks',
    ];
    
    /**
     * Variables that should not be default/test values in production.
     */
    private const SECURITY_SENSITIVE_VARS = [
        'VERIFICATION_ENCRYPTION_KEY',
        'COMPLIANCE_ENCRYPTION_KEY',
        'PAYMENTS_CALLBACK_TOKEN',
        'ADMIN_API_TOKEN',
    ];
    
    /**
     * Known test/default values that should not be used in production.
     */
    private const FORBIDDEN_TEST_VALUES = [
        '00000000000000000000000000000000',
        '1234567890abcdef1234567890abcdef',
        'test_key',
        'test',
        'development',
        'change_me',
        'your_secret_key_here',
    ];
    
    /**
     * Validation errors collected during validation.
     * @var array<string>
     */
    private array $errors = [];
    
    /**
     * Validation warnings (non-fatal).
     * @var array<string>
     */
    private array $warnings = [];
    
    /**
     * Validate all configuration.
     * 
     * @param bool $strict If true, throw exception on first error
     * @return bool True if all validations pass
     * @throws \RuntimeException If validation fails in strict mode
     */
    public function validate(bool $strict = true): bool
    {
        $this->errors = [];
        $this->warnings = [];
        
        $isProduction = $this->isProduction();
        
        // 1. Check required variables
        $this->validateRequiredVars(self::REQUIRED_VARS);
        
        // 2. Check production-only required variables
        if ($isProduction) {
            $this->validateRequiredVars(self::PRODUCTION_REQUIRED_VARS, true);
            
            // 3. Check for test/default values in sensitive variables
            $this->validateNoTestValues();
            
            // 4. Validate APP_ENV is set to production
            $this->validateProductionEnv();
        }
        
        // 5. Validate encryption keys format
        $this->validateEncryptionKeys();
        
        // 6. Validate Telegram bot token format
        $this->validateTelegramToken();
        
        // 7. Validate database configuration
        $this->validateDatabaseConfig();
        
        // Report results
        if ($strict && !empty($this->errors)) {
            throw new \RuntimeException(
                "Configuration validation failed:\n" . 
                implode("\n", array_map(fn($e) => "  - $e", $this->errors))
            );
        }
        
        // Log warnings
        foreach ($this->warnings as $warning) {
            error_log("[ConfigValidator] WARNING: $warning");
        }
        
        return empty($this->errors);
    }
    
    /**
     * Check if we're running in production.
     */
    private function isProduction(): bool
    {
        $env = Config::get('APP_ENV', 'local');
        return in_array(strtolower($env), ['production', 'prod', 'live'], true);
    }
    
    /**
     * Validate required variables exist and are non-empty.
     */
    private function validateRequiredVars(array $vars, bool $productionOnly = false): void
    {
        foreach ($vars as $key => $description) {
            $value = Config::get($key);
            
            if ($value === null || $value === '') {
                $prefix = $productionOnly ? '[PRODUCTION] ' : '';
                $this->errors[] = "{$prefix}Missing required configuration: {$key} ({$description})";
            }
        }
    }
    
    /**
     * Validate that sensitive variables don't contain test values in production.
     */
    private function validateNoTestValues(): void
    {
        foreach (self::SECURITY_SENSITIVE_VARS as $key) {
            $value = Config::get($key);
            
            if ($value === null) {
                continue;
            }
            
            $normalizedValue = strtolower(trim($value));
            
            foreach (self::FORBIDDEN_TEST_VALUES as $forbiddenValue) {
                if ($normalizedValue === strtolower($forbiddenValue)) {
                    $this->errors[] = "SECURITY: {$key} contains a test/default value. Generate a secure random value for production.";
                    break;
                }
            }
            
            // Check if value is too short
            if (strlen($value) < 16) {
                $this->warnings[] = "{$key} is unusually short (less than 16 characters). Ensure this is a secure value.";
            }
        }
    }
    
    /**
     * Validate APP_ENV is production in production environment.
     */
    private function validateProductionEnv(): void
    {
        $appEnv = Config::get('APP_ENV');
        
        if ($appEnv === null || strtolower($appEnv) === 'local') {
            $this->errors[] = "APP_ENV should be 'production' in production environment, got: " . ($appEnv ?? 'null');
        }
    }
    
    /**
     * Validate encryption keys format.
     */
    private function validateEncryptionKeys(): void
    {
        $keys = [
            'VERIFICATION_ENCRYPTION_KEY',
            'COMPLIANCE_ENCRYPTION_KEY',
        ];
        
        foreach ($keys as $key) {
            $value = Config::get($key);
            
            if ($value === null) {
                continue;
            }
            
            // Encryption keys should be hex-encoded (64 characters for 32 bytes)
            if (!ctype_xdigit($value) || strlen($value) !== 64) {
                if (strlen($value) < 32) {
                    $this->warnings[] = "{$key} is not a 64-character hex string. Key derivation will be used.";
                }
            }
        }
    }
    
    /**
     * Validate Telegram bot token format.
     */
    private function validateTelegramToken(): void
    {
        $token = Config::get('TELEGRAM_BOT_TOKEN');
        
        if ($token === null) {
            return;
        }
        
        // Telegram bot tokens have format: 123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
        if (!preg_match('/^\d+:[A-Za-z0-9_-]{35}$/', $token)) {
            $this->warnings[] = "TELEGRAM_BOT_TOKEN may be invalid. Expected format: 123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11";
        }
    }
    
    /**
     * Validate database configuration.
     */
    private function validateDatabaseConfig(): void
    {
        $dbHost = Config::get('DB_HOST', 'localhost');
        $dbPort = Config::getInt('DB_PORT', 3306);
        
        // Validate port range
        if ($dbPort < 1 || $dbPort > 65535) {
            $this->errors[] = "DB_PORT must be between 1 and 65535, got: {$dbPort}";
        }
        
        // Warn if using default password in non-local environment
        $dbPassword = Config::get('DB_PASSWORD');
        $appEnv = Config::get('APP_ENV', 'local');
        
        if ($appEnv !== 'local' && ($dbPassword === null || $dbPassword === '' || $dbPassword === 'root')) {
            $this->warnings[] = "DB_PASSWORD appears to be empty or default. Use a strong password in non-local environments.";
        }
    }
    
    /**
     * Get validation errors.
     * 
     * @return array<string> List of error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get validation warnings.
     * 
     * @return array<string> List of warning messages
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    /**
     * Quick validation helper - validate and return boolean.
     */
    public static function isValid(): bool
    {
        $validator = new self();
        return $validator->validate(false);
    }
    
    /**
     * Validate on bootstrap - throws exception if invalid.
     */
    public static function validateOnStartup(): void
    {
        $validator = new self();
        $validator->validate(true);
    }
}

