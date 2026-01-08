<?php

declare(strict_types=1);

/**
 * Wallet Recovery Status API endpoint for Ghidar
 * Returns the status of a recovery request.
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

    // Get request_id from query parameter
    if (!isset($_GET['request_id'])) {
        Response::jsonError('INVALID_REQUEST_ID', 'request_id query parameter is required', 400);
        exit;
    }

    $requestId = (int) $_GET['request_id'];

    // Initialize recovery service
    $recoveryService = new CrossChainRecoveryService();

    // Get recovery status
    $request = $recoveryService->getRecoveryStatus($requestId, $userId);

    if ($request === null) {
        Response::jsonError('REQUEST_NOT_FOUND', 'Recovery request not found or does not belong to you', 404);
        exit;
    }

    // Prepare response data
    $responseData = [
        'request_id' => (int) $request['id'],
        'request_type' => $request['request_type'],
        'recovery_status' => $request['recovery_status'],
        'original_network' => $request['original_network'],
        'target_network' => $request['target_network'],
        'original_transaction_hash' => $request['original_transaction_hash'],
        'requested_amount' => $request['requested_amount'],
        'estimated_fee' => $request['estimated_fee'],
        'created_at' => $request['created_at'],
        'updated_at' => $request['updated_at']
    ];

    // Add verification data if available
    if ($request['user_provided_verification_data'] !== null) {
        $responseData['verification_data'] = $request['user_provided_verification_data'];
    }

    Response::jsonSuccess($responseData);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

