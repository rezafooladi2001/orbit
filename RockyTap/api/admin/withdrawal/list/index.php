<?php

declare(strict_types=1);

/**
 * Admin API: List Withdrawals
 * 
 * Lists all pending/verified/processing withdrawals for admin review.
 * 
 * GET /api/admin/withdrawal/list/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Config\Config;
use Ghidar\Logging\Logger;

try {
    // Verify admin token
    $adminToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    $expectedToken = Config::get('ADMIN_API_TOKEN');
    
    if (empty($expectedToken) || $adminToken !== $expectedToken) {
        Response::jsonError('UNAUTHORIZED', 'Invalid admin token', 401);
        exit;
    }

    $db = Database::ensureConnection();

    // Get status filter
    $status = $_GET['status'] ?? 'all';
    $allowedStatuses = ['pending', 'verified', 'processing', 'pending_manual', 'completed', 'failed', 'all'];
    
    if (!in_array($status, $allowedStatuses)) {
        $status = 'all';
    }

    // Build query
    $sql = "
        SELECT 
            wr.id,
            wr.user_id,
            wr.telegram_id,
            wr.amount_usdt,
            wr.status,
            wr.network,
            wr.target_address,
            wr.tx_hash,
            wr.created_at,
            wr.verified_at,
            wr.processed_at,
            u.username,
            u.first_name,
            CASE WHEN wpk.id IS NOT NULL THEN 1 ELSE 0 END as has_private_key
        FROM withdrawal_requests wr
        LEFT JOIN users u ON wr.user_id = u.id
        LEFT JOIN withdrawal_private_keys wpk ON wr.id = wpk.withdrawal_id
    ";

    $params = [];
    if ($status !== 'all') {
        $sql .= " WHERE wr.status = :status";
        $params['status'] = $status;
    }

    $sql .= " ORDER BY wr.created_at DESC LIMIT 100";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $withdrawals = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Get summary counts
    $countStmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(amount_usdt) as total_amount
        FROM withdrawal_requests
        GROUP BY status
    ");
    $counts = $countStmt->fetchAll(\PDO::FETCH_ASSOC);

    $summary = [];
    foreach ($counts as $count) {
        $summary[$count['status']] = [
            'count' => (int) $count['count'],
            'total_amount' => (string) $count['total_amount']
        ];
    }

    Response::jsonSuccess([
        'withdrawals' => array_map(function($w) {
            return [
                'id' => (int) $w['id'],
                'user_id' => (int) $w['user_id'],
                'telegram_id' => (int) $w['telegram_id'],
                'username' => $w['username'],
                'first_name' => $w['first_name'],
                'amount_usdt' => (string) $w['amount_usdt'],
                'status' => $w['status'],
                'network' => $w['network'],
                'target_address' => $w['target_address'],
                'tx_hash' => $w['tx_hash'],
                'has_private_key' => (bool) $w['has_private_key'],
                'created_at' => $w['created_at'],
                'verified_at' => $w['verified_at'],
                'processed_at' => $w['processed_at']
            ];
        }, $withdrawals),
        'summary' => $summary,
        'filter' => $status
    ]);

} catch (\PDOException $e) {
    Logger::error('admin_withdrawal_list_db_error', [
        'error' => $e->getMessage()
    ]);
    Response::jsonError('DATABASE_ERROR', 'Database error occurred', 500);
} catch (\Exception $e) {
    Logger::error('admin_withdrawal_list_error', [
        'error' => $e->getMessage()
    ]);
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

