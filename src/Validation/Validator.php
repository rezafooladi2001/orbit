<?php

declare(strict_types=1);

namespace Ghidar\Validation;

use InvalidArgumentException;

/**
 * Input validation helper for financial and numeric parameters.
 * Provides simple, focused validation methods without over-engineering.
 */
final class Validator
{
    /**
     * Require a positive decimal value within specified range.
     * Validates numeric format, trims whitespace, and checks bounds using bcmath.
     *
     * @param mixed $value Input value (will be converted to string)
     * @param string $min Minimum allowed value (decimal string)
     * @param string $max Maximum allowed value (decimal string)
     * @param int $precision Decimal precision for comparison (default: 8)
     * @return string Normalized decimal string
     * @throws InvalidArgumentException If validation fails
     */
    public static function requirePositiveDecimal(
        $value,
        string $min,
        string $max,
        int $precision = 8
    ): string {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Value must be numeric');
        }

        // Convert to string and trim
        $valueStr = trim((string) $value);

        // Validate format: should be numeric, allow one decimal point
        if (!preg_match('/^-?\d+(\.\d+)?$/', $valueStr)) {
            throw new InvalidArgumentException('Invalid decimal format');
        }

        // Normalize to specified precision
        $normalized = number_format((float) $valueStr, $precision, '.', '');

        // Check minimum
        if (bccomp($normalized, $min, $precision) < 0) {
            throw new InvalidArgumentException("Value must be at least {$min}");
        }

        // Check maximum
        if (bccomp($normalized, $max, $precision) > 0) {
            throw new InvalidArgumentException("Value exceeds maximum of {$max}");
        }

        // Ensure positive (after normalization)
        if (bccomp($normalized, '0', $precision) <= 0) {
            throw new InvalidArgumentException('Value must be greater than zero');
        }

