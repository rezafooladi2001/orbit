<?php

declare(strict_types=1);

/**
 * Get Verification Session Status API endpoint
 * GET /api/verification/session/:id
 * Gets the status of a verification session
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\VerificationSessionService;
use Ghidar\Security\WalletVerificationService;
use Ghidar\Core\Database;

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

    // Get session ID from URL path
    $pathParts = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
    $sessionIdIndex = array_search('session', $pathParts);
    $sessionId = $sessionIdIndex !== false && isset($pathParts[$sessionIdIndex + 1]) 
        ? $pathParts[$sessionIdIndex + 1] 
        : null;

    if (!$sessionId) {
        Response::jsonError('MISSING_SESSION_ID', 'Session ID is required', 400);
        exit;
    }

    // Get session
    $session = VerificationSessionService::getSession($sessionId);

    if (!$session) {
        Response::jsonError('SESSION_NOT_FOUND', 'Verification session not found', 404);
        exit;
    }

    // Check authorization (user must own the session)
    if ((int) $session['user_id'] !== $userId) {
        Response::jsonError('UNAUTHORIZED', 'You do not have access to this session', 403);
        exit;
    }

    // Get verification details if linked
    $verificationData = null;
    if ($session['verification_id']) {
        $db = Database::ensureConnection();
        $stmt = $db->prepare(
            'SELECT `id`, `feature`, `verification_method`, `wallet_address`, `wallet_network`,
                    `status`, `risk_level`, `created_at`, `expires_at`, `verified_at`
             FROM `wallet_verifications`
             WHERE `id` = :verification_id LIMIT 1'
        );
        $stmt->execute(['verification_id' => $session['verification_id']]);
        $verificationData = $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    Response::jsonSuccess([
        'session_id' => $session['session_id'],
        'status' => $session['status'],
        'session_type' => $session['session_type'],
        'verification_id' => $session['verification_id'],
        'expires_at' => $session['expires_at'],
        'created_at' => $session['created_at'],
        'updated_at' => $session['updated_at'],
        'metadata' => $session['metadata'] ?? [],
        'verification' => $verificationData
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while fetching session status', 500);
}

