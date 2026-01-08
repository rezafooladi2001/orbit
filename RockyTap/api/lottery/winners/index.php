<?php

declare(strict_types=1);

/**
 * Lottery Winners API endpoint for Ghidar
 * Returns winners for a given lottery.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Lottery\LotteryService;

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();

    // Get lottery_id from GET or JSON input
    $lotteryId = null;

    if (isset($_GET['lottery_id']) && is_numeric($_GET['lottery_id'])) {
        $lotteryId = (int) $_GET['lottery_id'];
    } else {
        // Try JSON body
        $input = file_get_contents('php://input');
        if ($input !== false) {
            $data = json_decode($input, true);
            if ($data !== null && isset($data['lottery_id']) && is_numeric($data['lottery_id'])) {
                $lotteryId = (int) $data['lottery_id'];
            }
        }
    }

    if ($lotteryId === null || $lotteryId <= 0) {
        Response::jsonError('MISSING_LOTTERY_ID', 'lottery_id is required and must be a positive integer', 400);
        exit;
    }

    // Get lottery info
    $db = \Ghidar\Core\Database::ensureConnection();
    $stmt = $db->prepare('SELECT * FROM lotteries WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $lotteryId]);
    $lottery = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($lottery === false) {
        Response::jsonError('LOTTERY_NOT_FOUND', 'Lottery not found', 404);
        exit;
    }

    // Get winners for the lottery
    $winners = LotteryService::getWinners($lotteryId);

    // Format winners for response
    $formattedWinners = [];
    $rank = 1;
    foreach ($winners as $winner) {
        $formattedWinners[] = [
            'id' => $rank,
            'telegram_id' => $winner['user_id'],
            'username' => $winner['username'] ?? null,
            'first_name' => null, // Not included in getWinners
            'prize_amount_usdt' => $winner['prize_amount_usdt'],
            'rank' => $rank
        ];
        $rank++;
    }

    Response::jsonSuccess([
        'lottery' => [
            'id' => (int) $lottery['id'],
            'title' => $lottery['title'],
            'type' => $lottery['type'],
            'prize_pool_usdt' => (string) $lottery['prize_pool_usdt'],
            'status' => $lottery['status']
        ],
        'winners' => $formattedWinners
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

