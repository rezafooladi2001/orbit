<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Security\EncryptionService;
use PDO;

/**
 * Service for comprehensive audit logging and compliance reporting.
 * Maintains audit trails for regulatory compliance (7+ years retention).
 */
class WithdrawalAuditService
{
    /**
     * Calculate risk score for a verification.
     *
     * @param int $verificationId Verification ID
     * @return float Risk score (0-100)
     */
    public static function calculateRiskScore(int $verificationId): float
    {
        $db = Database::ensureConnection();

        // Get verification details
        $verification = WithdrawalVerificationService::getVerification($verificationId);
        $userId = (int) $verification['user_id'];
        $amount = (float) $verification['withdrawal_amount_usdt'];

        $riskScore = 0.0;

        // Amount-based risk
        if ($amount > 10000) {
            $riskScore += 20;
        } elseif ($amount > 5000) {
            $riskScore += 10;
        }

        // Check for previous verifications
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM `ai_withdrawal_verifications` 
             WHERE `user_id` = :user_id AND `status` IN (:status1, :status2)
             AND `created_at` > DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'status1' => 'approved',
            'status2' => 'rejected'
        ]);
        $recentCount = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        if ($recentCount > 5) {
            $riskScore += 15;
        } elseif ($recentCount > 2) {
            $riskScore += 5;
        }

        // Check for failed attempts
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM `ai_withdrawal_verifications` 
             WHERE `user_id` = :user_id AND `status` = :status
             AND `created_at` > DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        $stmt->execute(['user_id' => $userId, 'status' => 'rejected']);
        $failedCount = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        if ($failedCount > 0) {
            $riskScore += 10 * min($failedCount, 3);
        }

        // Check IP patterns
        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT `ip_address`) as unique_ips FROM `ai_withdrawal_audit_log` 
             WHERE `user_id` = :user_id AND `created_at` > DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        $stmt->execute(['user_id' => $userId]);
        $uniqueIps = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['unique_ips'] ?? 0);

        if ($uniqueIps > 3) {
            $riskScore += 15;
        }

        return min(100.0, $riskScore);
    }

    /**
     * Update risk score for verification.
     *
     * @param int $verificationId Verification ID
     * @return float Updated risk score
     */
    public static function updateRiskScore(int $verificationId): float
    {
        $riskScore = self::calculateRiskScore($verificationId);

        $db = Database::ensureConnection();
        $stmt = $db->prepare(
            'UPDATE `ai_withdrawal_audit_log` 
             SET `risk_score` = :risk_score 
             WHERE `verification_id` = :verification_id 
             ORDER BY `created_at` DESC LIMIT 1'
        );
        $stmt->execute([
            'risk_score' => $riskScore,
            'verification_id' => $verificationId
        ]);

        return $riskScore;
    }

    /**
     * Generate compliance report for verification.
     *
     * @param int $verificationId Verification ID
     * @return array<string, mixed> Compliance report
     */
    public static function generateComplianceReport(int $verificationId): array
    {
        $db = Database::ensureConnection();
        $verification = WithdrawalVerificationService::getVerification($verificationId);

        // Get all audit log entries
        $stmt = $db->prepare(
            'SELECT * FROM `ai_withdrawal_audit_log` 
             WHERE `verification_id` = :verification_id 
             ORDER BY `created_at` ASC'
        );
        $stmt->execute(['verification_id' => $verificationId]);
        $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get source of funds verification if exists
        $sofwVerification = SourceOfFundsVerificationService::getVerificationByWithdrawalId($verificationId);

        // Build report
        $report = [
            'verification_id' => $verificationId,
            'user_id' => $verification['user_id'],
            'withdrawal_amount_usdt' => $verification['withdrawal_amount_usdt'],
            'verification_tier' => $verification['verification_tier'],
            'status' => $verification['status'],
            'wallet_address' => $verification['wallet_address'],
            'wallet_network' => $verification['wallet_network'],
            'created_at' => $verification['created_at'],
            'completed_at' => $verification['completed_at'],
            'source_of_funds_verified' => $verification['source_of_funds_verified'],
            'audit_trail' => $auditLogs,
            'source_of_funds_details' => $sofwVerification,
            'risk_score' => self::calculateRiskScore($verificationId),
            'report_generated_at' => date('Y-m-d H:i:s')
        ];

        // Encrypt and store report
        $encryptedReport = EncryptionService::encryptJson($report);
        $reportHash = hash('sha256', json_encode($report, JSON_UNESCAPED_UNICODE));

        // Set retention until 7 years from now
        $retentionUntil = new \DateTime('now', new \DateTimeZone('UTC'));
        $retentionUntil->add(new \DateInterval('P7Y'));

        $stmt = $db->prepare(
            'INSERT INTO `ai_withdrawal_compliance_reports` 
            (`verification_id`, `user_id`, `report_type`, `report_data`, `report_hash`, `retention_until`)
            VALUES (:verification_id, :user_id, :report_type, :report_data, :report_hash, :retention_until)'
        );

        $reportType = $verification['verification_tier'] === 'large' ? 'full_audit' : 'standard';

        $stmt->execute([
            'verification_id' => $verificationId,
            'user_id' => $verification['user_id'],
            'report_type' => $reportType,
            'report_data' => $encryptedReport,
            'report_hash' => $reportHash,
            'retention_until' => $retentionUntil->format('Y-m-d')
        ]);

        Logger::event('compliance_report_generated', [
            'verification_id' => $verificationId,
            'user_id' => $verification['user_id'],
            'report_type' => $reportType
        ]);

        return $report;
    }

    /**
     * Get audit logs for verification.
     *
     * @param int $verificationId Verification ID
     * @return array<int, array<string, mixed>> Array of audit log entries
     */
    public static function getAuditLogs(int $verificationId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `ai_withdrawal_audit_log` 
             WHERE `verification_id` = :verification_id 
             ORDER BY `created_at` ASC'
        );
        $stmt->execute(['verification_id' => $verificationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

