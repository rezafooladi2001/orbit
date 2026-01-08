<?php
/**
 * Referral History API endpoint for Ghidar
 * Returns user's referral reward history with pagination.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

try {
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(max(1, (int) $_GET['limit']), 100) : 20;
    $offset = ($page - 1) * $limit;

    $db = Database::ensureConnection();

    // Get total count
    $stmt = $db->prepare('SELECT COUNT(*) as total FROM `referral_rewards` WHERE `user_id` = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult !== false ? (int) $totalResult['total'] : 0;

    // Get rewards
    $stmt = $db->prepare(
        'SELECT `from_user_id`, `level`, `amount_usdt`, `source_type`, `source_id`, `created_at`
         FROM `referral_rewards`
         WHERE `user_id` = :user_id
         ORDER BY `created_at` DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format rewards
    $formattedRewards = [];
    foreach ($rewards as $reward) {
        $formattedRewards[] = [
            'from_user_id' => (int) $reward['from_user_id'],
            'level' => (int) $reward['level'],
            'amount_usdt' => $reward['amount_usdt'],
            'source_type' => $reward['source_type'],
            'source_id' => $reward['source_id'] !== null ? (int) $reward['source_id'] : null,
            'created_at' => $reward['created_at']
        ];
    }

    Response::jsonSuccess([
        'rewards' => $formattedRewards,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) ceil($total / $limit)
        ]
    ]);
} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log("Referral history API error: " . $e->getMessage());
    Response::jsonError('SERVER_ERROR', 'Failed to load referral history', 500);
}

