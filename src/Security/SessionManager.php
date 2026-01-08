<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

/**
 * Session Manager
 * Database-backed session management with expiration and revocation support.
 */
class SessionManager
{
    private const DEFAULT_LIFETIME = 3600; // 1 hour
    private const SESSION_ID_LENGTH = 32;
    private static bool $initialized = false;

    /**
     * Initialize session manager.
     * Must be called before using session functions.
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        // Set custom session handlers
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', self::isHttps() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', (string)self::DEFAULT_LIFETIME);

        // Set custom session save handler
        session_set_save_handler(
            [self::class, 'open'],
            [self::class, 'close'],
            [self::class, 'read'],
            [self::class, 'write'],
            [self::class, 'destroy'],
            [self::class, 'gc']
        );

        self::$initialized = true;
    }

    /**
     * Check if request is over HTTPS.
     *
     * @return bool True if HTTPS
     */
    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    }

    /**
     * Start session with database backend.
     *
     * @param string|null $sessionId Optional session ID
     * @return bool True if session started successfully
     */
    public static function start(?string $sessionId = null): bool
    {
        self::initialize();

        if ($sessionId !== null) {
            session_id($sessionId);
        }

        return session_start();
    }

    /**
     * Generate secure session ID.
     *
     * @return string Session ID
     */
    public static function generateSessionId(): string
    {
        return bin2hex(random_bytes(self::SESSION_ID_LENGTH));
    }

    /**
     * Get session data for a session ID.
     *
     * @param string $sessionId Session ID
     * @return array<string, mixed>|null Session data or null if not found/expired
     */
    public static function getSession(string $sessionId): ?array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT session_data, user_id, expires_at, revoked
                FROM user_sessions
                WHERE session_id = :session_id
            ");
            $stmt->execute([':session_id' => $sessionId]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$session) {
                return null;
            }

            // Check if expired
            if (strtotime($session['expires_at']) < time()) {
                self::destroy($sessionId);
                return null;
            }

            // Check if revoked
            if ((bool)$session['revoked']) {
                return null;
            }

            $data = json_decode($session['session_data'], true);
            return [
                'data' => is_array($data) ? $data : [],
                'user_id' => $session['user_id'],
                'expires_at' => $session['expires_at']
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Refresh session expiration time.
     *
     * @param string $sessionId Session ID
     * @param int $lifetime Lifetime in seconds (default: 1 hour)
     * @return bool True if refreshed successfully
     */
    public static function refresh(string $sessionId, int $lifetime = self::DEFAULT_LIFETIME): bool
    {
        try {
            $db = Database::getConnection();
            $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);

            $stmt = $db->prepare("
                UPDATE user_sessions
                SET expires_at = :expires_at, last_activity = NOW()
                WHERE session_id = :session_id
            ");
            $stmt->execute([
                ':session_id' => $sessionId,
                ':expires_at' => $expiresAt
            ]);

            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            Logger::error('Failed to refresh session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoke session (mark as revoked).
     *
     * @param string $sessionId Session ID
     * @return bool True if revoked successfully
     */
    public static function revoke(string $sessionId): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE user_sessions
                SET revoked = 1, revoked_at = NOW()
                WHERE session_id = :session_id
            ");
            $stmt->execute([':session_id' => $sessionId]);

            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            Logger::error('Failed to revoke session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoke all sessions for a user.
     *
     * @param int $userId User ID
     * @return int Number of sessions revoked
     */
    public static function revokeAllForUser(int $userId): int
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE user_sessions
                SET revoked = 1, revoked_at = NOW()
                WHERE user_id = :user_id AND revoked = 0
            ");
            $stmt->execute([':user_id' => $userId]);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            Logger::error('Failed to revoke all sessions for user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // Session save handler methods

    /**
     * Open session (save handler).
     *
     * @param string $savePath Save path (not used)
     * @param string $sessionName Session name
     * @return bool True on success
     */
    public static function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    /**
     * Close session (save handler).
     *
     * @return bool True on success
     */
    public static function close(): bool
    {
        return true;
    }

    /**
     * Read session data (save handler).
     *
     * @param string $sessionId Session ID
     * @return string Session data (serialized)
     */
    public static function read(string $sessionId): string
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT session_data, expires_at, revoked
                FROM user_sessions
                WHERE session_id = :session_id
            ");
            $stmt->execute([':session_id' => $sessionId]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$session) {
                return '';
            }

            // Check if expired
            if (strtotime($session['expires_at']) < time()) {
                self::destroy($sessionId);
                return '';
            }

            // Check if revoked
            if ((bool)$session['revoked']) {
                return '';
            }

            // Refresh last activity
            self::refresh($sessionId);

            return $session['session_data'] ?? '';
        } catch (\Exception $e) {
            Logger::error('Failed to read session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * Write session data (save handler).
     *
     * @param string $sessionId Session ID
     * @param string $data Session data (serialized)
     * @return bool True on success
     */
    public static function write(string $sessionId, string $data): bool
    {
        try {
            $db = Database::getConnection();
            $expiresAt = date('Y-m-d H:i:s', time() + self::DEFAULT_LIFETIME);

            // Try to get user_id from session data if available
            $sessionData = json_decode($data, true);
            $userId = null;
            if (is_array($sessionData) && isset($sessionData['user_id'])) {
                $userId = (int)$sessionData['user_id'];
            }

            $stmt = $db->prepare("
                INSERT INTO user_sessions 
                (session_id, user_id, session_data, expires_at, created_at, last_activity)
                VALUES (:session_id, :user_id, :session_data, :expires_at, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    session_data = :session_data_update,
                    expires_at = :expires_at_update,
                    last_activity = NOW()
            ");
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':session_data' => $data,
                ':expires_at' => $expiresAt,
                ':session_data_update' => $data,
                ':expires_at_update' => $expiresAt
            ]);

            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to write session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Destroy session (save handler).
     *
     * @param string $sessionId Session ID
     * @return bool True on success
     */
    public static function destroy(string $sessionId): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM user_sessions WHERE session_id = :session_id");
            $stmt->execute([':session_id' => $sessionId]);

            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to destroy session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Garbage collection (save handler).
     * Removes expired sessions.
     *
     * @param int $maxLifetime Maximum lifetime in seconds
     * @return int|false Number of deleted sessions or false on error
     */
    public static function gc(int $maxLifetime): int|false
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                DELETE FROM user_sessions 
                WHERE expires_at < NOW() OR (revoked = 1 AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
            ");
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\Exception $e) {
            Logger::error('Failed to run session garbage collection', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

