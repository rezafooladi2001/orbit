<?php

declare(strict_types=1);

namespace Ghidar\Http;

use Ghidar\Config\Config;
use Ghidar\Core\Response;

/**
 * HTTP Middleware for security headers, CORS, and request validation.
 * Call Middleware::init() at the beginning of each API endpoint.
 */
final class Middleware
{
    private static bool $initialized = false;

    /**
     * Initialize middleware - sets security headers and handles CORS.
     * Should be called at the beginning of each API endpoint.
     *
     * @param string $allowedMethod Allowed HTTP method (GET, POST, etc.)
     * @return bool True if request should continue, false if it was handled (OPTIONS)
     */
    public static function init(string $allowedMethod = 'POST'): bool
    {
        if (self::$initialized) {
            return true;
        }

        self::$initialized = true;

        // Set security headers
        self::setSecurityHeaders();

        // Handle CORS
        self::handleCors();

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Validate request method
        if ($_SERVER['REQUEST_METHOD'] !== $allowedMethod) {
            Response::jsonError('METHOD_NOT_ALLOWED', "Only {$allowedMethod} method is allowed", 405);
            exit;
        }

        return true;
    }

    /**
     * Set security-related HTTP headers.
     */
    private static function setSecurityHeaders(): void
    {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // NOTE: X-Frame-Options is NOT set for API endpoints because:
        // 1. APIs return JSON, not HTML (no clickjacking risk)
        // 2. The MiniApp runs inside Telegram's iframe and needs to call these APIs
        // Instead, we use CSP frame-ancestors for the HTML pages.

        // XSS Protection (legacy, but still useful for older browsers)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Cache control for API responses
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        // Content Security Policy for API - allow Telegram origins to embed
        header("Content-Security-Policy: frame-ancestors https://web.telegram.org https://*.telegram.org https://telegram.org 'self';");
    }

    /**
     * Telegram origins that are always allowed for Mini Apps.
     */
    private const TELEGRAM_ALLOWED_ORIGINS = [
        'https://web.telegram.org',
        'https://webk.telegram.org',
        'https://webz.telegram.org',
    ];

    /**
     * Handle CORS headers for cross-origin requests.
     * In production, only allows Telegram origins and explicitly configured origins.
     */
    private static function handleCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $env = Config::get('APP_ENV', 'local');

        // Always set allowed methods and headers
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, Origin, Telegram-Data, telegram-data, Telegram-Init-Data, X-PAYMENTS-CALLBACK-TOKEN');
        header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours

        // If no origin, it's likely a same-origin or server-to-server request
        if (empty($origin)) {
            // In production, don't set wildcard CORS for credentialed requests
            if ($env === 'production') {
                // No Access-Control-Allow-Origin header means same-origin only
                return;
            }
            // In development, allow all for easier testing
            header('Access-Control-Allow-Origin: *');
            return;
        }

        // Check if origin is allowed
        if (self::isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        } else {
            // Log rejected origin in production for security monitoring
            if ($env === 'production') {
                \Ghidar\Logging\Logger::warning('cors_origin_rejected', [
                    'origin' => $origin,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            }
            // Don't set CORS headers - browser will block the request
        }
    }

    /**
     * Check if an origin is allowed for CORS.
     *
     * @param string $origin The origin to check
     * @return bool True if origin is allowed
     */
    private static function isOriginAllowed(string $origin): bool
    {
        $env = Config::get('APP_ENV', 'local');

        // In development/testing, allow all origins for easier debugging
        if ($env !== 'production') {
            return true;
        }

        // Always allow Telegram origins (required for Mini App to work)
        foreach (self::TELEGRAM_ALLOWED_ORIGINS as $telegramOrigin) {
            if ($origin === $telegramOrigin || strpos($origin, $telegramOrigin) === 0) {
                return true;
            }
        }

        // Check against configured allowed origins
        $allowedOrigins = self::getAllowedOrigins();

        // If wildcard is configured, allow all (not recommended for production)
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Check subdomain patterns (e.g., *.example.com)
        foreach ($allowedOrigins as $allowedOrigin) {
            if (strpos($allowedOrigin, '*.') === 0) {
                $domain = substr($allowedOrigin, 2); // Remove *.
                if (preg_match('/^https?:\/\/[^\/]*' . preg_quote($domain, '/') . '$/i', $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get allowed CORS origins from configuration.
     *
     * @return array<string> List of allowed origins
     */
    private static function getAllowedOrigins(): array
    {
        $originsString = Config::get('CORS_ALLOWED_ORIGINS', '');
        
        // Empty string means only Telegram origins are allowed
        if ($originsString === '' || $originsString === null) {
            return [];
        }

        if ($originsString === '*') {
            return ['*'];
        }

        return array_filter(array_map('trim', explode(',', $originsString)));
    }

    /**
     * Require authentication and return user context.
     * Combines auth check with method validation.
     *
     * @param string $allowedMethod Allowed HTTP method
     * @return array{user: array, wallet: array} User context with user and wallet
     */
    public static function requireAuth(string $allowedMethod = 'POST'): array
    {
        self::init($allowedMethod);

        try {
            return \Ghidar\Core\UserContext::requireCurrentUserWithWallet();
        } catch (\RuntimeException $e) {
            $errorMessage = $e->getMessage();

            if (strpos($errorMessage, 'banned') !== false) {
                Response::jsonError('USER_BANNED', 'User is banned', 403);
            } elseif (strpos($errorMessage, 'Invalid') !== false || strpos($errorMessage, 'not found') !== false) {
                Response::jsonError('UNAUTHORIZED', 'Invalid authentication', 401);
            } else {
                Response::jsonError('AUTH_ERROR', 'Authentication failed', 401);
            }
            exit;
        }
    }

    /**
     * Parse JSON request body.
     *
     * @return array<string, mixed> Parsed JSON data
     */
    public static function parseJsonBody(): array
    {
        $input = file_get_contents('php://input');

        if ($input === false || $input === '') {
            Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
            exit;
        }

        $data = json_decode($input, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
            exit;
        }

        return is_array($data) ? $data : [];
    }
}

