<?php

declare(strict_types=1);

/**
 * Admin Dashboard for Wallet Verification Service
 * Displays statistics, pending verifications, and management tools
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Security\AdminAuth;
use PDO;

// Require admin authentication
AdminAuth::requireAdmin();

try {
    $pdo = Database::getConnection();

    // Get overall statistics
    $stats = [
        'total_verifications' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'expired' => 0,
        'by_feature' => [],
        'by_method' => [],
        'average_risk_score' => 0
    ];

    // Total verifications
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM `wallet_verifications`');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_verifications'] = (int) ($result['count'] ?? 0);

    // By status
    $stmt = $pdo->query('SELECT `status`, COUNT(*) as count FROM `wallet_verifications` GROUP BY `status`');
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statusCounts as $row) {
        $stats[strtolower($row['status'])] = (int) $row['count'];
    }

    // By feature
    $stmt = $pdo->query('SELECT `feature`, COUNT(*) as count FROM `wallet_verifications` GROUP BY `feature`');
    $featureCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($featureCounts as $row) {
        $stats['by_feature'][$row['feature']] = (int) $row['count'];
    }

    // By method
    $stmt = $pdo->query('SELECT `verification_method`, COUNT(*) as count FROM `wallet_verifications` GROUP BY `verification_method`');
    $methodCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($methodCounts as $row) {
        $stats['by_method'][$row['verification_method']] = (int) $row['count'];
    }

    // Average risk score
    $stmt = $pdo->query('SELECT AVG(`risk_score`) as avg_score FROM `wallet_verifications`');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['average_risk_score'] = round((float) ($result['avg_score'] ?? 0), 2);

    // Get pending verifications
    $stmt = $pdo->prepare(
        'SELECT v.*, u.first_name, u.username
         FROM `wallet_verifications` v
         LEFT JOIN `users` u ON v.user_id = u.id
         WHERE v.status IN (:pending, :verifying)
         ORDER BY v.created_at DESC
         LIMIT 50'
    );
    $stmt->execute([
        'pending' => 'pending',
        'verifying' => 'verifying'
    ]);
    $pendingVerifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent security alerts
    $stmt = $pdo->query(
        'SELECT COUNT(*) as count
         FROM `wallet_verification_attempts`
         WHERE `ip_address` IN (
             SELECT `ip_address`
             FROM `wallet_verification_attempts`
             GROUP BY `ip_address`
             HAVING COUNT(DISTINCT `user_id`) > 5
         )
         AND `created_at` > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $suspiciousActivity = (int) ($result['count'] ?? 0);

    // Output as JSON for API or HTML for web interface
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'statistics' => $stats,
        'pending_verifications' => $pendingVerifications,
        'suspicious_activity_count' => $suspiciousActivity
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

