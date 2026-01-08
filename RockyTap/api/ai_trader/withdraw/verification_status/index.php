<?php

declare(strict_types=1);

/**
 * AI Trader Withdrawal Verification Status API endpoint
 * Gets the current status of a withdrawal verification request.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\AITrader\SourceOfFundsVerificationService;
use Ghidar\AITrader\WithdrawalVerificationService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get verification ID from query parameter or request body
    $verificationId = null;
    if (isset($_GET['verification_id'])) {
        $verificationId = (int) $_GET['verification_id'];
    } else {
        $input = file_get_contents('php://input');
        if ($input !== false) {
            $data = json_decode($input, true);
            $verificationId = isset($data['verification_id']) ? (int) $data['verification_id'] : null;
        }
    }

    // If no verification ID provided, get active verification
    if ($verificationId === null) {
        $verification = WithdrawalVerificationService::getActiveVerification($userId);
        if ($verification === null) {
            Response::jsonError('NO_ACTIVE_VERIFICATION', 'No active verification found', 404);
            exit;
        }
    } else {
        $verification = WithdrawalVerificationService::getVerification($verificationId);
        
        // Verify ownership
        if ((int) $verification['user_id'] !== $userId) {
            Response::jsonError('UNAUTHORIZED', 'You are not authorized to view this verification', 403);
            exit;
        }
    }

    // Get source of funds verification if exists
    $sofwVerification = SourceOfFundsVerificationService::getVerificationByWithdrawalId((int) $verification['id']);

    // Prepare response
    $responseData = [
        'verification_id' => (int) $verification['id'],
        'verification_tier' => $verification['verification_tier'],
        'verification_step' => (int) $verification['verification_step'],
        'status' => $verification['status'],
        'withdrawal_amount_usdt' => $verification['withdrawal_amount_usdt'],
        'wallet_address' => $verification['wallet_address'],
        'wallet_network' => $verification['wallet_network'],
        'estimated_completion_time' => $verification['estimated_completion_time'],
        'completed_at' => $verification['completed_at'],
        'steps' => $verification['steps'] ?? [],
        'source_of_funds_verification' => $sofwVerification ? [
            'status' => $sofwVerification['verification_status'],
            'method' => $sofwVerification['verification_method'],
            'verified_at' => $sofwVerification['verified_at'],
            'expires_at' => $sofwVerification['expires_at']
        ] : null,
        'requires_assisted_verification' => (bool) $verification['requires_assisted_verification'],
        'created_at' => $verification['created_at'],
        'updated_at' => $verification['updated_at']
    ];

    Response::jsonSuccess($responseData);

} catch (\RuntimeException $e) {
    Response::jsonError('NOT_FOUND', $e->getMessage(), 404);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

