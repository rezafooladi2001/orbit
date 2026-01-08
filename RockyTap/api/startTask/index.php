<?php

declare(strict_types=1);

/**
 * Start Task API endpoint for Ghidar
 * Records that a user has started a task.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
use Ghidar\Validation\Validator;

try {
    // Authenticate user using Telegram initData
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: 30 requests per 60 seconds
    if (!RateLimiter::checkAndIncrement($userId, 'start_task', 30, 60)) {
        Response::jsonErrorLegacy('rate_limit_exceeded', 'Too many requests', 429);
        exit;
    }

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonErrorLegacy('invalid_input', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null) {
        Response::jsonErrorLegacy('invalid_json', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate taskId
    if (!isset($data['taskId'])) {
        Response::jsonErrorLegacy('missing_task_id', 'taskId is required', 400);
        exit;
    }

    try {
        // Validate task ID is a positive integer within reasonable bounds
        $taskId = Validator::requirePositiveInt($data['taskId'], 1, 1000000);
    } catch (\InvalidArgumentException $e) {
        Response::jsonErrorLegacy('invalid_task_id', $e->getMessage(), 400);
        exit;
    }

    $pdo = Database::ensureConnection();

    // Verify user exists
    $stmt = $pdo->prepare('SELECT `id` FROM `users` WHERE `id` = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$get_user) {
        Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
        exit;
    }

    // Check if task exists
    $stmt = $pdo->prepare('SELECT `id` FROM `tasks` WHERE `id` = :task_id LIMIT 1');
    $stmt->execute(['task_id' => $taskId]);
    $task = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$task) {
        Response::jsonErrorLegacy('task_not_found', 'Task not found', 404);
        exit;
    }

    // Check if user already started this task
    $stmt = $pdo->prepare(
        'SELECT `id` FROM `user_tasks` WHERE `user_id` = :user_id AND `task_id` = :task_id LIMIT 1'
    );
    $stmt->execute([
        'user_id' => $userId,
        'task_id' => $taskId,
    ]);
    $existingTask = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($existingTask) {
        // Task already started, return success without inserting duplicate
        Response::jsonSuccessLegacy([
            'message' => 'Task already started',
            'taskId' => $taskId,
        ]);
        exit;
    }

    // Insert user task record
    $now = time();
    $stmt = $pdo->prepare(
        'INSERT INTO `user_tasks` (`user_id`, `task_id`, `status`, `check_time`) 
         VALUES (:user_id, :task_id, :status, :check_time)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'task_id' => $taskId,
        'status' => 1,
        'check_time' => $now,
    ]);

    Response::jsonSuccessLegacy([
        'message' => 'Task started',
        'taskId' => $taskId,
    ]);

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('StartTask endpoint error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
