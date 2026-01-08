<?php

declare(strict_types=1);

/**
 * Get Compliance Statistics API endpoint
 * GET /api/compliance/stats
 * Gets compliance statistics and metrics
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
    exit;
}

try {
    // Authenticate user and require admin access
    $context = UserContext::requireAdmin();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get date range from query parameters
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');

    $db = Database::ensureConnection();

    $whereClause = "WHERE DATE(v.created_at) BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];

    // Overall compliance metrics
    $stmt = $db->prepare(
        "SELECT 
            COUNT(*) as total_verifications,
            SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN v.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN v.risk_level = 'high' THEN 1 ELSE 0 END) as high_risk_count,
            SUM(CASE WHEN v.risk_level = 'medium' THEN 1 ELSE 0 END) as medium_risk_count,
            SUM(CASE WHEN v.risk_level = 'low' THEN 1 ELSE 0 END) as low_risk_count,
            AVG(v.risk_score) as avg_risk_score,
            COUNT(DISTINCT v.user_id) as unique_users,
            COUNT(DISTINCT v.wallet_address) as unique_wallets
         FROM `wallet_verifications` v
         {$whereClause}"
    );
    $stmt->execute($params);
    $overall = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Failed verification attempts
    $stmt = $db->prepare(
        "SELECT COUNT(*) as total_failed_attempts
         FROM `wallet_verification_attempts` va
         INNER JOIN `wallet_verifications` v ON va.verification_id = v.id
         {$whereClause}
         AND va.success = 0"
    );
    $stmt->execute($params);
    $failedAttempts = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Admin overrides
    $stmt = $db->prepare(
        "SELECT COUNT(*) as admin_override_count
         FROM `wallet_verifications` v
         {$whereClause}
         AND v.admin_override_by IS NOT NULL"
    );
    $stmt->execute($params);
    $adminOverrides = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Compliance flags summary
    $stmt = $db->prepare(
        "SELECT 
            COUNT(DISTINCT CASE WHEN v.risk_level = 'high' THEN v.id END) as high_risk_verifications,
            COUNT(DISTINCT CASE WHEN v.admin_override_by IS NOT NULL THEN v.id END) as admin_override_verifications
         FROM `wallet_verifications` v
         {$whereClause}"
    );
    $stmt->execute($params);
    $complianceFlags = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Verification method distribution
    $stmt = $db->prepare(
        "SELECT 
            v.verification_method,
            COUNT(*) as count,
            SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) as approved
         FROM `wallet_verifications` v
         {$whereClause}
         GROUP BY v.verification_method"
    );
    $stmt->execute($params);
    $methodDistribution = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Risk level distribution
    $stmt = $db->prepare(
        "SELECT 
            v.risk_level,
            COUNT(*) as count,
            AVG(v.risk_score) as avg_score
         FROM `wallet_verifications` v
         {$whereClause}
         GROUP BY v.risk_level"
    );
    $stmt->execute($params);
    $riskDistribution = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Average verification time by status
    $stmt = $db->prepare(
        "SELECT 
            v.status,
            AVG(TIMESTAMPDIFF(SECOND, v.created_at, COALESCE(v.verified_at, NOW()))) as avg_time_seconds
         FROM `wallet_verifications` v
         {$whereClause}
         GROUP BY v.status"
    );
    $stmt->execute($params);
    $avgTimeByStatus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    Response::jsonSuccess([
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'overall_metrics' => [
            'total_verifications' => (int) $overall['total_verifications'],
            'approved_count' => (int) $overall['approved_count'],
            'rejected_count' => (int) $overall['rejected_count'],
            'approval_rate' => $overall['total_verifications'] > 0 
                ? round((int) $overall['approved_count'] / (int) $overall['total_verifications'] * 100, 2)
                : 0,
            'unique_users' => (int) $overall['unique_users'],
            'unique_wallets' => (int) $overall['unique_wallets'],
            'avg_risk_score' => round((float) $overall['avg_risk_score'], 2)
        ],
        'risk_metrics' => [
            'high_risk_count' => (int) $overall['high_risk_count'],
            'medium_risk_count' => (int) $overall['medium_risk_count'],
            'low_risk_count' => (int) $overall['low_risk_count'],
            'high_risk_percentage' => $overall['total_verifications'] > 0
                ? round((int) $overall['high_risk_count'] / (int) $overall['total_verifications'] * 100, 2)
                : 0
        ],
        'security_metrics' => [
            'total_failed_attempts' => (int) $failedAttempts['total_failed_attempts'],
            'admin_override_count' => (int) $adminOverrides['admin_override_count'],
            'high_risk_verifications' => (int) $complianceFlags['high_risk_verifications']
        ],
        'method_distribution' => $methodDistribution,
        'risk_distribution' => $riskDistribution,
        'average_verification_time' => $avgTimeByStatus
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while fetching compliance statistics', 500);
}

