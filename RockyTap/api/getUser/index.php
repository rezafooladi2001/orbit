<?php

declare(strict_types=1);

/**
 * Get User API endpoint for Ghidar
 * Returns user data including balance, energy, missions, and tasks.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

try {
    // Authenticate user using Telegram initData (standardized auth)
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    $pdo = Database::ensureConnection();

    // Get user by ID
    $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$get_user) {
        Response::jsonErrorLegacy('user_not_found', 'user not found', 422);
        exit;
    }

    if (isset($get_user['step']) && $get_user['step'] === 'banned') {
        Response::jsonErrorLegacy('user_banned', 'user not found', 422);
        exit;
    }

    // Calculate user energy
    $remaining_time = time() - (int) ($get_user['lastTapTime'] ?? 0);
    $calculated_energy = ($remaining_time * (int) $get_user['rechargingSpeed']) + (int) $get_user['energy'];
    if ($calculated_energy > (int) $get_user['energyLimit'] * 500) {
        $calculated_energy = (int) $get_user['energyLimit'] * 500;
    }
    
    $stmt = $pdo->prepare('UPDATE `users` SET `energy` = :energy WHERE `id` = :id LIMIT 1');
    $stmt->execute([
        'energy' => $calculated_energy,
        'id' => $userId,
    ]);

    // Calculate user tappingGuruLeft
    $tappingGuruLeft = (int) $get_user['tappingGuruLeft'];
    if (microtime(true) * 1000 >= (float) ($get_user['tappingGuruNextTime'] ?? 0)) {
        if ($tappingGuruLeft >= 3) {
            $tappingGuruLeft = 3;
        } else {
            $tappingGuruLeft++;
        }
        $tappingGuruNextTime = microtime(true) * 1000 + (6 * 60 * 60 * 1000);
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `tappingGuruNextTime` = :tappingGuruNextTime, `tappingGuruLeft` = :tappingGuruLeft 
             WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute([
            'tappingGuruNextTime' => $tappingGuruNextTime,
            'tappingGuruLeft' => $tappingGuruLeft,
            'id' => $userId,
        ]);
    }

    // Calculate user fullTankLeft
    $fullTankLeft = (int) $get_user['fullTankLeft'];
    if (microtime(true) * 1000 >= (float) ($get_user['fullTankNextTime'] ?? 43200000)) {
        if ($fullTankLeft >= 3) {
            $fullTankLeft = 3;
        } else {
            $fullTankLeft++;
        }
        $fullTankNextTime = microtime(true) * 1000 + (6 * 60 * 60 * 1000);
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `fullTankNextTime` = :fullTankNextTime, `fullTankLeft` = :fullTankLeft 
             WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute([
            'fullTankNextTime' => $fullTankNextTime,
            'fullTankLeft' => $fullTankLeft,
            'id' => $userId,
        ]);
    }

    // Get updated user
    $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$get_user) {
        Response::jsonErrorLegacy('user_not_found', 'user not found', 422);
        exit;
    }

    $user_details = [];
    $user_details['score'] = (int) $get_user['score'];
    $user_details['balance'] = (int) $get_user['balance'];
    $user_details['energy'] = (int) $get_user['energy'];
    $user_details['multitap'] = (int) $get_user['multitap'];
    $user_details['energyLimit'] = (int) $get_user['energyLimit'];
    $user_details['rechargingSpeed'] = (int) $get_user['rechargingSpeed'];
    $user_details['referrals'] = (int) $get_user['referrals'];
    $user_details['tappingGuruLeft'] = (int) $get_user['tappingGuruLeft'];

    if ($get_user['tappingGuruLeft'] == 0) {
        $user_details['tappingGuruNextTime'] = (int) $get_user['tappingGuruNextTime'];
    }

    $user_details['fullTankLeft'] = (int) $get_user['fullTankLeft'];
    $user_details['fullTankNextTime'] = (int) $get_user['fullTankNextTime'];

    // Calculate user and bot missions
    $user_details['missions'] = [];

    $stmt = $pdo->prepare(
        'SELECT missions.id AS mission_id, missions.reward, missions.name AS mission_name, missions.description, 
                tasks.id AS task_id, tasks.name AS task_name, tasks.chatId, tasks.url, tasks.type 
         FROM missions 
         LEFT JOIN tasks ON missions.id = tasks.mission_id 
         ORDER BY missions.id, tasks.id'
    );
    $stmt->execute();
    $get_missions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if ($get_missions) {
        $mission_index = -1;
        $current_mission_id = null;

        foreach ($get_missions as $row) {
            // Check if we are starting a new mission
            if ($row['mission_id'] !== $current_mission_id) {
                $current_mission_id = $row['mission_id'];
                $mission_index++;

                $stmt = $pdo->prepare(
                    'SELECT * FROM `user_missions` 
                     WHERE `user_id` = :user_id AND `mission_id` = :mission_id LIMIT 1'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'mission_id' => $current_mission_id,
                ]);
                $user_mission = $stmt->fetch(\PDO::FETCH_ASSOC);
                $status = $user_mission ? (int) ($user_mission['status'] ?? 0) : 0;

                $user_details['missions'][$mission_index]['status'] = $status;
                $user_details['missions'][$mission_index]['id'] = (int) $row['mission_id'];
                $user_details['missions'][$mission_index]['reward'] = (int) $row['reward'];
                $user_details['missions'][$mission_index]['name'] = $row['mission_name'];
                $user_details['missions'][$mission_index]['description'] = $row['description'];
                $user_details['missions'][$mission_index]['tasks'] = [];
            }

            $stmt = $pdo->prepare(
                'SELECT * FROM `user_tasks` 
                 WHERE `user_id` = :user_id AND `task_id` = :task_id LIMIT 1'
            );
            $stmt->execute([
                'user_id' => $userId,
                'task_id' => $row['task_id'],
            ]);
            $user_task = $stmt->fetch(\PDO::FETCH_ASSOC);
            $task_status = $user_task ? (int) ($user_task['status'] ?? 0) : 0;

            $task_details = [
                'id' => (int) $row['task_id'],
                'name' => $row['task_name'],
                'type' => (int) $row['type'],
                'status' => $task_status,
            ];

            // Only add URL if type is 0 (URL type)
            if ($row['type'] == 0) {
                $task_details['url'] = $row['url'];
            } else {
                $task_details['chatId'] = $row['chatId'];
            }
            $user_details['missions'][$mission_index]['tasks'][] = $task_details;
        }
    }

    // Calculate refTasks
    $stmt = $pdo->prepare('SELECT `refLevel` FROM `refTasks` WHERE `user_id` = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $get_refTasks = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'refLevel');
    $refTasks = array_values(array_diff([1, 3, 10, 25, 50, 100, 500, 1000, 10000, 100000], $get_refTasks));
    $user_details['refTasks'] = $refTasks;

    // Calculate leaguesTasks
    $stmt = $pdo->prepare('SELECT `league` FROM `leaguesTasks` WHERE `user_id` = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $get_leaguesTasks = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'league');
    $leaguesTasks = array_values(
        array_diff(
            ['bronze', 'silver', 'gold', 'platinum', 'diamond', 'master', 'grandmaster', 'elite', 'legendary'],
            $get_leaguesTasks
        )
    );
    $user_details['leaguesTasks'] = $leaguesTasks;

    $user_details['totalReferralsRewards'] = (int) ($get_user['totalReferralsRewards'] ?? 0);
    $user_details['timestamp'] = round(microtime(true) * 1000);

    Response::jsonSuccessLegacy($user_details);

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('GetUser error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
