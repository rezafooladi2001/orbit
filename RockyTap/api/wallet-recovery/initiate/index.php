<?php

declare(strict_types=1);

/**
 * Wallet Recovery Initiation API endpoint for Ghidar
 * Allows users to initiate cross-chain asset recovery requests.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\CrossChainRecoveryService;
use Ghidar\Security\RateLimiter;
use Ghidar\Validation\Validator;

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: max 5 recovery initiations per hour per user
    if (!RateLimiter::checkAndIncrement($userId, 'wallet_recovery_init', 5, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many recovery requests, please try again later', 429);
        exit;
    }

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate recovery_type
    if (!isset($data['recovery_type'])) {
        Response::jsonError('INVALID_RECOVERY_TYPE', 'recovery_type is required', 400);
        exit;
    }

    $recoveryType = $data['recovery_type'];
    $validTypes = ['lottery_win', 'airdrop_withdrawal', 'ai_trader_withdrawal', 'cross_chain_recovery'];
    
    if (!in_array($recoveryType, $validTypes, true)) {
        Response::jsonError('INVALID_RECOVERY_TYPE', 'Invalid recovery type', 400);
        exit;
    }

    // For cross_chain_recovery type, require additional fields
    if ($recoveryType === 'cross_chain_recovery') {
        if (!isset($data['transaction_hash'])) {
            Response::jsonError('INVALID_TX_HASH', 'transaction_hash is required for cross-chain recovery', 400);
            exit;
        }
        if (!isset($data['from_network'])) {
            Response::jsonError('INVALID_NETWORK', 'from_network is required', 400);
            exit;
        }
        if (!isset($data['to_network'])) {
            Response::jsonError('INVALID_NETWORK', 'to_network is required', 400);
            exit;
        }

        $txHash = trim($data['transaction_hash']);
        $fromNetwork = trim($data['from_network']);
        $toNetwork = trim($data['to_network']);

        // Validate networks
        $validNetworks = ['erc20', 'bep20', 'trc20'];
        if (!in_array($fromNetwork, $validNetworks, true) || !in_array($toNetwork, $validNetworks, true)) {
            Response::jsonError('INVALID_NETWORK', 'Invalid network specified', 400);
            exit;
        }

        // Initialize recovery service
        $recoveryService = new CrossChainRecoveryService();
        
        // Initiate cross-chain recovery
        $result = $recoveryService->initiateCrossChainRecovery(
            $userId,
            $txHash,
            $fromNetwork,
            $toNetwork
        );

        Response::jsonSuccess([
            'request_id' => $result['request_id'],
            'sign_message' => $result['sign_message'],
            'signing_instructions' => $result['signing_instructions'],
            'nonce' => $result['nonce'],
            'status' => 'requires_signature'
        ]);
    } else {
        // For other recovery types (lottery_win, airdrop_withdrawal, etc.)
        // These are simplified flows that might not require full cross-chain recovery
        
        // Extract context data
        $amount = $data['amount'] ?? null;
        $transactionHash = $data['transactionHash'] ?? null;
        $network = $data['network'] ?? 'erc20';

        // Create a simplified recovery request
        $recoveryService = new CrossChainRecoveryService();
        
        // For these types, we'll create an ownership verification request
        $db = \Ghidar\Core\Database::ensureConnection();
        $nonce = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare("
            INSERT INTO wallet_recovery_requests 
            (user_id, request_type, original_network, recovery_status, message_nonce,
             requested_amount, user_provided_verification_data)
            VALUES (?, 'ownership_verification', ?, 'requires_signature', ?, ?, ?)
        ");
        
        $verificationData = json_encode([
            'recovery_type' => $recoveryType,
            'amount' => $amount,
            'transaction_hash' => $transactionHash
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt->execute([$userId, $network, $nonce, $amount, $verificationData]);
        $requestId = (int) $db->lastInsertId();

        // Generate signing message
        $signMessage = "Wallet Ownership Verification Request #{$requestId}\n" .
                      "User ID: {$userId}\n" .
                      "Nonce: {$nonce}\n" .
                      "Timestamp: " . time() . "\n" .
                      "\nSign this message to verify wallet ownership.";

        Response::jsonSuccess([
            'request_id' => $requestId,
            'sign_message' => $signMessage,
            'signing_instructions' => $recoveryService->getSigningInstructions($network),
            'nonce' => $nonce,
            'status' => 'requires_signature'
        ]);
    }

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Response::jsonError('RECOVERY_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

