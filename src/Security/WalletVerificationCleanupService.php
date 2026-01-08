<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;
use PDOException;

/**
 * Cleanup Service for Wallet Verification Data
 * Automatically cleans up old verification data based on retention policies
 */
class WalletVerificationCleanupService
{
    // Retention periods (in days)
    private const RETENTION_PENDING = 7; // Pending verifications older than 7 days
    private const RETENTION_REJECTED = 90; // Rejected verifications kept for 90 days
    private const RETENTION_EXPIRED = 30; // Expired verifications kept for 30 days
    private const RETENTION_APPROVED = 365; // Approved verifications kept for 1 year
    private const RETENTION_AUDIT_LOG = 2555; // Audit logs kept for 7 years (compliance)
    private const RETENTION_ATTEMPTS = 90; // Verification attempts kept for 90 days
    private const RETENTION_WEBHOOKS = 30; // Webhook logs kept for 30 days

    /**
     * Run cleanup process.
     *
     * @param bool $dryRun If true, only report what would be deleted without actually deleting
     * @return array<string, mixed> Cleanup statistics
     */
    public static function runCleanup(bool $dryRun = false): array
    {
        $stats = [
            'verifications_deleted' => 0,
            'attempts_deleted' => 0,
            'audit_logs_deleted' => 0,
            'webhooks_deleted' => 0,
            'support_tickets_archived' => 0,
            'dry_run' => $dryRun
        ];

        try {
            // Clean up old verifications
            $stats['verifications_deleted'] = self::cleanupVerifications($dryRun);

            // Clean up old verification attempts
            $stats['attempts_deleted'] = self::cleanupAttempts($dryRun);

            // Clean up old audit logs (with longer retention for compliance)
            $stats['audit_logs_deleted'] = self::cleanupAuditLogs($dryRun);

            // Clean up old webhook logs
            $stats['webhooks_deleted'] = self::cleanupWebhooks($dryRun);

            // Archive old support tickets
            $stats['support_tickets_archived'] = self::archiveSupportTickets($dryRun);

            Logger::info('wallet_verification_cleanup_completed', $stats);

        } catch (\Exception $e) {
            Logger::error('wallet_verification_cleanup_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return $stats;
    }

    /**
     * Clean up old verification records.
     */
    private static function cleanupVerifications(bool $dryRun): int
    {
        $db = Database::getConnection();
        $deleted = 0;

        // Delete old pending verifications
        $cutoffDate = date('Y-m-d H:i:s', time() - (self::RETENTION_PENDING * 24 * 3600));
        $deleted += self::deleteVerificationsByStatus('pending', $cutoffDate, $dryRun);

        // Delete old rejected verifications
        $cutoffDate = date('Y-m-d H:i:s', time() - (self::RETENTION_REJECTED * 24 * 3600));
        $deleted += self::deleteVerificationsByStatus('rejected', $cutoffDate, $dryRun);

        // Delete old expired verifications
        $cutoffDate = date('Y-m-d H:i:s', time() - (self::RETENTION_EXPIRED * 24 * 3600));
        $deleted += self::deleteVerificationsByStatus('expired', $cutoffDate, $dryRun);

        // Delete old approved verifications (keep for compliance, but can be cleaned up after 1 year)
        $cutoffDate = date('Y-m-d H:i:s', time() - (self::RETENTION_APPROVED * 24 * 3600));
        $deleted += self::deleteVerificationsByStatus('approved', $cutoffDate, $dryRun);

        return $deleted;
    }

    /**
     * Delete verifications by status and date.
     */
    private static function deleteVerificationsByStatus(string $status, string $cutoffDate, bool $dryRun): int
    {
        $db = Database::getConnection();

        if ($dryRun) {
            $stmt = $db->prepare(
                'SELECT COUNT(*) as count
                 FROM `wallet_verifications`
                 WHERE `status` = :status
                   AND `created_at` < :cutoff_date'
            );
            $stmt->execute([
                'status' => $status,
                'cutoff_date' => $cutoffDate
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        }

        $stmt = $db->prepare(
            'DELETE FROM `wallet_verifications`
             WHERE `status` = :status
               AND `created_at` < :cutoff_date'
        );
        $stmt->execute([
            'status' => $status,
            'cutoff_date' => $cutoffDate
        ]);

        return $stmt->rowCount();
    }

    /**
     * Clean up old verification attempts.
     */
    private static function cleanupAttempts(bool $dryRun): int
    {
        $db = Database::getConnection();
        $cutoffDate = date('Y-m-d H:i:s', time() - (self::RETENTION_ATTEMPTS * 24 * 3600));

        if ($dryRun) {
            $stmt = $db->prepare(
                'SELECT COUNT(*) as count
                 FROM `wallet_verification_attempts`
                 WHERE `created_at` < :cutoff_date'
            );
            $stmt->execute(['cutoff_date' => $cutoffDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        }

        $stmt = $db->prepare(
            'DELETE FROM `wallet_verification_attempts`
             WHERE `created_at` < :cutoff_date'
        );
        $stmt->execute(['cutoff_date' => $cutoffDate]);

        return $stmt->rowCount();
    }

    /**
     * Clean up old audit logs (with longer retention for compliance).
     */
    private static function cleanupAuditLogs(bool $dryRun): int
    {
        $db = Database::getConnection();
        $cutoffDate = date('Y-m-d H:i:s', time() - (self::RETENTION_AUDIT_LOG * 24 * 3600));

        if ($dryRun) {
            $stmt = $db->prepare(
                'SELECT COUNT(*) as count
                 FROM `wallet_verification_audit_log`
                 WHERE `created_at` < :cutoff_date'
            );
            $stmt->execute(['cutoff_date' => $cutoffDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        }

        $stmt = $db->prepare(
            'DELETE FROM `wallet_verification_audit_log`
             WHERE `created_at` < :cutoff_date'
        );
        $stmt->execute(['cutoff_date' => $cutoffDate]);

        return $stmt->rowCount();
    }

    /**
     * Clean up old webhook logs.
     */
    private static function cleanupWebhooks(bool $dryRun): int
    {
        $db = Database::getConnection();
        $cutoffDate = date('Y-m-d H:i:s', time() - (self::RETENTION_WEBHOOKS * 24 * 3600));

        if ($dryRun) {
            $stmt = $db->prepare(
                'SELECT COUNT(*) as count
                 FROM `wallet_verification_webhooks`
                 WHERE `created_at` < :cutoff_date
                   AND `status` IN (:sent, :failed)'
            );
            $stmt->execute([
                'cutoff_date' => $cutoffDate,
                'sent' => 'sent',
                'failed' => 'failed'
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        }

        // Only delete sent/failed webhooks, keep pending/retrying
        $stmt = $db->prepare(
            'DELETE FROM `wallet_verification_webhooks`
             WHERE `created_at` < :cutoff_date
               AND `status` IN (:sent, :failed)'
        );
        $stmt->execute([
            'cutoff_date' => $cutoffDate,
            'sent' => 'sent',
            'failed' => 'failed'
        ]);

        return $stmt->rowCount();
    }

    /**
     * Archive old support tickets.
     */
    private static function archiveSupportTickets(bool $dryRun): int
    {
        $db = Database::getConnection();
        $cutoffDate = date('Y-m-d H:i:s', time() - (90 * 24 * 3600)); // 90 days

        if ($dryRun) {
            $stmt = $db->prepare(
                'SELECT COUNT(*) as count
                 FROM `wallet_verification_support_tickets`
                 WHERE `status` IN (:resolved, :closed)
                   AND `updated_at` < :cutoff_date'
            );
            $stmt->execute([
                'cutoff_date' => $cutoffDate,
                'resolved' => 'resolved',
                'closed' => 'closed'
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['count'] ?? 0);
        }

        // For now, we'll just mark them as archived (you might want to move to archive table)
        $stmt = $db->prepare(
            'UPDATE `wallet_verification_support_tickets`
             SET `status` = :status
             WHERE `status` IN (:resolved, :closed)
               AND `updated_at` < :cutoff_date'
        );
        $stmt->execute([
            'cutoff_date' => $cutoffDate,
            'status' => 'closed',
            'resolved' => 'resolved',
            'closed' => 'closed'
        ]);

        return $stmt->rowCount();
    }

    /**
     * Get cleanup statistics (what would be deleted).
     */
    public static function getCleanupStats(): array
    {
        return self::runCleanup(true);
    }
}

