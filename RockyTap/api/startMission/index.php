<?php

declare(strict_types=1);

/**
 * Start Mission API endpoint for Ghidar
 * Starts a mission for the authenticated user.
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

    // Insert user mission record
    $stmt = $pdo->prepare(
        'INSERT INTO `user_missions` (`user_id`, `mission_id`, `status`) 
         VALUES (:user_id, :mission_id, 1)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'mission_id' => $missionId,
    ]);

    Response::jsonSuccessLegacy(['ok' => true]);

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\PDOException $e) {
    error_log('StartMission error: ' . $e->getMessage());
    Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
} catch (\Exception $e) {
    error_log('StartMission error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
