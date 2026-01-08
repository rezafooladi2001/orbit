<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Logging\Logger;

/**
 * Security Event Logger
 * Specialized logger for security-relevant events with automatic alerting.
 */
class SecurityLogger
{
    /**
     * Log security event with severity level.
     *
     * @param string $severity Severity level (info, warning, critical)
     * @param string $message Event message
     * @param array<string, mixed> $context Additional context
     */
    private static function log(string $severity, string $message, array $context = []): void
    {
        // Add security-specific context
        $securityContext = array_merge($context, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'timestamp' => time()
        ]);

        // Log using standard logger
        switch ($severity) {
            case 'critical':
                Logger::error("SECURITY CRITICAL: {$message}", $securityContext);
                // TODO: Add alerting mechanism (email, SMS, webhook, etc.)
                break;
            case 'warning':
                Logger::warning("SECURITY WARNING: {$message}", $securityContext);
                break;
            case 'info':
            default:
                Logger::info("SECURITY INFO: {$message}", $securityContext);
                break;
        }
    }

    /**
     * Log critical security event (e.g., SQL injection attempt, unauthorized access).
     *
     * @param string $message Event message
     * @param array<string, mixed> $context Additional context
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    /**
     * Log security warning (e.g., suspicious activity, rate limit exceeded).
     *
     * @param string $message Event message
     * @param array<string, mixed> $context Additional context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /**
     * Log security info (e.g., successful authentication, normal operations).
     *
     * @param string $message Event message
     * @param array<string, mixed> $context Additional context
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /**
     * Log SQL injection attempt.
     *
     * @param string $query Suspicious query
     * @param array<string, mixed> $context Additional context
     */
    public static function logSqlInjectionAttempt(string $query, array $context = []): void
    {
        self::critical('SQL injection attempt detected', array_merge($context, [
            'suspicious_query' => substr($query, 0, 500) // Limit length
        ]));
    }

    /**
     * Log unauthorized access attempt.
     *
     * @param string $resource Resource that was accessed
     * @param array<string, mixed> $context Additional context
     */
    public static function logUnauthorizedAccess(string $resource, array $context = []): void
    {
        self::critical('Unauthorized access attempt', array_merge($context, [
            'resource' => $resource
        ]));
    }

    /**
     * Log authentication failure.
     *
     * @param string $username Username or identifier
     * @param array<string, mixed> $context Additional context
     */
    public static function logAuthenticationFailure(string $username, array $context = []): void
    {
        self::warning('Authentication failure', array_merge($context, [
            'username' => $username
        ]));
    }

    /**
     * Log successful authentication.
     *
     * @param string $username Username or identifier
     * @param array<string, mixed> $context Additional context
     */
    public static function logAuthenticationSuccess(string $username, array $context = []): void
    {
        self::info('Authentication success', array_merge($context, [
            'username' => $username
        ]));
    }

    /**
     * Log rate limit exceeded.
     *
     * @param string $identifier User or IP identifier
     * @param string $action Action that was rate limited
     * @param array<string, mixed> $context Additional context
     */
    public static function logRateLimitExceeded(string $identifier, string $action, array $context = []): void
    {
        self::warning('Rate limit exceeded', array_merge($context, [
            'identifier' => $identifier,
            'action' => $action
        ]));
    }

    /**
     * Log CSRF token validation failure.
     *
     * @param array<string, mixed> $context Additional context
     */
    public static function logCsrfFailure(array $context = []): void
    {
        self::warning('CSRF token validation failed', $context);
    }

    /**
     * Log file upload security issue.
     *
     * @param string $filename Filename that was rejected
     * @param string $reason Reason for rejection
     * @param array<string, mixed> $context Additional context
     */
    public static function logFileUploadSecurityIssue(string $filename, string $reason, array $context = []): void
    {
        self::warning('File upload security issue', array_merge($context, [
            'filename' => $filename,
            'reason' => $reason
        ]));
    }
}

