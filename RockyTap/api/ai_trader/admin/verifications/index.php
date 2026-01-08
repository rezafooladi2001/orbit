<?php

declare(strict_types=1);

/**
 * Admin API endpoint for viewing withdrawal verifications
 * Lists all withdrawal verifications with filtering options.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

// Simple admin check - in production, implement proper admin authentication
function isAdmin(int $userId): bool
{
    $adminIds = [125125166]; // Add admin user IDs here
    return in_array($userId, $adminIds);
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    if (!isAdmin($userId)) {
        Response::jsonError('UNAUTHORIZED', 'Admin access required', 403);
        exit;
    }

    $db = Database::ensureConnection();

    // Get query parameters
    $status = $_GET['status'] ?? null;
    $tier = $_GET['tier'] ?? null;
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

    // Build query
    $whereConditions = [];
    $params = [];

    if ($status !== null) {
        $whereConditions[] = '`status` = :status';
        $params['status'] = $status;
    }

    if ($tier !== null) {
        $whereConditions[] = '`verification_tier` = :tier';
        $params['tier'] = $tier;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $stmt = $db->prepare(
        "SELECT * FROM `ai_withdrawal_verifications` 
         {$whereClause}
         ORDER BY `created_at` DESC 
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();

    $verifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM `ai_withdrawal_verifications` {$whereClause}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

    Response::jsonSuccess([
        'verifications' => $verifications,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

