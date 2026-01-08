<?php

declare(strict_types=1);

/**
 * Tap API endpoint for Ghidar
 * Handles user tap actions and updates score/balance/energy.
 * This is the core game logic endpoint.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Validation\Validator;

// Maximum taps per request to prevent abuse
const MAX_TAPS_PER_REQUEST = 1000;

try {
    // Authenticate user using Telegram initData (standardized auth)
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
    if ($data === null) {
        Response::jsonErrorLegacy('invalid_json', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate tapsInc
    if (!isset($data['tapsInc'])) {
        Response::jsonErrorLegacy('missing_taps', 'tapsInc is required', 400);
        exit;
    }

    try {
        // Validate tap count is a positive integer within reasonable bounds
        $tapsInc = Validator::requirePositiveInt($data['tapsInc'], 1, MAX_TAPS_PER_REQUEST);
    } catch (\InvalidArgumentException $e) {
        Response::jsonErrorLegacy('invalid_taps', $e->getMessage(), 400);
        exit;
    }

    $tappingGuruEnded = isset($data['tappingGuruEnded']) && (bool) $data['tappingGuruEnded'];

    $pdo = Database::ensureConnection();

    try {
        $pdo->beginTransaction();

        // Get user with lock to prevent race conditions
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$get_user) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
            exit;
        }

        // Calculate user energy based on time passed
        $lastTapTime = (int) ($get_user['lastTapTime'] ?? 0);
        $remaining_time = time() - $lastTapTime;
        $rechargingSpeed = (int) $get_user['rechargingSpeed'];
        $energyLimit = (int) $get_user['energyLimit'];
        
        $calculated_energy = ($remaining_time * $rechargingSpeed) + (int) $get_user['energy'];
        $maxEnergy = $energyLimit * 500;
        
        if ($calculated_energy > $maxEnergy) {
            $calculated_energy = $maxEnergy;
        }

        // Update energy first
        $stmt = $pdo->prepare('UPDATE `users` SET `energy` = :energy WHERE `id` = :user_id LIMIT 1');
        $stmt->execute([
            'energy' => $calculated_energy,
            'user_id' => $userId,
        ]);

        // Refresh user data
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $get_user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$get_user) {
            $pdo->rollBack();
            Response::jsonErrorLegacy('user_not_found', 'User not found', 404);
            exit;
        }

        $time = time();
        $energy = 0;
        $multitap = (int) $get_user['multitap'];
        $currentEnergy = (int) $get_user['energy'];
        $tappingGuruStarted = (float) ($get_user['tappingGuruStarted'] ?? 0);

        // Handle tapping guru logic (5x multiplier)
        if ($tappingGuruEnded) {
            $tapsInc *= 5;
            $tapsInc = $tapsInc * $multitap;
            $energy = $currentEnergy;
            $tappingGuruStarted = (microtime(true) * 1000) + 20000;
            
            $stmt = $pdo->prepare(
                'UPDATE `users` 
                 SET `score` = `score` + :tapsInc, 
                     `balance` = `balance` + :tapsInc, 
                     `lastTapTime` = :time, 
                     `tappingGuruStarted` = :tappingGuruStarted 
                 WHERE `id` = :user_id 
                 LIMIT 1'
            );
            $stmt->execute([
                'tapsInc' => $tapsInc,
                'time' => $time,
                'tappingGuruStarted' => $tappingGuruStarted,
                'user_id' => $userId,
            ]);
        } elseif ((microtime(true) * 1000) - $tappingGuruStarted <= 20000) {
            // Still in tapping guru window
            $tapsInc *= 5;
            $tapsInc = $tapsInc * $multitap;
            $energy = $currentEnergy;
            
            $stmt = $pdo->prepare(
                'UPDATE `users` 
                 SET `score` = `score` + :tapsInc, 
                     `balance` = `balance` + :tapsInc, 
                     `lastTapTime` = :time 
                 WHERE `id` = :user_id 
                 LIMIT 1'
            );
            $stmt->execute([
                'tapsInc' => $tapsInc,
                'time' => $time,
                'user_id' => $userId,
            ]);
        } else {
            // Normal tapping - consume energy
            $tapsInc = $tapsInc * $multitap;

            // Validate energy is sufficient
            if ($tapsInc > $currentEnergy) {
                $tapsInc = $currentEnergy;
                $energy = 0;
            } else {
                $energy = $currentEnergy - $tapsInc;
            }

            // Ensure energy is non-negative
            if ($energy < 0) {
                $energy = 0;
            }

            $stmt = $pdo->prepare(
                'UPDATE `users` 
                 SET `score` = `score` + :tapsInc, 
                     `balance` = `balance` + :tapsInc, 
                     `energy` = :energy, 
                     `lastTapTime` = :time 
                 WHERE `id` = :user_id 
                 LIMIT 1'
            );
            $stmt->execute([
                'tapsInc' => $tapsInc,
                'energy' => $energy,
                'time' => $time,
                'user_id' => $userId,
            ]);
        }

        // Get updated user for response
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $updated_user = $stmt->fetch(\PDO::FETCH_ASSOC);

        $pdo->commit();

        if (!$updated_user) {
            Response::jsonErrorLegacy('user_not_found', 'Failed to retrieve updated user', 500);
            exit;
        }

        $responseData = [
            'score' => (int) $updated_user['score'],
            'balance' => (int) $updated_user['balance'],
            'energy' => $energy,
        ];

        // Use legacy response format for compatibility
        Response::jsonSuccessLegacy($responseData);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Tap endpoint error: ' . $e->getMessage());
        Response::jsonErrorLegacy('database_error', 'Database error occurred', 500);
    }

} catch (\RuntimeException $e) {
    Response::jsonErrorLegacy('auth_error', $e->getMessage(), 401);
} catch (\Exception $e) {
    error_log('Tap endpoint error: ' . $e->getMessage());
    Response::jsonErrorLegacy('internal_error', 'An error occurred', 500);
}
