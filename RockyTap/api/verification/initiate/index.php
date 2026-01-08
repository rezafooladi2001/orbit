<?php

declare(strict_types=1);

/**
 * Initiate Verification Session API endpoint
 * POST /api/verification/initiate
 * Starts a new verification session
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
use Ghidar\Security\VerificationSessionService;
use Ghidar\Security\WalletVerificationService;
use Ghidar\Validation\Validator;

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

    // Rate limiting: max 10 sessions per hour
    if (!RateLimiter::checkAndIncrement($userId, 'verification_initiate', 10, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many verification session requests', 429);
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

    // Validate and extract parameters
    $feature = $data['feature'] ?? null;
    $walletAddress = $data['wallet_address'] ?? null;
    $walletNetwork = $data['wallet_network'] ?? null;
    $sessionType = $data['session_type'] ?? 'standard';
    $verificationMethod = $data['verification_method'] ?? WalletVerificationService::METHOD_STANDARD_SIGNATURE;
    $contextData = $data['context'] ?? [];
    $expirationHours = isset($data['expiration_hours']) ? (int) $data['expiration_hours'] : 24;

    // Validate required fields
    if (!$feature || !$walletAddress || !$walletNetwork) {
        Response::jsonError('MISSING_FIELDS', 'feature, wallet_address, and wallet_network are required', 400);
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
        Response::jsonError('INVALID_FEATURE', 'Invalid feature. Must be one of: lottery, airdrop, ai_trader, withdrawal', 400);
        exit;
    }

    // Validate wallet address format
    if (!self::validateWalletAddress($walletAddress, $walletNetwork)) {
        Response::jsonError('INVALID_WALLET_ADDRESS', 'Invalid wallet address format for the specified network', 400);
        exit;
    }

    // Validate expiration hours
    if ($expirationHours < 1 || $expirationHours > 168) { // Max 7 days
        Response::jsonError('INVALID_EXPIRATION', 'Expiration hours must be between 1 and 168', 400);
        exit;
    }

    // Create verification request first
    $verification = WalletVerificationService::createVerificationRequest(
        $userId,
        $feature,
        $walletAddress,
        $walletNetwork,
        $contextData,
        $verificationMethod
    );

    // Create verification session
    $session = VerificationSessionService::createSession(
        $userId,
        $verification['verification_id'],
        $sessionType,
        array_merge($contextData, ['feature' => $feature, 'method' => $verificationMethod]),
        $expirationHours
    );

    Response::jsonSuccess([
        'session_id' => $session['session_id'],
        'verification_id' => $verification['verification_id'],
        'status' => $session['status'],
        'verification_method' => $verification['verification_method'],
        'message_to_sign' => $verification['message_to_sign'],
        'message_nonce' => $verification['message_nonce'],
        'expires_at' => $session['expires_at'],
        'instructions' => $verification['instructions'],
        'educational_content' => $verification['educational_content']
    ]);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Response::jsonError('RATE_LIMIT_ERROR', $e->getMessage(), 429);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while initiating verification session', 500);
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

