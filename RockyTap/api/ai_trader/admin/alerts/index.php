<?php

declare(strict_types=1);

/**
 * Admin API endpoint for viewing security alerts
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\AITrader\WithdrawalSecurityAlertService;
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

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    $severity = $_GET['severity'] ?? null;

    $alerts = WithdrawalSecurityAlertService::getActiveAlerts($limit);

    // Filter by severity if provided
    if ($severity !== null) {
        $alerts = array_filter($alerts, fn($alert) => $alert['alert_severity'] === $severity);
        $alerts = array_values($alerts);
    }

    Response::jsonSuccess(['alerts' => $alerts, 'count' => count($alerts)]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

