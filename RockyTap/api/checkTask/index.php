<?php

declare(strict_types=1);

/**
 * Check Task API endpoint for Ghidar
 * Checks task completion status.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Config\Config;
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
        echo '{"status": "wait"}';
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null || !isset($data['taskId'])) {
        echo '{"status": "wait"}';
        exit;
    }

    // Validate taskId is a positive integer
    try {
        $taskId = Validator::requirePositiveInt($data['taskId'], 1, PHP_INT_MAX);
    } catch (\InvalidArgumentException $e) {
        echo '{"status": "wait"}';
        exit;
    }

    $pdo = Database::ensureConnection();

    // Get task time
    $stmt = $pdo->prepare(
        'SELECT `check_time` FROM `user_tasks` 
         WHERE `user_id` = :user_id 
           AND `task_id` = :task_id 
         LIMIT 1'
    );
    $stmt->execute([
        'user_id' => $userId,
        'task_id' => $taskId,
    ]);
    $taskData = $stmt->fetch(\PDO::FETCH_ASSOC);
    $get_task_time = $taskData ? (int) $taskData['check_time'] : 0;

    // Get task details
    $stmt = $pdo->prepare('SELECT * FROM `tasks` WHERE `id` = :id LIMIT 1');
    $stmt->execute(['id' => $taskId]);
    $get_task = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$get_task) {
        echo '{"status": "wait"}';
        exit;
    }

    $taskType = (int) $get_task['type'];

    if ($taskType == 1) {
        // Type 1: Check Telegram chat membership
        $apiKey = Config::get('TELEGRAM_BOT_TOKEN');
        $chatId = $get_task['url'] ?? '';
        
        if (empty($apiKey) || empty($chatId)) {
            echo '{"status": "wait"}';
            exit;
        }

        $url = "https://api.telegram.org/bot{$apiKey}/getChatMember?chat_id={$chatId}&user_id={$userId}";
        $result = json_decode(file_get_contents($url), false);

        if ($result && $result->ok && in_array($result->result->status, ['member', 'administrator'])) {
            $stmt = $pdo->prepare(
                'UPDATE `user_tasks` 
                 SET `status` = 3 
                 WHERE `user_id` = :user_id 
                   AND `task_id` = :task_id 
                 LIMIT 1'
            );
            $stmt->execute([
                'user_id' => $userId,
                'task_id' => $taskId,
            ]);
            echo '{"status": "ok"}';
        } else {
            echo '{"status": "wait"}';
        }
    } else {
        // Type 0 or other: Mark as completed
        $stmt = $pdo->prepare(
            'UPDATE `user_tasks` 
             SET `status` = 3 
             WHERE `user_id` = :user_id 
               AND `task_id` = :task_id 
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'task_id' => $taskId,
        ]);
        echo '{"status": "ok"}';
    }

} catch (\RuntimeException $e) {
    echo '{"status": "wait"}';
} catch (\Exception $e) {
    error_log('CheckTask error: ' . $e->getMessage());
    echo '{"status": "wait"}';
}
