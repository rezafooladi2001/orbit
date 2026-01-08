<?php

declare(strict_types=1);

/**
 * Lottery Pending Rewards API endpoint for Ghidar
 * Returns user's pending lottery rewards that require verification.
 * 
 * This endpoint is designed to fail gracefully - if tables don't exist
 * or there are database issues, it returns an empty response instead of error.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Lottery\WalletVerificationService;
use Ghidar\Logging\Logger;

/**
 * Build an empty/default pending rewards response
 */
function getEmptyPendingRewardsResponse(): array
{
    return [
        'pending_balance_usdt' => '0',
        'rewards' => [],
        'can_claim' => false
    ];
}

try {
    // Initialize middleware and authenticate
    $context = Middleware::requireAuth('GET');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get pending rewards with graceful degradation
    try {
        $pendingRewards = WalletVerificationService::getPendingRewards($userId);
    } catch (\PDOException $dbError) {
        // Check if it's a "table doesn't exist" or "column doesn't exist" error
        $errorMessage = $dbError->getMessage();
        if (
            strpos($errorMessage, "doesn't exist") !== false ||
            strpos($errorMessage, "Unknown column") !== false ||
            strpos($errorMessage, "no such table") !== false
        ) {
            // Gracefully return empty response for missing tables/columns
            Logger::warning('lottery_pending_rewards_table_missing', [
                'user_id' => $userId,
                'error' => $errorMessage
            ]);
            Response::jsonSuccess(getEmptyPendingRewardsResponse());
            exit;
        }
        // Re-throw for other database errors
        throw $dbError;
    }

    // Handle null or invalid response from service
    if ($pendingRewards === null || !is_array($pendingRewards)) {
        Response::jsonSuccess(getEmptyPendingRewardsResponse());
        exit;
    }

    // Format response
    $formattedRewards = [];
    $rewards = $pendingRewards['rewards'] ?? [];
    
    foreach ($rewards as $reward) {
        if (!is_array($reward)) continue;
        
        $formattedRewards[] = [
            'id' => (int) ($reward['id'] ?? 0),
            'lottery_id' => (int) ($reward['lottery_id'] ?? 0),
            'lottery_title' => $reward['lottery_title'] ?? 'Unknown Lottery',
            'reward_type' => $reward['reward_type'] ?? 'unknown',
            'reward_amount_usdt' => (string) ($reward['reward_amount_usdt'] ?? '0'),
            'ticket_count' => (int) ($reward['ticket_count'] ?? 0),
            'status' => $reward['status'] ?? 'unknown',
            'created_at' => $reward['created_at'] ?? null
        ];
    }

    $response = [
        'pending_balance_usdt' => (string) ($pendingRewards['pending_balance_usdt'] ?? '0'),
        'rewards' => $formattedRewards,
        'can_claim' => (bool) ($pendingRewards['can_claim'] ?? false)
    ];

    // Include active verification request if exists
    if (isset($pendingRewards['active_verification_request']) && 
        $pendingRewards['active_verification_request'] !== null) {
        $req = $pendingRewards['active_verification_request'];
        $response['active_verification_request'] = [
            'id' => (int) ($req['id'] ?? 0),
            'verification_method' => $req['verification_method'] ?? 'signature',
            'verification_status' => $req['verification_status'] ?? 'pending',
            'message_to_sign' => $req['message_to_sign'] ?? null,
            'message_nonce' => $req['message_nonce'] ?? null,
            'expires_at' => $req['expires_at'] ?? null,
            'created_at' => $req['created_at'] ?? null
        ];
    }

    Response::jsonSuccess($response);

} catch (\PDOException $e) {
    // For any remaining database errors, return empty response with warning log
    Logger::warning('lottery_pending_rewards_db_fallback', [
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    Response::jsonSuccess(getEmptyPendingRewardsResponse());
} catch (\Throwable $e) {
    // For any other errors, also return empty response for graceful degradation
    Logger::error('lottery_pending_rewards_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonSuccess(getEmptyPendingRewardsResponse());
}

