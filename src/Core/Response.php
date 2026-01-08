<?php

declare(strict_types=1);

namespace Ghidar\Core;

use Ghidar\Config\Config;

/**
 * HTTP response helper for JSON API responses.
 * Includes gzip compression, ETags, and caching support.
 */
class Response
{
    /**
     * Enable gzip compression for responses larger than this size.
     */
    private const GZIP_MIN_SIZE = 1024;
    
    /**
     * Set CORS headers for API responses.
     */
    private static function setCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Allow Telegram WebApp origins and same-site requests
        $allowedOrigins = [
            'https://web.telegram.org',
            'https://webk.telegram.org',
            'https://webz.telegram.org',
            'https://ghidar.com',
            'https://www.ghidar.com',
        ];
        
        // Always set these headers
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Telegram-Data, Telegram-Init-Data, Accept, If-None-Match');
        header('Access-Control-Max-Age: 3600');
        header('Access-Control-Expose-Headers: ETag, X-Request-Id');
        
        if (in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        } elseif (!empty($origin)) {
            // For other origins with a valid Origin header, echo it back (permissive for now)
            // You can restrict this in production
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        } else {
            // No Origin header (same-origin request or direct access)
            // Don't set Access-Control-Allow-Origin to avoid issues
        }
    }
    
    /**
     * Set common response headers.
     */
    private static function setCommonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        
        // Add request ID for tracing
        $requestId = bin2hex(random_bytes(8));
        header("X-Request-Id: $requestId");
    }
    
    /**
     * Set caching headers.
     * 
     * @param string $cacheControl Cache-Control header value
     * @param int|null $maxAge Max age in seconds (for public caching)
     */
    private static function setCacheHeaders(string $cacheControl = 'no-cache, no-store, must-revalidate', ?int $maxAge = null): void
    {
        if ($maxAge !== null && $maxAge > 0) {
            // Enable public caching with specified max age
            header("Cache-Control: public, max-age=$maxAge");
            // Remove Pragma header to allow caching (it's a legacy HTTP/1.0 header)
            header_remove('Pragma');
        } else {
            // Disable caching
            header("Cache-Control: $cacheControl");
            header('Pragma: no-cache');
        }
    }
    
    /**
     * Generate ETag for response data.
     * 
     * @param string $content Response content
     * @return string ETag value
     */
    private static function generateEtag(string $content): string
    {
        return '"' . md5($content) . '"';
    }
    
    /**
     * Check if client has matching ETag (304 Not Modified).
     * 
     * @param string $etag Current ETag
     * @return bool True if client has matching ETag
     */
    private static function checkEtagMatch(string $etag): bool
    {
        $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        return $clientEtag === $etag;
    }
    
    /**
     * Compress content with gzip if supported.
     * 
     * @param string $content Content to compress
     * @return string Compressed or original content
     */
    private static function compressIfSupported(string $content): string
    {
        // Check if gzip is supported
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        if (strlen($content) < self::GZIP_MIN_SIZE) {
            return $content;
        }
        
        if (strpos($acceptEncoding, 'gzip') === false) {
            return $content;
        }
        
        if (!function_exists('gzencode')) {
            return $content;
        }
        
        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            return $content;
        }
        
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        
        return $compressed;
    }
    
    /**
     * Send JSON response with optimizations.
     * 
     * @param array<string, mixed> $response Full response array
     * @param int $httpStatus HTTP status code
     * @param array<string, mixed> $options Response options (etag, cache_max_age)
     */
    private static function sendJson(array $response, int $httpStatus = 200, array $options = []): void
    {
        self::setCorsHeaders();
        self::setCommonHeaders();
        
        // Encode JSON (compact in production, pretty in dev)
        $isProduction = Config::get('APP_ENV', 'local') === 'production';
        $jsonFlags = JSON_UNESCAPED_UNICODE;
        if (!$isProduction) {
            $jsonFlags |= JSON_PRETTY_PRINT;
        }
        
        $content = json_encode($response, $jsonFlags);
        
        if ($content === false) {
            http_response_code(500);
            echo '{"success":false,"data":null,"error":{"code":"JSON_ERROR","message":"Failed to encode response"}}';
            return;
        }
        
        // Set cache headers first (needed for both 200 and 304 responses per RFC 7232)
        $cacheMaxAge = $options['cache_max_age'] ?? null;
        self::setCacheHeaders(
            $cacheMaxAge ? "public, max-age=$cacheMaxAge" : 'no-cache, no-store, must-revalidate',
            $cacheMaxAge
        );
        
        // Generate and check ETag if enabled
        if ($options['etag'] ?? false) {
            $etag = self::generateEtag($content);
            header("ETag: $etag");
            
            // RFC 7232: 304 response MUST include cache headers (already set above)
            if (self::checkEtagMatch($etag) && $httpStatus === 200) {
                http_response_code(304);
                return;
            }
        }
        
        // Compress if supported
        $output = self::compressIfSupported($content);
        
        // Set content length
        header('Content-Length: ' . strlen($output));
        
        http_response_code($httpStatus);
        echo $output;
    }

    /**
     * Send JSON success response with new format.
     *
     * @param array<string, mixed> $data Response data
     * @param int $httpStatus HTTP status code
     * @param array<string, mixed> $options Response options
     */
    public static function jsonSuccess(array $data = [], int $httpStatus = 200, array $options = []): void
    {
        self::sendJson([
            'success' => true,
            'data' => $data,
            'error' => null
        ], $httpStatus, $options);
    }

    /**
     * Send JSON error response with new format.
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param int $httpStatus HTTP status code
     */
    public static function jsonError(string $code, string $message, int $httpStatus = 400): void
    {
        self::sendJson([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ], $httpStatus);
    }

    /**
     * Send JSON success response with legacy format (for backward compatibility).
     *
     * @param array<string, mixed> $data Response data
     * @param int $httpStatus HTTP status code
     */
    public static function jsonSuccessLegacy(array $data = [], int $httpStatus = 200): void
    {
        self::sendJson(['ok' => true] + $data, $httpStatus);
    }

    /**
     * Send JSON error response with legacy format (for backward compatibility).
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param int $httpStatus HTTP status code
     */
    public static function jsonErrorLegacy(string $code, string $message, int $httpStatus = 400): void
    {
        self::sendJson([
            'ok' => false,
            'code' => $code,
            'message' => $message
        ], $httpStatus);
    }
    
    /**
     * Send cached JSON response (with ETag and cache headers).
     * 
     * @param array<string, mixed> $data Response data
     * @param int $cacheMaxAge Max age in seconds
     */
    public static function jsonCached(array $data, int $cacheMaxAge = 60): void
    {
        self::jsonSuccess($data, 200, [
            'etag' => true,
            'cache_max_age' => $cacheMaxAge
        ]);
    }
    
    /**
     * Send paginated response.
     * 
     * @param array<mixed> $items Items array
     * @param int $page Current page
     * @param int $limit Items per page
     * @param int $total Total items count
     * @param string $itemsKey Key name for items in response
     */
    public static function jsonPaginated(
        array $items, 
        int $page, 
        int $limit, 
        int $total,
        string $itemsKey = 'items'
    ): void {
        $totalPages = (int)ceil($total / $limit);
        
        self::jsonSuccess([
            $itemsKey => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ]
        ]);
    }
}
