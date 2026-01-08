<?php

declare(strict_types=1);

/**
 * Get User Verification History API endpoint
 * GET /api/verification/user-history
 * Gets the user's verification history
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\VerificationSessionService;
use Ghidar\Validation\Validator;

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

    // Get pagination parameters
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

    // Validate pagination
    if ($limit < 1 || $limit > 100) {
        $limit = 50;
    }
    if ($offset < 0) {
        $offset = 0;
    }

    // Get user history
    $history = VerificationSessionService::getUserHistory($userId, $limit, $offset);

    Response::jsonSuccess($history);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while fetching verification history', 500);
}

