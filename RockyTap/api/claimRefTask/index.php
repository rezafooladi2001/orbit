<?php

declare(strict_types=1);

/**
 * Claim Referral Task API endpoint for Ghidar
 * Claims reward for reaching a referral milestone.
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
    if ($data === null || !isset($data['refLevel'])) {
        Response::jsonErrorLegacy('invalid_input', 'refLevel is required', 400);
        exit;
    }

    $refLevel = (string) $data['refLevel'];

    // Define valid ref levels and prizes
    $ref_prize = [
        '1' => 5000,
        '3' => 100000,
        '10' => 400000,
        '25' => 500000,
        '50' => 600000,
        '100' => 1000000,
        '500' => 4000000,
        '1000' => 2500000,
        '10000' => 10000000,
        '100000' => 100000000,
    ];

    // Validate refLevel
    if (!isset($ref_prize[$refLevel])) {
        Response::jsonErrorLegacy('invalid_ref_level', 'Invalid referral level', 400);
        exit;
    }

    $reward = $ref_prize[$refLevel];

    $pdo = Database::ensureConnection();

    try {
        $pdo->beginTransaction();

        // Check if reward already claimed
        $stmt = $pdo->prepare(
            'SELECT `id` FROM `refTasks` 
             WHERE `user_id` = :user_id 
               AND `refLevel` = :ref_level 
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'ref_level' => $refLevel,
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

        // Calculate new balance (ensure non-negative)
        $currentBalance = abs((int) $userData['balance']);
        $newBalance = $currentBalance + $reward;

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

        // Insert ref task record
        $stmt = $pdo->prepare(
            'INSERT INTO `refTasks` (`user_id`, `refLevel`) 
             VALUES (:user_id, :ref_level)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'ref_level' => $refLevel,
        ]);

        $pdo->commit();

        Response::jsonSuccessLegacy(['ok' => true, 'reward' => $reward]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('ClaimRefTask error: ' . $e->getMessage());
        Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
    }

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('ClaimRefTask error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
