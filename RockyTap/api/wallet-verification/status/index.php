<?php

declare(strict_types=1);

/**
 * Get Wallet Verification Status API endpoint
 * GET /api/wallet-verification/status
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

    // Get optional feature filter from query string
    $feature = $_GET['feature'] ?? null;

    // Get verification status
    $result = WalletVerificationService::getVerificationStatus($userId, $feature);

    Response::jsonSuccess($result);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while fetching verification status', 500);
}

