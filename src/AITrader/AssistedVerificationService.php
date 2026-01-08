<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Security\EncryptionService;
use PDO;
use PDOException;

/**
 * Service for assisted verification pathway.
 * Provides premium customer support for users who have issues with standard wallet signing.
 */
class AssistedVerificationService
{
    /**
     * Request assisted verification.
     *
     * @param int $verificationId Withdrawal verification ID
     * @param int $userId User ID
     * @param string $reason Reason for assisted verification
     * @param array<string, mixed>|null $userInfo User-provided information (will be encrypted)
     * @return array<string, mixed> Assisted verification record
     */
    public static function requestAssistance(
        int $verificationId,
        int $userId,
        string $reason,
        ?array $userInfo = null
    ): array {
        $db = Database::ensureConnection();

        // Generate support ticket ID
        $supportTicketId = 'AV-' . date('Ymd') . '-' . strtoupper(substr(md5($verificationId . $userId . time()), 0, 8));

        try {
            $db->beginTransaction();

            // Create assisted verification record
            $stmt = $db->prepare(
                'INSERT INTO `ai_assisted_verifications` 
                (`verification_id`, `user_id`, `support_ticket_id`, `reason`, `user_provided_info`, `status`)
                VALUES (:verification_id, :user_id, :ticket_id, :reason, :user_info, :status)'
            );

            $encryptedUserInfo = null;
            if ($userInfo !== null) {
                $encryptedUserInfo = EncryptionService::encryptJson($userInfo);
            }

            $stmt->execute([
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'ticket_id' => $supportTicketId,
                'reason' => $reason,
                'user_info' => $encryptedUserInfo,
                'status' => 'pending'
            ]);

            $assistedVerificationId = (int) $db->lastInsertId();

            // Update withdrawal verification to mark assisted verification as required
            $updateStmt = $db->prepare(
                'UPDATE `ai_withdrawal_verifications` 
                 SET `requires_assisted_verification` = TRUE, 
                     `assisted_verification_reason` = :reason, `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $updateStmt->execute([
                'reason' => $reason,
                'id' => $verificationId
            ]);

            $db->commit();

            Logger::event('assisted_verification_requested', [
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'assisted_verification_id' => $assistedVerificationId,
                'support_ticket_id' => $supportTicketId
            ]);

            return self::getAssistedVerification($assistedVerificationId);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('Failed to request assisted verification', [
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get assisted verification record.
     *
     * @param int $assistedVerificationId Assisted verification ID
     * @return array<string, mixed> Assisted verification record
     * @throws \RuntimeException If not found
     */
    public static function getAssistedVerification(int $assistedVerificationId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_assisted_verifications` WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute(['id' => $assistedVerificationId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            throw new \RuntimeException('Assisted verification not found');
        }

        return $record;
    }

    /**
     * Get assisted verification by withdrawal verification ID.
     *
     * @param int $verificationId Withdrawal verification ID
     * @return array<string, mixed>|null Assisted verification record or null
     */
    public static function getAssistedVerificationByVerificationId(int $verificationId): ?array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_assisted_verifications` 
             WHERE `verification_id` = :verification_id 
             LIMIT 1'
        );
        $stmt->execute(['verification_id' => $verificationId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record !== false ? $record : null;
    }

    /**
     * Assign assisted verification to admin.
     *
     * @param int $assistedVerificationId Assisted verification ID
     * @param int $adminUserId Admin user ID
     * @return array<string, mixed> Updated record
     */
    public static function assignToAdmin(int $assistedVerificationId, int $adminUserId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'UPDATE `ai_assisted_verifications` 
             SET `assigned_to_admin_id` = :admin_id, `status` = :status, `updated_at` = NOW()
             WHERE `id` = :id'
        );
        $stmt->execute([
            'admin_id' => $adminUserId,
            'status' => 'in_progress',
            'id' => $assistedVerificationId
        ]);

        return self::getAssistedVerification($assistedVerificationId);
    }

    /**
     * Complete assisted verification.
     *
     * @param int $assistedVerificationId Assisted verification ID
     * @param int $adminUserId Admin user ID
     * @param string $verificationResult Verification result (encrypted)
     * @param string $supportNotes Internal support notes
     * @return array<string, mixed> Updated record
     */
    public static function completeVerification(
        int $assistedVerificationId,
        int $adminUserId,
        string $verificationResult,
        string $supportNotes
    ): array {
        $db = Database::ensureConnection();
        $record = self::getAssistedVerification($assistedVerificationId);

        try {
            $db->beginTransaction();

            // Encrypt verification result
            $encryptedResult = EncryptionService::encrypt($verificationResult);

            // Update assisted verification
            $stmt = $db->prepare(
                'UPDATE `ai_assisted_verifications` 
                 SET `verification_result` = :result, `support_notes` = :notes, 
                     `status` = :status, `completed_at` = NOW(), `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $stmt->execute([
                'result' => $encryptedResult,
                'notes' => $supportNotes,
                'status' => 'completed',
                'id' => $assistedVerificationId
            ]);

            // Update withdrawal verification to mark source of funds as verified
            $updateStmt = $db->prepare(
                'UPDATE `ai_withdrawal_verifications` 
                 SET `source_of_funds_verified` = TRUE, `updated_at` = NOW()
                 WHERE `id` = :id'
            );
            $updateStmt->execute(['id' => $record['verification_id']]);

            $db->commit();

            Logger::event('assisted_verification_completed', [
                'assisted_verification_id' => $assistedVerificationId,
                'verification_id' => $record['verification_id'],
                'admin_user_id' => $adminUserId
            ]);

            return self::getAssistedVerification($assistedVerificationId);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get pending assisted verifications.
     *
     * @param int $limit Maximum number to return
     * @return array<int, array<string, mixed>> Array of assisted verification records
     */
    public static function getPendingVerifications(int $limit = 50): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_assisted_verifications` 
             WHERE `status` IN (:status1, :status2)
             ORDER BY `created_at` ASC
             LIMIT :limit'
        );
        $stmt->bindValue('status1', 'pending', PDO::PARAM_STR);
        $stmt->bindValue('status2', 'in_progress', PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

