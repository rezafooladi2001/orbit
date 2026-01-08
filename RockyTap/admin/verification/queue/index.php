<?php

declare(strict_types=1);

/**
 * Get Verification Queue API endpoint (Admin)
 * GET /admin/verification/queue
 * Gets pending verifications for admin review
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;
use Ghidar\Security\WalletVerificationService;

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

    // Get query parameters
    $status = $_GET['status'] ?? WalletVerificationService::STATUS_PENDING;
    $feature = $_GET['feature'] ?? null;
    $method = $_GET['method'] ?? null;
    $riskLevel = $_GET['risk_level'] ?? null;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

    // Validate pagination
    if ($limit < 1 || $limit > 100) {
        $limit = 50;
    }
    if ($offset < 0) {
        $offset = 0;
    }

    $db = Database::getConnection();

    // Build query
    $sql = 'SELECT v.*, u.first_name, u.username
            FROM `wallet_verifications` v
            LEFT JOIN `users` u ON v.user_id = u.id
            WHERE 1=1';
    $params = [];

    if ($status) {
        $sql .= ' AND v.status = :status';
        $params['status'] = $status;
    }

    if ($feature) {
        $sql .= ' AND v.feature = :feature';
        $params['feature'] = $feature;
    }

    if ($method) {
        $sql .= ' AND v.verification_method = :method';
        $params['method'] = $method;
    }

    if ($riskLevel) {
        $sql .= ' AND v.risk_level = :risk_level';
        $params['risk_level'] = $riskLevel;
    }

    $sql .= ' ORDER BY v.created_at DESC LIMIT :limit OFFSET :offset';

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    $verifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Get total count
    $countSql = 'SELECT COUNT(*) as total FROM `wallet_verifications` v WHERE 1=1';
    $countParams = [];
    foreach ($params as $key => $value) {
        if ($key !== 'limit' && $key !== 'offset') {
            $countSql .= ' AND v.' . $key . ' = :' . $key;
            $countParams[$key] = $value;
        }
    }
    $countStmt = $db->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

    Response::jsonSuccess([
        'verifications' => $verifications,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'filters' => [
            'status' => $status,
            'feature' => $feature,
            'method' => $method,
            'risk_level' => $riskLevel
        ]
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while fetching verification queue', 500);
}

