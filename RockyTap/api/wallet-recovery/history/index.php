<?php

declare(strict_types=1);

/**
 * Wallet Recovery History API endpoint for Ghidar
 * Returns user's recovery request history.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\CrossChainRecoveryService;

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get optional limit parameter
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $limit = min(max($limit, 1), 100); // Clamp between 1 and 100

    // Initialize recovery service
    $recoveryService = new CrossChainRecoveryService();

    // Get recovery history
    $history = $recoveryService->getUserRecoveryHistory($userId, $limit);

    // Format response
    $formattedHistory = array_map(function ($request) {
        return [
            'request_id' => (int) $request['id'],
            'request_type' => $request['request_type'],
            'recovery_status' => $request['recovery_status'],
            'original_network' => $request['original_network'],
            'target_network' => $request['target_network'],
            'original_transaction_hash' => $request['original_transaction_hash'],
            'requested_amount' => $request['requested_amount'],
            'created_at' => $request['created_at'],
            'updated_at' => $request['updated_at']
        ];
    }, $history);

    Response::jsonSuccess([
        'history' => $formattedHistory,
        'count' => count($formattedHistory)
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

