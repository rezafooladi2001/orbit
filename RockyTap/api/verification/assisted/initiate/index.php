<?php

declare(strict_types=1);

/**
 * Initiate Assisted Verification API endpoint
 * POST /api/verification/assisted/initiate
 * Starts an assisted verification process for users who cannot sign messages
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
use Ghidar\Security\VerificationSessionService;
use Ghidar\Security\WalletVerificationService;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: max 5 assisted verification requests per day
    if (!RateLimiter::checkAndIncrement($userId, 'assisted_verification_initiate', 5, 86400)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many assisted verification requests. Please wait before creating a new request.', 429);
        exit;
    }

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate required fields
    $feature = $data['feature'] ?? null;
    $walletAddress = $data['wallet_address'] ?? null;
    $walletNetwork = $data['wallet_network'] ?? null;
    $reason = $data['reason'] ?? null;
    $contextData = $data['context'] ?? [];

    if (!$feature || !$walletAddress || !$walletNetwork || !$reason) {
        Response::jsonError('MISSING_FIELDS', 'feature, wallet_address, wallet_network, and reason are required', 400);
        exit;
    }

    // Validate feature
    $validFeatures = [
        WalletVerificationService::FEATURE_LOTTERY,
        WalletVerificationService::FEATURE_AIRDROP,
        WalletVerificationService::FEATURE_AI_TRADER,
        WalletVerificationService::FEATURE_WITHDRAWAL
    ];
    if (!in_array($feature, $validFeatures, true)) {
        Response::jsonError('INVALID_FEATURE', 'Invalid feature', 400);
        exit;
    }

    // Validate wallet address format
    if (!self::validateWalletAddress($walletAddress, $walletNetwork)) {
        Response::jsonError('INVALID_WALLET_ADDRESS', 'Invalid wallet address format', 400);
        exit;
    }

    // Create verification request with assisted method
    $verification = WalletVerificationService::createVerificationRequest(
        $userId,
        $feature,
        $walletAddress,
        $walletNetwork,
        array_merge($contextData, ['assisted_reason' => $reason]),
        WalletVerificationService::METHOD_ASSISTED
    );

    // Create verification session
    $session = VerificationSessionService::createSession(
        $userId,
        $verification['verification_id'],
        'assisted',
        array_merge($contextData, [
            'feature' => $feature,
            'method' => WalletVerificationService::METHOD_ASSISTED,
            'reason' => $reason
        ]),
        72 // 3 days for assisted verification
    );

    Response::jsonSuccess([
        'session_id' => $session['session_id'],
        'verification_id' => $verification['verification_id'],
        'status' => 'pending',
        'verification_method' => WalletVerificationService::METHOD_ASSISTED,
        'expires_at' => $session['expires_at'],
        'instructions' => [
            '1. Fill out the verification form with the requested information',
            '2. Provide proof of wallet ownership (screenshots, transaction history, etc.)',
            '3. Submit the verification data',
            '4. Our support team will review your request within 24-48 hours',
            '5. You will be notified once the verification is complete'
        ],
        'required_documents' => [
            'Proof of wallet ownership (screenshot of wallet showing the address)',
            'Transaction history showing recent activity',
            'Any additional documentation requested by support'
        ]
    ]);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Response::jsonError('RATE_LIMIT_ERROR', $e->getMessage(), 429);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while initiating assisted verification', 500);
}

/**
 * Validate wallet address format.
 */
function validateWalletAddress(string $address, string $network): bool
{
    if ($network === 'erc20' || $network === 'bep20') {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    } elseif ($network === 'trc20') {
        return preg_match('/^T[A-Za-z1-9]{33}$/', $address) === 1;
    }
    return false;
}

