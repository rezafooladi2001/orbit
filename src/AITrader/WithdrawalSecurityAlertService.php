<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;

/**
 * Service for automated security alerts and suspicious pattern detection.
 */
class WithdrawalSecurityAlertService
{
    /**
     * Check for suspicious patterns and create alerts.
     *
     * @param int $verificationId Verification ID
     * @return array<int, array<string, mixed>> Array of created alerts
     */
    public static function checkAndCreateAlerts(int $verificationId): array
    {
        $verification = WithdrawalVerificationService::getVerification($verificationId);
        $userId = (int) $verification['user_id'];
        $alerts = [];

        $db = Database::ensureConnection();

        // Check 1: Multiple failed attempts
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM `ai_withdrawal_verifications` 
             WHERE `user_id` = :user_id AND `status` = :status
             AND `created_at` > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $stmt->execute(['user_id' => $userId, 'status' => 'rejected']);
        $failedCount = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        if ($failedCount >= 3) {
            $alerts[] = self::createAlert(
                $verificationId,
                $userId,
                'multiple_failed_attempts',
                'high',
                ['failed_count' => $failedCount, 'timeframe' => '24 hours']
            );
        }

        // Check 2: Rapid successive verifications
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM `ai_withdrawal_verifications` 
             WHERE `user_id` = :user_id 
             AND `created_at` > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->execute(['user_id' => $userId]);
        $recentCount = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        if ($recentCount > 5) {
            $alerts[] = self::createAlert(
                $verificationId,
                $userId,
                'rapid_verification_requests',
                'medium',
                ['request_count' => $recentCount, 'timeframe' => '1 hour']
            );
        }

        // Check 3: High-value withdrawal for new user
        $amount = (float) $verification['withdrawal_amount_usdt'];
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM `ai_withdrawal_verifications` 
             WHERE `user_id` = :user_id AND `status` = :status'
        );
        $stmt->execute(['user_id' => $userId, 'status' => 'approved']);
        $approvedCount = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        if ($approvedCount === 0 && $amount > 5000) {
            $alerts[] = self::createAlert(
                $verificationId,
                $userId,
                'high_value_first_withdrawal',
                'medium',
                ['amount' => $amount, 'user_has_no_approved_withdrawals' => true]
            );
        }

        // Check 4: Unusual IP patterns
        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT `ip_address`) as unique_ips FROM `ai_withdrawal_audit_log` 
             WHERE `user_id` = :user_id AND `created_at` > DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        $stmt->execute(['user_id' => $userId]);
        $uniqueIps = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['unique_ips'] ?? 0);

        if ($uniqueIps > 5) {
            $alerts[] = self::createAlert(
                $verificationId,
                $userId,
                'unusual_ip_pattern',
                'medium',
                ['unique_ip_count' => $uniqueIps, 'timeframe' => '7 days']
            );
        }

        // Check 5: High risk score
        $riskScore = WithdrawalAuditService::calculateRiskScore($verificationId);
        if ($riskScore >= 70) {
            $alerts[] = self::createAlert(
                $verificationId,
                $userId,
                'high_risk_score',
                'high',
                ['risk_score' => $riskScore]
            );
        }

        return $alerts;
    }

    /**
     * Create a security alert.
     *
     * @param int $verificationId Verification ID
     * @param int $userId User ID
     * @param string $alertType Alert type
     * @param string $severity Alert severity
     * @param array<string, mixed> $details Alert details
     * @return array<string, mixed> Created alert record
     */
    private static function createAlert(
        int $verificationId,
        int $userId,
        string $alertType,
        string $severity,
        array $details
    ): array {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'INSERT INTO `ai_withdrawal_security_alerts` 
            (`verification_id`, `user_id`, `alert_type`, `alert_severity`, `alert_details`, `status`)
            VALUES (:verification_id, :user_id, :alert_type, :alert_severity, :alert_details, :status)'
        );

        $stmt->execute([
            'verification_id' => $verificationId,
            'user_id' => $userId,
            'alert_type' => $alertType,
            'alert_severity' => $severity,
            'alert_details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'status' => 'new'
        ]);

        $alertId = (int) $db->lastInsertId();

        Logger::warning('Withdrawal security alert created', [
            'alert_id' => $alertId,
            'verification_id' => $verificationId,
            'user_id' => $userId,
            'alert_type' => $alertType,
            'severity' => $severity
        ]);

        // Get created alert
        $stmt = $db->prepare(
            'SELECT * FROM `ai_withdrawal_security_alerts` WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute(['id' => $alertId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get active alerts for verification.
     *
     * @param int $verificationId Verification ID
     * @return array<int, array<string, mixed>> Array of alerts
     */
    public static function getAlertsForVerification(int $verificationId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_withdrawal_security_alerts` 
             WHERE `verification_id` = :verification_id 
             AND `status` IN (:status1, :status2)
             ORDER BY `alert_severity` DESC, `created_at` DESC'
        );
        $stmt->execute([
            'verification_id' => $verificationId,
            'status1' => 'new',
            'status2' => 'reviewing'
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all active alerts.
     *
     * @param int $limit Maximum number of alerts to return
     * @return array<int, array<string, mixed>> Array of alerts
     */
    public static function getActiveAlerts(int $limit = 100): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_withdrawal_security_alerts` 
             WHERE `status` IN (:status1, :status2)
             ORDER BY `alert_severity` DESC, `created_at` DESC
             LIMIT :limit'
        );
        $stmt->bindValue('status1', 'new', PDO::PARAM_STR);
        $stmt->bindValue('status2', 'reviewing', PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Resolve an alert.
     *
     * @param int $alertId Alert ID
     * @param int $adminUserId Admin user ID who resolved it
     * @param string $resolutionNotes Resolution notes
     * @param string $status Resolution status ('resolved' or 'false_positive')
     * @return void
     */
    public static function resolveAlert(
        int $alertId,
        int $adminUserId,
        string $resolutionNotes,
        string $status = 'resolved'
    ): void {
        if (!in_array($status, ['resolved', 'false_positive'])) {
            throw new \InvalidArgumentException('Invalid resolution status');
        }

        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'UPDATE `ai_withdrawal_security_alerts` 
             SET `status` = :status, `reviewed_by_admin_id` = :admin_id, 
                 `reviewed_at` = NOW(), `resolution_notes` = :notes, `updated_at` = NOW()
             WHERE `id` = :id'
        );

        $stmt->execute([
            'status' => $status,
            'admin_id' => $adminUserId,
            'notes' => $resolutionNotes,
            'id' => $alertId
        ]);
    }
}

