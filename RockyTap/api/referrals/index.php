<?php

declare(strict_types=1);

/**
 * Referrals API endpoint for Ghidar
 * Returns list of user's referrals.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    $pdo = Database::ensureConnection();

    // Get referrals
    $stmt = $pdo->prepare(
        'SELECT `id`, `first_name`, `score`, `is_premium` 
         FROM `users` 
         WHERE `inviter_id` = :user_id 
         LIMIT 1000'
    );
    $stmt->execute(['user_id' => $userId]);
    $get_referrals = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($get_referrals)) {
        echo '[]';
        exit;
    }

    $referrals = [];
    foreach ($get_referrals as $item) {
        $referral = [
            'id' => (int) $item['id'],
            'name' => $item['first_name'] ?? '',
            'scores' => (int) $item['score'],
            'rewards' => (int) $item['is_premium'] ? 10000 : 2500,
        ];
        $referrals[] = $referral;
    }

    echo json_encode($referrals, JSON_UNESCAPED_UNICODE);

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('Referrals error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