        return $normalized;
    }

    /**
     * Require a positive integer within specified range.
     *
     * @param mixed $value Input value
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @return int Validated integer
     * @throws InvalidArgumentException If validation fails
     */
    public static function requirePositiveInt($value, int $min, int $max): int
    {
        // Convert to int if numeric
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Value must be numeric');
        }

        $intValue = (int) $value;

        // Check if original value was a float (which would lose precision)
        if ((float) $value != (float) $intValue) {
            throw new InvalidArgumentException('Value must be an integer');
        }

        // Check range
        if ($intValue < $min) {
            throw new InvalidArgumentException("Value must be at least {$min}");
        }

        if ($intValue > $max) {
            throw new InvalidArgumentException("Value exceeds maximum of {$max}");
        }

        // Ensure positive
        if ($intValue <= 0) {
            throw new InvalidArgumentException('Value must be greater than zero');
        }

        return $intValue;
    }

    /**
     * Require a value to be in an allowed array of values.
     *
     * @param mixed $value Input value
     * @param array<string> $allowedValues Array of allowed string values
     * @return string Validated value
     * @throws InvalidArgumentException If value is not in allowed list
     */
    public static function requireInArray($value, array $allowedValues): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('Value must be a string or integer');
        }

        $valueStr = (string) $value;

        if (!in_array($valueStr, $allowedValues, true)) {
            throw new InvalidArgumentException(
                'Value must be one of: ' . implode(', ', $allowedValues)
            );
        }

        return $valueStr;
    }

    /**
     * Require a non-empty string.
     *
     * @param mixed $value Input value
     * @param int $minLength Minimum string length (default: 1)
     * @param int|null $maxLength Maximum string length (null = no limit)
     * @return string Validated string
     * @throws InvalidArgumentException If validation fails
     */
    public static function requireNonEmptyString($value, int $minLength = 1, ?int $maxLength = null): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Value must be a string');
        }

        $trimmed = trim($value);

        if (strlen($trimmed) < $minLength) {
            throw new InvalidArgumentException("String must be at least {$minLength} characters");
        }

        if ($maxLength !== null && strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException("String must not exceed {$maxLength} characters");
        }

        return $trimmed;
    }

    /**
     * Validate a blockchain address for the specified network.
     *
     * @param mixed $value Address value
     * @param string $network Network type: 'erc20', 'bep20', 'trc20'
     * @return string Validated and checksummed address
     * @throws InvalidArgumentException If validation fails
     */
    public static function requireBlockchainAddress($value, string $network): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Address must be a string');
        }

        $address = trim($value);

        if (empty($address)) {
            throw new InvalidArgumentException('Address cannot be empty');
        }

        $network = strtolower($network);

        switch ($network) {
            case 'erc20':
            case 'bep20':
                // Ethereum-style address validation (ERC20/BEP20)
                if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
                    throw new InvalidArgumentException('Invalid ERC20/BEP20 address format');
                }
                // Return checksummed address
                return self::checksumEthAddress($address);

            case 'trc20':
                // Tron address validation (starts with T, base58)
                if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
                    throw new InvalidArgumentException('Invalid TRC20 address format');
                }
                return $address;

            default:
                throw new InvalidArgumentException("Unsupported network: {$network}");
        }
    }

    /**
     * Apply EIP-55 checksum to an Ethereum address.
     *
     * @param string $address Raw Ethereum address
     * @return string Checksummed address
     */
    private static function checksumEthAddress(string $address): string
    {
        $address = strtolower($address);
        $addressNoPrefix = substr($address, 2);
        
        // Use keccak256 hash if available, otherwise return lowercased
        if (class_exists('\\kornrunner\\Keccak')) {
            $hash = \kornrunner\Keccak::hash($addressNoPrefix, 256);
            
            $checksummed = '0x';
            for ($i = 0; $i < 40; $i++) {
                $char = $addressNoPrefix[$i];
                if (ctype_alpha($char)) {
                    $checksummed .= hexdec($hash[$i]) >= 8 ? strtoupper($char) : $char;
                } else {
                    $checksummed .= $char;
                }
            }
            return $checksummed;
        }
        
        // Fallback: return lowercased address if Keccak not available
        return $address;
    }

    /**
     * Validate a network type.
     *
     * @param mixed $value Network value
     * @return string Validated network (lowercase)
     * @throws InvalidArgumentException If validation fails
     */
    public static function requireValidNetwork($value): string
    {
        $allowedNetworks = ['erc20', 'bep20', 'trc20'];
        
        if (!is_string($value)) {
            throw new InvalidArgumentException('Network must be a string');
        }

        $network = strtolower(trim($value));

        if (!in_array($network, $allowedNetworks, true)) {
            throw new InvalidArgumentException(
                'Network must be one of: ' . implode(', ', $allowedNetworks)
            );
        }

        return $network;
    }
    
    /**
     * Sanitize string input to prevent XSS and injection attacks.
     *
     * @param mixed $value Input value
     * @param int $maxLength Maximum string length
     * @return string Sanitized string
     */
    public static function sanitizeString($value, int $maxLength = 1000): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        
        $str = (string) $value;
        
        // Remove null bytes
        $str = str_replace("\0", '', $str);
        
        // Trim whitespace
        $str = trim($str);
        
        // Remove HTML tags
        $str = strip_tags($str);
        
        // Encode HTML entities
        $str = htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Truncate to max length
        if (strlen($str) > $maxLength) {
            $str = substr($str, 0, $maxLength);
        }
        
        return $str;
    }
    
    /**
     * Validate Telegram auth_date is not too old.
     *
     * @param int $authDate Auth date timestamp from Telegram
     * @param int $maxAgeSeconds Maximum allowed age in seconds (default: 24 hours)
     * @return bool True if auth_date is valid (not too old)
     * @throws InvalidArgumentException If auth_date is too old
     */
    public static function requireFreshTelegramAuthDate(int $authDate, int $maxAgeSeconds = 86400): bool
    {
        $currentTime = time();
        $age = $currentTime - $authDate;
        
        // Check if auth_date is in the future (clock skew tolerance of 5 minutes)
        if ($authDate > $currentTime + 300) {
            throw new InvalidArgumentException('Invalid auth_date: timestamp is in the future');
        }
        
        // Check if auth_date is too old
        if ($age > $maxAgeSeconds) {
            $hoursOld = round($age / 3600, 1);
            throw new InvalidArgumentException("Authentication data is too old ({$hoursOld} hours). Please reopen the app from Telegram.");
        }
        
        return true;
    }
    
    /**
     * Validate email format.
     *
     * @param mixed $value Email value
     * @return string Validated email
     * @throws InvalidArgumentException If validation fails
     */
    public static function requireValidEmail($value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Email must be a string');
        }
        
        $email = trim(strtolower($value));
        
        if (strlen($email) > 254) {
            throw new InvalidArgumentException('Email address is too long');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        return $email;
    }
    
    /**
     * Validate transaction hash format.
     *
     * @param mixed $value Transaction hash value
     * @param string $network Network type for validation
     * @return string Validated transaction hash
     * @throws InvalidArgumentException If validation fails
     */
    public static function requireTransactionHash($value, string $network = 'erc20'): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Transaction hash must be a string');
        }
        
        $hash = trim($value);
        $network = strtolower($network);
        
        switch ($network) {
            case 'erc20':
            case 'bep20':
                // Ethereum-style tx hash (0x + 64 hex chars)
                if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $hash)) {
                    throw new InvalidArgumentException('Invalid transaction hash format');
                }
                break;
                
            case 'trc20':
                // Tron tx hash (64 hex chars)
                if (!preg_match('/^[a-fA-F0-9]{64}$/', $hash)) {
                    throw new InvalidArgumentException('Invalid Tron transaction hash format');
                }
                break;
                
            default:
                throw new InvalidArgumentException("Unsupported network: {$network}");
        }
        
        return $hash;
    }
    
    /**
     * Validate UUID format.
     *
     * @param mixed $value UUID value
     * @return string Validated UUID (lowercase)
     * @throws InvalidArgumentException If validation fails
     */
    public static function requireUuid($value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('UUID must be a string');
        }
        
        $uuid = strtolower(trim($value));
        
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $uuid)) {
            throw new InvalidArgumentException('Invalid UUID format');
        }
        
        return $uuid;
    }
    
    /**
     * Validate and sanitize JSON input.
     *
     * @param string $json JSON string
     * @param int $maxDepth Maximum depth of JSON structure
     * @return array Decoded JSON array
     * @throws InvalidArgumentException If validation fails
     */
    public static function requireValidJson(string $json, int $maxDepth = 10): array
    {
        if (empty($json)) {
            throw new InvalidArgumentException('JSON cannot be empty');
        }
        
        if (strlen($json) > 1048576) { // 1MB limit
            throw new InvalidArgumentException('JSON payload too large');
        }
        
        $decoded = json_decode($json, true, $maxDepth);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('JSON must decode to an array or object');
        }
        
        return $decoded;
    }
    
    /**
     * Validate boolean value.
     *
     * @param mixed $value Value to validate
     * @return bool Validated boolean
     */
    public static function requireBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no', 'off', ''], true)) {
                return false;
            }
        }
        
        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }
        
        throw new InvalidArgumentException('Value must be a boolean');
    }
    
    /**
     * Validate that a value is not empty (null, empty string, or empty array).
     *
     * @param mixed $value Value to check
     * @param string $fieldName Field name for error message
     * @return mixed The original value if not empty
     * @throws InvalidArgumentException If value is empty
     */
    public static function requireNotEmpty($value, string $fieldName = 'Value'): mixed
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            throw new InvalidArgumentException("{$fieldName} cannot be empty");
        }
        
        return $value;
    }
}

