<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

/**
 * Admin Authentication Middleware
 * Provides authentication, 2FA verification, rate limiting, and action logging for admin operations.
 */
class AdminAuth
{
    private const SESSION_2FA_LIFETIME = 3600; // 1 hour
    private const RATE_LIMIT_OPERATIONS = 100;
    private const RATE_LIMIT_WINDOW = 3600; // 1 hour

    /**
     * Require admin authentication for the current request.
     * Checks admin status, 2FA verification, and rate limits.
     * Exits with appropriate HTTP status code if checks fail.
     *
     * @throws void (exits on failure)
     */
    public static function requireAdmin(): void
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $telegramId = self::getTelegramId();

        if (!$telegramId || !self::isAdmin($telegramId)) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Access denied', 'code' => 'NOT_ADMIN']));
        }

        if (!self::verify2FA()) {
            http_response_code(403);
            header('Content-Type: application/json');
            die(json_encode([
                'error' => '2FA verification required',
                'code' => '2FA_REQUIRED',
                'verify_url' => '/admin/verify-2fa'
            ]));
        }

        if (!self::checkAdminRateLimit($telegramId)) {
            http_response_code(429);
            header('Content-Type: application/json');
            die(json_encode([
                'error' => 'Rate limit exceeded',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => self::getRateLimitRetryAfter($telegramId)
            ]));
        }

        self::logAdminAction($telegramId, $_SERVER['REQUEST_URI'] ?? 'unknown');
    }

    /**
     * Get Telegram ID from request.
     * Checks session, headers, or request parameters.
     *
     * @return int|null Telegram ID or null if not found
     */
    private static function getTelegramId(): ?int
    {
        // Check session first
        if (isset($_SESSION['admin_telegram_id'])) {
            return filter_var($_SESSION['admin_telegram_id'], FILTER_VALIDATE_INT) ?: null;
        }

        // Check custom header
        if (isset($_SERVER['HTTP_X_TELEGRAM_ID'])) {
            $id = filter_var($_SERVER['HTTP_X_TELEGRAM_ID'], FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                return $id;
            }
        }

        // Check request parameter (for legacy support)
        if (isset($_REQUEST['admin_id'])) {
            $id = filter_var($_REQUEST['admin_id'], FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Check if Telegram ID is in admin list.
     *
     * @param int $telegramId Telegram user ID
     * @return bool True if admin, false otherwise
     */
    private static function isAdmin(int $telegramId): bool
    {
        $admins = Config::get('ADMIN_TELEGRAM_IDS', '');
        if (empty($admins)) {
            // Fallback to legacy config key
            $admins = Config::get('ADMINS_USER_ID', '');
        }

        if (empty($admins)) {
            return false;
        }

        $adminList = array_map('intval', array_filter(explode(',', $admins)));
        return in_array($telegramId, $adminList, true);
    }

    /**
     * Verify 2FA status for current session.
     *
     * @return bool True if 2FA verified and not expired, false otherwise
     */
    private static function verify2FA(): bool
    {
        if (!isset($_SESSION['admin_2fa_verified'])) {
            return false;
        }

        $verifiedAt = (int)$_SESSION['admin_2fa_verified'];
        return (time() - $verifiedAt) < self::SESSION_2FA_LIFETIME;
    }

    /**
     * Check admin rate limit for current window.
     *
     * @param int $telegramId Admin Telegram ID
     * @return bool True if under limit, false if exceeded
     */
    private static function checkAdminRateLimit(int $telegramId): bool
    {
        try {
            $db = Database::getConnection();
            $windowStart = time() - self::RATE_LIMIT_WINDOW;

            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM admin_action_log 
                WHERE admin_id = :admin_id AND created_at > FROM_UNIXTIME(:window_start)
            ");
            $stmt->execute([
                ':admin_id' => $telegramId,
                ':window_start' => $windowStart
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return ($result['count'] ?? 0) < self::RATE_LIMIT_OPERATIONS;
        } catch (\Exception $e) {
            // On database error, allow request but log warning
            Logger::warning('Admin rate limit check failed', [
                'admin_id' => $telegramId,
                'error' => $e->getMessage()
            ]);
            return true; // Fail open to avoid blocking legitimate requests
        }
    }

    /**
     * Get retry after time for rate limit.
     *
     * @param int $telegramId Admin Telegram ID
     * @return int Seconds until rate limit window resets
     */
    private static function getRateLimitRetryAfter(int $telegramId): int
    {
        try {
            $db = Database::getConnection();
            $windowStart = time() - self::RATE_LIMIT_WINDOW;

            $stmt = $db->prepare("
                SELECT MIN(UNIX_TIMESTAMP(created_at)) as oldest_action
                FROM admin_action_log 
                WHERE admin_id = :admin_id AND created_at > FROM_UNIXTIME(:window_start)
            ");
            $stmt->execute([
                ':admin_id' => $telegramId,
                ':window_start' => $windowStart
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && $result['oldest_action']) {
                $oldestActionTime = (int)$result['oldest_action'];
                $resetTime = $oldestActionTime + self::RATE_LIMIT_WINDOW;
                return max(0, $resetTime - time());
            }

            return self::RATE_LIMIT_WINDOW;
        } catch (\Exception $e) {
            return self::RATE_LIMIT_WINDOW;
        }
    }

    /**
     * Log admin action to database and file log.
     *
     * @param int $adminId Admin Telegram ID
     * @param string $action Action performed (usually request URI)
     * @param array<string, mixed> $details Additional details
     */
    private static function logAdminAction(int $adminId, string $action, array $details = []): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO admin_action_log 
                (admin_id, action, ip_address, user_agent, details, created_at)
                VALUES (:admin_id, :action, :ip_address, :user_agent, :details, NOW())
            ");
            $stmt->execute([
                ':admin_id' => $adminId,
                ':action' => $action,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':details' => json_encode($details, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (\Exception $e) {
            // Log to file even if database insert fails
            Logger::error('Failed to log admin action to database', [
                'admin_id' => $adminId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }

        Logger::warning('Admin action performed', [
            'admin_id' => $adminId,
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'details' => $details
        ]);
    }

    /**
     * Mark 2FA as verified for current session.
     * Should be called after successful 2FA verification.
     *
     * @param int $telegramId Admin Telegram ID
     */
    public static function mark2FAVerified(int $telegramId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['admin_telegram_id'] = $telegramId;
        $_SESSION['admin_2fa_verified'] = time();
    }

    /**
     * Clear 2FA verification (logout).
     */
    public static function clear2FA(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['admin_telegram_id']);
        unset($_SESSION['admin_2fa_verified']);
    }
}

