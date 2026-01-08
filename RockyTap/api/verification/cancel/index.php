<?php

declare(strict_types=1);

/**
 * Cancel Verification Session API endpoint
 * POST /api/verification/cancel/:id
 * Cancels an active verification session
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;
use Ghidar\Security\VerificationSessionService;

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

    // Rate limiting: max 20 cancellations per hour
    if (!RateLimiter::checkAndIncrement($userId, 'verification_cancel', 20, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many cancellation requests', 429);
        exit;
    }

    // Get session ID from URL path
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
    $cancelIndex = array_search('cancel', $pathParts);
    $sessionId = $cancelIndex !== false && isset($pathParts[$cancelIndex + 1]) 
        ? $pathParts[$cancelIndex + 1] 
        : null;

    if (!$sessionId) {
        Response::jsonError('MISSING_SESSION_ID', 'Session ID is required', 400);
        exit;
    }

    // Cancel session
    $cancelled = VerificationSessionService::cancelSession($sessionId, $userId);

    if (!$cancelled) {
        Response::jsonError('CANCEL_FAILED', 'Failed to cancel session. Session may not exist, be already cancelled, or you may not have permission.', 400);
        exit;
    }

    Response::jsonSuccess([
        'session_id' => $sessionId,
        'status' => 'cancelled',
        'message' => 'Verification session cancelled successfully'
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while cancelling session', 500);
}

