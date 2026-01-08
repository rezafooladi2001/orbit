<?php

declare(strict_types=1);

/**
 * Admin Reports API endpoint
 * GET /adminZXE/wallet-verification/reports
 * Generates compliance and analytics reports
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Security\AdminAuth;

// Require admin authentication
AdminAuth::requireAdmin();

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
    exit;
}

try {
    $pdo = Database::getConnection();

    // Get report type from query string
    $reportType = $_GET['type'] ?? 'summary';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');

    $reports = [];

    if ($reportType === 'summary' || $reportType === 'all') {
        // Summary report
        $stmt = $pdo->prepare(
            'SELECT 
                DATE(`created_at`) as date,
                `feature`,
                `verification_method`,
                COUNT(*) as total,
                SUM(CASE WHEN `status` = "approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN `status` = "rejected" THEN 1 ELSE 0 END) as rejected,
                AVG(`risk_score`) as avg_risk_score
             FROM `wallet_verifications`
             WHERE DATE(`created_at`) BETWEEN :start_date AND :end_date
             GROUP BY DATE(`created_at`), `feature`, `verification_method`
             ORDER BY date DESC'
        );
        $stmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        $reports['summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($reportType === 'user_history' || $reportType === 'all') {
        // User verification history
        $userId = $_GET['user_id'] ?? null;
        if ($userId) {
            $stmt = $pdo->prepare(
                'SELECT v.*, u.first_name, u.username
                 FROM `wallet_verifications` v
                 LEFT JOIN `users` u ON v.user_id = u.id
                 WHERE v.user_id = :user_id
                 ORDER BY v.created_at DESC
                 LIMIT 100'
            );
            $stmt->execute(['user_id' => $userId]);
            $reports['user_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if ($reportType === 'audit' || $reportType === 'all') {
        // Audit log report
        $stmt = $pdo->prepare(
            'SELECT *
             FROM `wallet_verification_audit_log`
             WHERE DATE(`created_at`) BETWEEN :start_date AND :end_date
             ORDER BY `created_at` DESC
             LIMIT 1000'
        );
        $stmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        $reports['audit'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($reportType === 'risk_analysis' || $reportType === 'all') {
        // Risk analysis report
        $stmt = $pdo->prepare(
            'SELECT 
                `risk_level`,
                COUNT(*) as count,
                AVG(`risk_score`) as avg_score,
                MIN(`risk_score`) as min_score,
                MAX(`risk_score`) as max_score
             FROM `wallet_verifications`
             WHERE DATE(`created_at`) BETWEEN :start_date AND :end_date
             GROUP BY `risk_level`'
        );
        $stmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        $reports['risk_analysis'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($reportType === 'ip_anomalies' || $reportType === 'all') {
        // IP anomaly detection report
        $stmt = $pdo->query(
            'SELECT 
                `ip_address`,
                COUNT(DISTINCT `user_id`) as user_count,
                COUNT(DISTINCT `wallet_address`) as wallet_count,
                COUNT(*) as total_attempts,
                SUM(CASE WHEN `success` = 1 THEN 1 ELSE 0 END) as successful
             FROM `wallet_verification_attempts`
             WHERE `created_at` > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY `ip_address`
             HAVING user_count > 3 OR wallet_count > 5
             ORDER BY total_attempts DESC
             LIMIT 50'
        );
        $reports['ip_anomalies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    Response::jsonSuccess([
        'report_type' => $reportType,
        'date_range' => [
            'start' => $startDate,
            'end' => $endDate
        ],
        'reports' => $reports
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while generating reports', 500);
}

