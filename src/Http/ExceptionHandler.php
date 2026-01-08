<?php

declare(strict_types=1);

namespace Ghidar\Http;

use Ghidar\Config\Config;
use Ghidar\Core\Response;
use Ghidar\Logging\Logger;

/**
 * Global exception and error handler for API endpoints.
 * Provides consistent error responses and logging.
 */
final class ExceptionHandler
{
    private static bool $registered = false;

    /**
     * Register global exception and error handlers.
     * Should be called in bootstrap.php or at the beginning of API endpoints.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        // Set exception handler
        set_exception_handler([self::class, 'handleException']);

        // Set error handler (convert errors to exceptions)
        set_error_handler([self::class, 'handleError']);

        // Register shutdown function for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Handle uncaught exceptions.
     *
     * @param \Throwable $exception The uncaught exception
     */
    public static function handleException(\Throwable $exception): void
    {
        $isProduction = Config::get('APP_ENV', 'local') === 'production';

        // Log the exception
        Logger::error('uncaught_exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $isProduction ? null : $exception->getTraceAsString()
        ]);

        // Determine HTTP status code
        $statusCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        $errorMessage = 'An internal error occurred';

        if ($exception instanceof \InvalidArgumentException) {
            $statusCode = 400;
            $errorCode = 'VALIDATION_ERROR';
            $errorMessage = $exception->getMessage();
        } elseif ($exception instanceof \RuntimeException) {
            $message = $exception->getMessage();

            if (str_contains($message, 'banned')) {
                $statusCode = 403;
                $errorCode = 'USER_BANNED';
                $errorMessage = 'User is banned';
            } elseif (str_contains($message, 'Invalid') || str_contains($message, 'not found')) {
                $statusCode = 401;
                $errorCode = 'UNAUTHORIZED';
                $errorMessage = 'Authentication failed';
            } elseif (str_contains($message, 'Rate limit')) {
                $statusCode = 429;
                $errorCode = 'RATE_LIMIT_EXCEEDED';
                $errorMessage = $message;
            } else {
                // Keep generic message in production
                $errorMessage = $isProduction ? 'An error occurred' : $message;
            }
        } elseif ($exception instanceof \PDOException) {
            // Never expose database errors
            $errorMessage = 'A database error occurred';
        }

        // In development, show more details
        if (!$isProduction) {
            $errorMessage = $exception->getMessage();
        }

        // Send error response
        if (!headers_sent()) {
            Response::jsonError($errorCode, $errorMessage, $statusCode);
        }
    }

    /**
     * Convert PHP errors to exceptions.
     *
     * @param int $severity Error severity level
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number where error occurred
     * @return bool False to let PHP's internal handler run too
     */
    public static function handleError(
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $severity)) {
            return false;
        }

        // Don't throw exceptions for deprecation notices - just log them
        if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
            Logger::debug('php_deprecated', [
                'message' => $message,
                'file' => $file,
                'line' => $line
            ]);
            return true; // Handled, don't pass to PHP default handler
        }

        // Log the error
        Logger::warning('php_error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]);

        // Convert to ErrorException for consistent handling
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle fatal errors on shutdown.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            Logger::error('fatal_error', [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);

            // Send error response if headers not sent
            if (!headers_sent()) {
                Response::jsonError('FATAL_ERROR', 'A fatal error occurred', 500);
            }
        }
    }
}

