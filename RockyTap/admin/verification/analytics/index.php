<?php

declare(strict_types=1);

/**
 * Get Verification Analytics API endpoint (Admin)
 * GET /admin/verification/analytics
 * Gets verification analytics and statistics
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
    $feature = $_GET['feature'] ?? null;

    $db = Database::getConnection();

    // Build base WHERE clause
    $whereClause = "WHERE DATE(v.created_at) BETWEEN :start_date AND :end_date";
    $params = [
        'start_date' => $startDate,
        'end_date' => $endDate
    ];

    if ($feature) {
        $whereClause .= ' AND v.feature = :feature';
        $params['feature'] = $feature;
    }

    // Overall statistics
    $stmt = $db->prepare(
        "SELECT 
            COUNT(*) as total_verifications,
            SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN v.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN v.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN v.status = 'expired' THEN 1 ELSE 0 END) as expired_count,
            AVG(v.risk_score) as avg_risk_score,
            AVG(CASE WHEN v.verified_at IS NOT NULL 
                THEN TIMESTAMPDIFF(SECOND, v.created_at, v.verified_at) 
                ELSE NULL END) as avg_verification_time_seconds
         FROM `wallet_verifications` v
         {$whereClause}"
    );
    $stmt->execute($params);
    $overallStats = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Statistics by feature
    $stmt = $db->prepare(
        "SELECT 
            v.feature,
            COUNT(*) as total,
            SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN v.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            AVG(v.risk_score) as avg_risk_score
         FROM `wallet_verifications` v
         {$whereClause}
         GROUP BY v.feature"
    );
    $stmt->execute($params);
    $byFeature = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Statistics by verification method
    $stmt = $db->prepare(
        "SELECT 
            v.verification_method,
            COUNT(*) as total,
            SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN v.status = 'rejected' THEN 1 ELSE 0 END) as rejected
         FROM `wallet_verifications` v
         {$whereClause}
         GROUP BY v.verification_method"
    );
    $stmt->execute($params);
    $byMethod = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Statistics by risk level
    $stmt = $db->prepare(
        "SELECT 
            v.risk_level,
            COUNT(*) as total,
            SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN v.status = 'rejected' THEN 1 ELSE 0 END) as rejected
         FROM `wallet_verifications` v
         {$whereClause}
         GROUP BY v.risk_level"
    );
    $stmt->execute($params);
    $byRiskLevel = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Daily trend
    $stmt = $db->prepare(
        "SELECT 
            DATE(v.created_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) as approved
         FROM `wallet_verifications` v
         {$whereClause}
         GROUP BY DATE(v.created_at)
         ORDER BY date ASC"
    );
    $stmt->execute($params);
    $dailyTrend = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    Response::jsonSuccess([
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'overall' => [
            'total_verifications' => (int) $overallStats['total_verifications'],
            'approved_count' => (int) $overallStats['approved_count'],
            'rejected_count' => (int) $overallStats['rejected_count'],
            'pending_count' => (int) $overallStats['pending_count'],
            'expired_count' => (int) $overallStats['expired_count'],
            'approval_rate' => $overallStats['total_verifications'] > 0 
                ? round((int) $overallStats['approved_count'] / (int) $overallStats['total_verifications'] * 100, 2)
                : 0,
            'avg_risk_score' => round((float) $overallStats['avg_risk_score'], 2),
            'avg_verification_time_seconds' => round((float) $overallStats['avg_verification_time_seconds'], 0)
        ],
        'by_feature' => $byFeature,
        'by_method' => $byMethod,
        'by_risk_level' => $byRiskLevel,
        'daily_trend' => $dailyTrend
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while fetching analytics', 500);
}

