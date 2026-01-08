<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Core\Response;
use Ghidar\Logging\Logger;

/**
 * Request Security Middleware
 * Provides rate limiting, IP throttling, and request validation
 */
class RequestSecurityMiddleware
{
    /**
     * Apply security checks to a request.
     *
     * @param int $userId User ID
     * @param string $endpoint Endpoint identifier
     * @param array<string, int> $rateLimitConfig Rate limit configuration ['limit' => int, 'period' => int]
     * @return bool True if request is allowed, false otherwise
     */
    public static function checkRequest(
        int $userId,
        string $endpoint,
        array $rateLimitConfig = ['limit' => 100, 'period' => 3600]
    ): bool {
        // Rate limiting
        if (!RateLimiter::checkAndIncrement(
            $userId,
            $endpoint,
            $rateLimitConfig['limit'],
            $rateLimitConfig['period']
        )) {
            return false;
        }

        // IP-based throttling
        if (!self::checkIpThrottle()) {
            return false;
        }

        return true;
    }

    /**
     * Check IP-based request throttling.
     *
     * @return bool True if request is allowed
     */
    public static function checkIpThrottle(): bool
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if ($ipAddress === 'unknown') {
            return true; // Allow if IP cannot be determined
        }

        // Check IP rate limit (global, not per user)
        $limit = 1000; // Max requests per hour per IP
        $period = 3600;

        if (!RateLimiter::checkAndIncrement(0, 'ip_' . $ipAddress, $limit, $period)) {
            Logger::warning('ip_throttle_exceeded', [
                'ip_address' => $ipAddress,
                'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);
            return false;
        }

        // Check for suspicious IP patterns
        if (self::isSuspiciousIp($ipAddress)) {
            Logger::warning('suspicious_ip_detected', [
                'ip_address' => $ipAddress
            ]);
            // Don't block, but log for monitoring
        }

        return true;
    }

    /**
     * Validate request signature (for webhooks and secure endpoints).
     *
     * @param string $payload Request payload
     * @param string|null $signature Request signature from header
     * @param string $secret Secret key for signature verification
     * @return bool True if signature is valid
     */
    public static function validateRequestSignature(
        string $payload,
        ?string $signature,
        string $secret
    ): bool {
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Sanitize and validate input data.
     *
     * @param array<string, mixed> $data Input data
     * @param array<string, string> $rules Validation rules
     * @return array<string, mixed> Sanitized data
     * @throws \InvalidArgumentException If validation fails
     */
    public static function sanitizeAndValidate(
        array $data,
        array $rules
    ): array {
        $sanitized = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            // Check required
            if (strpos($rule, 'required') !== false && $value === null) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }

            if ($value === null) {
                continue;
            }

            // Sanitize based on type
            if (strpos($rule, 'string') !== false) {
                $value = filter_var($value, FILTER_SANITIZE_STRING);
                if (strpos($rule, 'max:') !== false) {
                    preg_match('/max:(\d+)/', $rule, $matches);
                    $maxLength = (int) ($matches[1] ?? 0);
                    if ($maxLength > 0 && strlen($value) > $maxLength) {
                        throw new \InvalidArgumentException("Field '{$field}' exceeds maximum length");
                    }
                }
            } elseif (strpos($rule, 'int') !== false) {
                $value = filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false) {
                    throw new \InvalidArgumentException("Field '{$field}' must be an integer");
                }
            } elseif (strpos($rule, 'email') !== false) {
                $value = filter_var($value, FILTER_SANITIZE_EMAIL);
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException("Field '{$field}' must be a valid email");
                }
            } elseif (strpos($rule, 'url') !== false) {
                $value = filter_var($value, FILTER_SANITIZE_URL);
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException("Field '{$field}' must be a valid URL");
                }
            }

            $sanitized[$field] = $value;
        }

        return $sanitized;
    }

    /**
     * Check if IP address is suspicious.
     *
     * @param string $ipAddress IP address
     * @return bool True if suspicious
     */
    private static function isSuspiciousIp(string $ipAddress): bool
    {
        // Check for known VPN/proxy ranges (simplified check)
        // In production, use a proper IP reputation service
        $suspiciousPatterns = [
            '/^10\./',  // Private network
            '/^192\.168\./',  // Private network
            '/^172\.(1[6-9]|2[0-9]|3[0-1])\./'  // Private network
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $ipAddress)) {
                // Private IPs are not necessarily suspicious, but log for monitoring
                return false;
            }
        }

        return false;
    }

    /**
     * Get CORS headers for API responses.
     *
     * @return array<string, string> CORS headers
     */
    public static function getCorsHeaders(): array
    {
        $allowedOrigins = \Ghidar\Config\Config::get('CORS_ALLOWED_ORIGINS', '*');
        $allowedMethods = \Ghidar\Config\Config::get('CORS_ALLOWED_METHODS', 'GET, POST, PUT, DELETE, OPTIONS');
        $allowedHeaders = \Ghidar\Config\Config::get('CORS_ALLOWED_HEADERS', 'Content-Type, Authorization, X-Ghidar-Signature');

        return [
            'Access-Control-Allow-Origin' => $allowedOrigins,
            'Access-Control-Allow-Methods' => $allowedMethods,
            'Access-Control-Allow-Headers' => $allowedHeaders,
            'Access-Control-Max-Age' => '3600'
        ];
    }

    /**
     * Apply CORS headers to response.
     */
    public static function applyCorsHeaders(): void
    {
        $headers = self::getCorsHeaders();
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Handle preflight OPTIONS request.
     */
    public static function handlePreflight(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::applyCorsHeaders();
            http_response_code(200);
            exit;
        }
    }
}

