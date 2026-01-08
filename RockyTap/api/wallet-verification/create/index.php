<?php

declare(strict_types=1);

/**
 * Create Wallet Verification Request API endpoint
 * POST /api/wallet-verification/create
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
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

    // Rate limiting: max 10 requests per hour
    if (!RateLimiter::checkAndIncrement($userId, 'wallet_verification_api', 10, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many verification requests', 429);
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
    $method = $data['method'] ?? WalletVerificationService::METHOD_STANDARD_SIGNATURE;
    $context = $data['context'] ?? [];

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

    // Create verification request
    $result = WalletVerificationService::createVerificationRequest(
        $userId,
        $feature,
        $walletAddress,
        $walletNetwork,
        $context,
        $method
    );

    Response::jsonSuccess($result);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    Response::jsonError('RATE_LIMIT_ERROR', $e->getMessage(), 429);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while creating verification request', 500);
}

