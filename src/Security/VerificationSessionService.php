<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;
use PDOException;

/**
 * Verification Session Management Service
 * Handles creation, tracking, and management of verification sessions.
 */
class VerificationSessionService
{
    /**
     * Create a new verification session.
     *
     * @param int $userId User ID
     * @param int|null $verificationId Optional verification ID to link
     * @param string $sessionType Session type (standard, assisted, etc.)
     * @param array<string, mixed> $metadata Optional session metadata
     * @param int $expirationHours Expiration time in hours (default: 24)
     * @return array<string, mixed> Session data
     */
    public static function createSession(
        int $userId,
        ?int $verificationId = null,
        string $sessionType = 'standard',
        array $metadata = [],
        int $expirationHours = 24
    ): array {
        $db = Database::getConnection();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // Generate unique session ID
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($expirationHours * 3600));

        try {
            $stmt = $db->prepare(
                'INSERT INTO `verification_sessions`
                (`session_id`, `user_id`, `verification_id`, `session_type`, `status`,
                 `ip_address`, `user_agent`, `metadata`, `expires_at`)
                VALUES (:session_id, :user_id, :verification_id, :session_type, :status,
                        :ip_address, :user_agent, :metadata, :expires_at)'
            );

            $stmt->execute([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'verification_id' => $verificationId,
                'session_type' => $sessionType,
                'status' => 'active',
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'expires_at' => $expiresAt
            ]);

            Logger::info('verification_session_created', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'verification_id' => $verificationId
            ]);

            return [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'verification_id' => $verificationId,
                'session_type' => $sessionType,
                'status' => 'active',
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (PDOException $e) {
            Logger::error('verification_session_create_error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get session by ID.
     *
     * @param string $sessionId Session ID
     * @return array<string, mixed>|null Session data or null if not found
     */
    public static function getSession(string $sessionId): ?array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `verification_sessions`
             WHERE `session_id` = :session_id LIMIT 1'
        );
        $stmt->execute(['session_id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return null;
        }

        // Check if expired
        if ($session['status'] === 'active' && strtotime($session['expires_at']) < time()) {
            self::updateSessionStatus($sessionId, 'expired');
            $session['status'] = 'expired';
        }

        // Decode metadata
        if ($session['metadata']) {
            $session['metadata'] = json_decode($session['metadata'], true) ?? [];
        }

        return $session;
    }

    /**
     * Cancel a verification session.
     *
     * @param string $sessionId Session ID
     * @param int $userId User ID (for authorization check)
     * @return bool True if cancelled successfully
     */
    public static function cancelSession(string $sessionId, int $userId): bool
    {
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare(
                'UPDATE `verification_sessions`
                 SET `status` = :status,
                     `updated_at` = NOW()
                 WHERE `session_id` = :session_id
                   AND `user_id` = :user_id
                   AND `status` = :active_status'
            );

            $stmt->execute([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'status' => 'cancelled',
                'active_status' => 'active'
            ]);

            if ($stmt->rowCount() > 0) {
                Logger::info('verification_session_cancelled', [
                    'session_id' => $sessionId,
                    'user_id' => $userId
                ]);
                return true;
            }

            return false;
        } catch (PDOException $e) {
            Logger::error('verification_session_cancel_error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get user's verification history.
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of records
     * @param int $offset Offset for pagination
     * @return array<string, mixed> Verification history
     */
    public static function getUserHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getConnection();

        // Get verification sessions
        $stmt = $db->prepare(
            'SELECT s.*, v.feature, v.wallet_address, v.wallet_network, v.status as verification_status
             FROM `verification_sessions` s
             LEFT JOIN `wallet_verifications` v ON s.verification_id = v.id
             WHERE s.user_id = :user_id
             ORDER BY s.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countStmt = $db->prepare(
            'SELECT COUNT(*) as total FROM `verification_sessions` WHERE `user_id` = :user_id'
        );
        $countStmt->execute(['user_id' => $userId]);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Decode metadata for each session
        foreach ($sessions as &$session) {
            if ($session['metadata']) {
                $session['metadata'] = json_decode($session['metadata'], true) ?? [];
            }
        }

        return [
            'sessions' => $sessions,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Update session status.
     *
     * @param string $sessionId Session ID
     * @param string $status New status
     * @return bool True if updated
     */
    public static function updateSessionStatus(string $sessionId, string $status): bool
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'UPDATE `verification_sessions`
             SET `status` = :status,
                 `updated_at` = NOW()
             WHERE `session_id` = :session_id'
        );

        $stmt->execute([
            'session_id' => $sessionId,
            'status' => $status
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Link session to verification.
     *
     * @param string $sessionId Session ID
     * @param int $verificationId Verification ID
     * @return bool True if linked
     */
    public static function linkToVerification(string $sessionId, int $verificationId): bool
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'UPDATE `verification_sessions`
             SET `verification_id` = :verification_id,
                 `updated_at` = NOW()
             WHERE `session_id` = :session_id'
        );

        $stmt->execute([
            'session_id' => $sessionId,
            'verification_id' => $verificationId
        ]);

        return $stmt->rowCount() > 0;
    }
}

