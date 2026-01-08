<?php

declare(strict_types=1);

/**
 * Admin Deposit List API
 * 
 * Returns list of deposits (pending, confirmed, etc.)
 * Protected by ADMIN_MONITOR_KEY header.
 * 
 * GET /api/payments/deposit/list/
 * GET /api/payments/deposit/list/?status=pending
 * GET /api/payments/deposit/list/?user_id=123
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Payments\PaymentsConfig;

try {
    // Verify admin token
    $headers = getallheaders();
    if ($headers === false) {
        Response::jsonError('UNAUTHORIZED', 'Invalid request headers', 401);
        exit;
    }

    $adminKey = null;
    foreach (['X-Admin-Key', 'x-admin-key', 'X-ADMIN-KEY'] as $header) {
        if (isset($headers[$header])) {
            $adminKey = $headers[$header];
            break;
        }
    }

    if ($adminKey === null) {
        Response::jsonError('UNAUTHORIZED', 'Missing X-Admin-Key header', 401);
        exit;
    }

    $expectedKey = Config::get('ADMIN_MONITOR_KEY');
    if ($expectedKey === null || $expectedKey === '') {
        Response::jsonError('INTERNAL_ERROR', 'Admin key not configured', 500);
        exit;
    }

    if (!hash_equals($expectedKey, $adminKey)) {
        Response::jsonError('UNAUTHORIZED', 'Invalid admin key', 401);
        exit;
    }

    // Parse query parameters
    $status = $_GET['status'] ?? null;
    $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 50;
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

    $db = Database::ensureConnection();

    // Build query
    $where = [];
    $params = [];

    if ($status !== null && in_array($status, PaymentsConfig::DEPOSIT_STATUSES, true)) {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }

    if ($userId !== null) {
        $where[] = 'user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM deposits {$whereClause}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['total'];

    // Get deposits
    $sql = "SELECT d.*, u.username, u.first_name, u.telegram_id
            FROM deposits d
            LEFT JOIN users u ON d.user_id = u.id
            {$whereClause}
            ORDER BY d.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    $deposits = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Format response
    $formattedDeposits = array_map(function ($deposit) {
        return [
            'id' => (int) $deposit['id'],
            'user_id' => (int) $deposit['user_id'],
            'telegram_id' => $deposit['telegram_id'] ?? null,
            'username' => $deposit['username'] ?? null,
            'first_name' => $deposit['first_name'] ?? null,
            'network' => $deposit['network'],
            'product_type' => $deposit['product_type'],
            'status' => $deposit['status'],
            'address' => $deposit['address'],
            'expected_amount_usdt' => $deposit['expected_amount_usdt'],
            'actual_amount_usdt' => $deposit['actual_amount_usdt'],
            'tx_hash' => $deposit['tx_hash'],
            'meta' => $deposit['meta'] ? json_decode($deposit['meta'], true) : null,
            'created_at' => $deposit['created_at'],
            'confirmed_at' => $deposit['confirmed_at'],
        ];
    }, $deposits);

    Response::jsonSuccess([
        'deposits' => $formattedDeposits,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

