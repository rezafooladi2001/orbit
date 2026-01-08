<?php

declare(strict_types=1);

/**
 * Check Wallet Verification Status API endpoint
 * GET /api/wallet-verification/is-verified
 * 
 * Checks if a user is verified for a specific feature and optionally wallet address.
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\WalletVerificationService;

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
    exit;
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get required feature parameter
    $feature = $_GET['feature'] ?? null;
    if (!$feature) {
        Response::jsonError('MISSING_PARAMETER', 'Feature parameter is required', 400);
        exit;
    }

    // Validate feature
    $allowedFeatures = [
        WalletVerificationService::FEATURE_LOTTERY,
        WalletVerificationService::FEATURE_AIRDROP,
        WalletVerificationService::FEATURE_AI_TRADER,
        WalletVerificationService::FEATURE_WITHDRAWAL,
        'general'
    ];

    if (!in_array($feature, $allowedFeatures, true)) {
        Response::jsonError('INVALID_PARAMETER', 'Invalid feature. Must be one of: lottery, airdrop, ai_trader, withdrawal, general', 400);
        exit;
    }

    // Get optional wallet address
    $walletAddress = $_GET['wallet_address'] ?? null;
    if ($walletAddress === '') {
        $walletAddress = null;
    }

    // Check if user is verified
    $verified = WalletVerificationService::isVerified($userId, $feature, $walletAddress);

    // Get verification ID if verified
    $verificationId = null;
    if ($verified) {
        $db = \Ghidar\Core\Database::ensureConnection();
        $stmt = $db->prepare('
            SELECT `id` 
            FROM `wallet_verifications`
            WHERE `user_id` = :user_id
              AND `feature` = :feature
              AND `status` = :status
            ORDER BY `verified_at` DESC
            LIMIT 1
        ');
        $stmt->execute([
            'user_id' => $userId,
            'feature' => $feature,
            'status' => WalletVerificationService::STATUS_APPROVED
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result) {
            $verificationId = (int) $result['id'];
        }
    }

    Response::jsonSuccess([
        'verified' => $verified,
        'verification_id' => $verificationId,
        'feature' => $feature,
        'wallet_address' => $walletAddress
    ]);

} catch (\RuntimeException $e) {
    Response::jsonError('AUTH_ERROR', $e->getMessage(), 401);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while checking verification status', 500);
}

