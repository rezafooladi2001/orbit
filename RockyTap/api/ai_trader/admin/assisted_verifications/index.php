<?php

declare(strict_types=1);

/**
 * Admin API endpoint for viewing assisted verifications
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\AITrader\AssistedVerificationService;
use Ghidar\Core\Response;
use Ghidar\Core\UserContext;

function isAdmin(int $userId): bool
{
    $adminIds = [125125166];
    return in_array($userId, $adminIds);
}

try {
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    if (!isAdmin($userId)) {
        Response::jsonError('UNAUTHORIZED', 'Admin access required', 403);
        exit;
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $assistedVerifications = AssistedVerificationService::getPendingVerifications($limit);

    Response::jsonSuccess([
        'assisted_verifications' => $assistedVerifications,
        'count' => count($assistedVerifications)
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

