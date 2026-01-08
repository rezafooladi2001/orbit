<?php

declare(strict_types=1);

/**
 * Claim League Task API endpoint for Ghidar
 * Claims reward for reaching a league milestone.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Validation\Validator;

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonErrorLegacy('invalid_input', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null || !isset($data['league'])) {
        Response::jsonErrorLegacy('invalid_input', 'league is required', 400);
        exit;
    }

    $league = (string) $data['league'];

    // Define valid leagues and prizes
    $league_prize = [
        'bronze' => 1000,
        'silver' => 5000,
        'gold' => 10000,
        'platinum' => 30000,
        'diamond' => 50000,
        'master' => 100000,
        'grandmaster' => 250000,
        'elite' => 500000,
        'legendary' => 1000000,
    ];

    // Validate league
    if (!isset($league_prize[$league])) {
        Response::jsonErrorLegacy('invalid_league', 'Invalid league', 400);
        exit;
    }

    $reward = $league_prize[$league];

    $pdo = Database::ensureConnection();

    try {
        $pdo->beginTransaction();

        // Check if reward already claimed
        $stmt = $pdo->prepare(
            'SELECT `id` FROM `leaguesTasks` 
             WHERE `user_id` = :user_id 
               AND `league` = :league 
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'league' => $league,
        ]);

        if ($stmt->fetch() !== false) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('already_claimed', 'This reward has already been claimed', 422);
            exit;
        }

        // Get current user balance
        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $userData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($userData === false) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
            exit;
        }

        // Calculate new balance
        $currentBalance = (int) $userData['balance'];
        $newBalance = $currentBalance + $reward;

        // Ensure balance is non-negative
        if ($newBalance < 0) {
            $newBalance = 0;
        }

        // Update user balance
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `balance` = :balance 
             WHERE `id` = :user_id 
             LIMIT 1'
        );
        $stmt->execute([
            'balance' => $newBalance,
            'user_id' => $userId,
        ]);

        // Insert league task record
        $stmt = $pdo->prepare(
            'INSERT INTO `leaguesTasks` (`user_id`, `league`) 
             VALUES (:user_id, :league)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'league' => $league,
        ]);

        $pdo->commit();

        Response::jsonSuccessLegacy(['ok' => true, 'reward' => $reward]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('ClaimLeagueTask error: ' . $e->getMessage());
        Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
    }

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('ClaimLeagueTask error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
