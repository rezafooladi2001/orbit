<?php

declare(strict_types=1);

namespace Ghidar\Http\Middleware;

use Ghidar\Config\Config;

/**
 * HTTPS Middleware
 * Ensures secure HTTPS connections in production.
 * Adds security headers including HSTS.
 */
class HttpsMiddleware
{
    /**
     * HSTS max-age in seconds (1 year)
     */
    private const HSTS_MAX_AGE = 31536000;
    
    /**
     * Apply HTTPS redirect and security headers.
     * Should be called early in the request lifecycle.
     */
    public static function apply(): void
    {
        $isProduction = self::isProduction();
        $isSecure = self::isSecureConnection();
        
        // In production, redirect HTTP to HTTPS
        if ($isProduction && !$isSecure && !self::isCliRequest()) {
            self::redirectToHttps();
            return;
        }
        
        // Add security headers
        self::addSecurityHeaders($isProduction, $isSecure);
    }
    
    /**
     * Check if we're running in production.
     */
    private static function isProduction(): bool
    {
        $env = Config::get('APP_ENV', 'local');
        return in_array(strtolower($env), ['production', 'prod', 'live'], true);
    }
    
    /**
     * Check if the current connection is secure (HTTPS).
     */
    private static function isSecureConnection(): bool
    {
        // Check HTTPS flag
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        
        // Check forwarded proto (for load balancers/proxies)
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && 
            strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        
        // Check CloudFlare
        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $visitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (isset($visitor['scheme']) && $visitor['scheme'] === 'https') {
                return true;
            }
        }
        
        // Check port
        if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if this is a CLI request.
     */
    private static function isCliRequest(): bool
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'cli-server';
    }
    
    /**
     * Redirect to HTTPS.
     */
    private static function redirectToHttps(): void
    {
        // Don't redirect if this is an API call - return error instead
        $contentType = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(426); // Upgrade Required
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'HTTPS_REQUIRED',
                    'message' => 'This API requires HTTPS. Please use https:// protocol.'
                ]
            ]);
            exit;
        }
        
        // Redirect browser requests
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        if (!empty($host)) {
            $redirectUrl = 'https://' . $host . $uri;
            header('Location: ' . $redirectUrl, true, 301);
            exit;
        }
        
        // Fallback error
        http_response_code(426);
        echo 'HTTPS Required';
        exit;
    }
    
    /**
     * Add security headers.
     */
    private static function addSecurityHeaders(bool $isProduction, bool $isSecure): void
    {
        // HSTS - only on secure connections
        if ($isSecure) {
            $hstsValue = 'max-age=' . self::HSTS_MAX_AGE;
            if ($isProduction) {
                $hstsValue .= '; includeSubDomains; preload';
            }
            header('Strict-Transport-Security: ' . $hstsValue);
        }
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://telegram.org https://*.telegram.org; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https://*.telegram.org wss://*.telegram.org; frame-ancestors 'self' https://web.telegram.org https://webk.telegram.org https://webz.telegram.org;");
        
        // X-Frame-Options - allow Telegram to embed
        header('X-Frame-Options: ALLOW-FROM https://web.telegram.org');
        
        // Other security headers
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Require HTTPS for the current request.
     * Use this for sensitive API endpoints.
     * 
     * @throws \RuntimeException If not using HTTPS in production
     */
    public static function requireHttps(): void
    {
        if (!self::isProduction()) {
            return; // Don't enforce in development
        }
        
        if (!self::isSecureConnection()) {
            throw new \RuntimeException('HTTPS is required for this operation');
        }
    }
}

