<?php

declare(strict_types=1);

/**
 * Finish Mission API endpoint for Ghidar
 * Completes a mission and awards the reward to the user.
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
    if ($data === null || !isset($data['missionId'])) {
        Response::jsonErrorLegacy('invalid_input', 'missionId is required', 400);
        exit;
    }

    // Validate missionId is a positive integer
    try {
        $missionId = Validator::requirePositiveInt($data['missionId'], 1, PHP_INT_MAX);
    } catch (\InvalidArgumentException $e) {
        Response::jsonErrorLegacy('invalid_mission_id', $e->getMessage(), 400);
        exit;
    }

    $pdo = Database::ensureConnection();

    try {
        $pdo->beginTransaction();

        // Update mission status
        $stmt = $pdo->prepare(
            'UPDATE `user_missions` 
             SET `status` = 2 
             WHERE `mission_id` = :mission_id 
               AND `user_id` = :user_id 
             LIMIT 1'
        );
        $stmt->execute([
            'mission_id' => $missionId,
            'user_id' => $userId,
        ]);

        // Get mission reward
        $stmt = $pdo->prepare('SELECT `reward` FROM `missions` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $missionId]);
        $mission = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($mission === false) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('mission_not_found', 'Mission not found', 404);
            exit;
        }

        $reward = (int) $mission['reward'];

        // Validate reward is positive
        if ($reward <= 0) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('invalid_reward', 'Invalid reward amount', 400);
            exit;
        }

        // Update user balance
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `balance` = `balance` + :reward 
             WHERE `id` = :user_id 
             LIMIT 1'
        );
        $stmt->execute([
            'reward' => $reward,
            'user_id' => $userId,
        ]);

        $pdo->commit();

        Response::jsonSuccessLegacy(['ok' => true, 'reward' => $reward]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('FinishMission error: ' . $e->getMessage());
        Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
    }

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('FinishMission error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
