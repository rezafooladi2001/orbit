<?php

declare(strict_types=1);

namespace Ghidar\Security;

/**
 * CSRF Protection Class
 * Generates and validates CSRF tokens for form submissions.
 */
class CSRFProtection
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LIFETIME = 3600; // 1 hour

    /**
     * Start session if not already started.
     * Configures secure session settings for production.
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configure secure session settings before starting
            $isProduction = (\Ghidar\Config\Config::get('APP_ENV', 'local') === 'production');
            
            // Set secure cookie parameters
            session_set_cookie_params([
                'lifetime' => 0, // Session cookie (expires when browser closes)
                'path' => '/',
                'domain' => '',
                'secure' => $isProduction, // Only send over HTTPS in production
                'httponly' => true, // Prevent JavaScript access
                'samesite' => 'Lax' // CSRF protection
            ]);
            
            // Set session name
            session_name('GHIDAR_SESS');
            
            session_start();
            
            // Regenerate session ID periodically to prevent fixation attacks
            if (!isset($_SESSION['_created'])) {
                $_SESSION['_created'] = time();
            } elseif (time() - $_SESSION['_created'] > 1800) {
                // Regenerate every 30 minutes
                session_regenerate_id(true);
                $_SESSION['_created'] = time();
            }
        }
    }

    /**
     * Generate or retrieve CSRF token for current session.
     *
     * @return string CSRF token
     */
    public static function getToken(): string
    {
        self::ensureSession();

        // Check if token exists and is still valid
        if (isset($_SESSION[self::TOKEN_NAME])) {
            $tokenData = $_SESSION[self::TOKEN_NAME];
            if (isset($tokenData['expires_at']) && $tokenData['expires_at'] > time()) {
                return $tokenData['token'];
            }
        }

        // Generate new token
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_NAME] = [
            'token' => $token,
            'expires_at' => time() + self::TOKEN_LIFETIME,
            'created_at' => time()
        ];

        return $token;
    }

    /**
     * Validate CSRF token from request.
     *
     * @param string|null $token Token to validate (if null, reads from POST/GET/header/JSON)
     * @return bool True if valid, false otherwise
     */
    public static function validateToken(?string $token = null): bool
    {
        self::ensureSession();

        // Get token from parameter or request
        if ($token === null) {
            // Try POST first (form data)
            $token = $_POST[self::TOKEN_NAME] ?? null;
            
            // Try GET (for legacy support)
            if ($token === null) {
                $token = $_GET[self::TOKEN_NAME] ?? null;
            }
            
            // Try header (X-CSRF-Token becomes HTTP_X_CSRF_TOKEN)
            if ($token === null) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            }
            
            // Try JSON body (for JSON requests)
            // Note: php://input can only be read once, so we check global cache first
            if ($token === null && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                if (strpos($contentType, 'application/json') !== false) {
                    // Check if input was already cached by the calling script
                    $jsonInput = $GLOBALS['_CSRF_JSON_INPUT'] ?? null;
                    if ($jsonInput === null) {
                        $jsonInput = file_get_contents('php://input');
                        $GLOBALS['_CSRF_JSON_INPUT'] = $jsonInput; // Cache for later use
                    }
                    $jsonData = json_decode($jsonInput, true);
                    if (is_array($jsonData) && isset($jsonData[self::TOKEN_NAME])) {
                        $token = $jsonData[self::TOKEN_NAME];
                    }
                }
            }
        }

        if ($token === null || empty($token)) {
            return false;
        }

        // Check if session token exists
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        $sessionToken = $_SESSION[self::TOKEN_NAME];

        // Check if token expired
        if (isset($sessionToken['expires_at']) && $sessionToken['expires_at'] <= time()) {
            unset($_SESSION[self::TOKEN_NAME]);
            return false;
        }

        // Compare tokens using constant-time comparison
        return hash_equals($sessionToken['token'], $token);
    }

    /**
     * Require valid CSRF token or exit with error.
     * Use this in form handlers.
     *
     * @param string|null $token Optional token to validate
     * @throws void (exits on failure)
     */
    public static function requireToken(?string $token = null): void
    {
        if (!self::validateToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode([
                'error' => 'Invalid or missing CSRF token',
                'code' => 'CSRF_TOKEN_INVALID'
            ]));
        }
    }

    /**
     * Get token as hidden input field HTML.
     *
     * @return string HTML input field
     */
    public static function getTokenField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="' . htmlspecialchars(self::TOKEN_NAME, ENT_QUOTES) . 
               '" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
    }

    /**
     * Get token for AJAX requests (to include in headers).
     *
     * @return string CSRF token
     */
    public static function getTokenForAjax(): string
    {
        return self::getToken();
    }

    /**
     * Regenerate token (useful after successful form submission).
     */
    public static function regenerateToken(): void
    {
        self::ensureSession();
        unset($_SESSION[self::TOKEN_NAME]);
        self::getToken(); // Generate new token
    }
}
