<?php

declare(strict_types=1);

/**
 * Airdrop Tap API endpoint for Ghidar
 * Handles batch tap reporting and GHD earning.
 * 
 * OPTIMIZATIONS:
 * - Increased rate limit (120 req/min) for high-speed tapping
 * - Uses APCu caching when available for fast rate limiting
 * - Minimal response payload for faster transfers
 * - Supports batch acknowledgment with sequence numbers
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Airdrop\AirdropService;
use Ghidar\Airdrop\GhdConfig;
use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Security\RateLimiter;
use Ghidar\Validation\Validator;
use Ghidar\Logging\Logger;

// Disable output buffering for faster response
if (ob_get_level()) {
    ob_end_clean();
}

// Set response headers for caching and performance
header('Cache-Control: no-cache, must-revalidate');
header('X-Accel-Expires: 0'); // Disable nginx caching

try {
    // Initialize middleware and authenticate
    $context = Middleware::requireAuth('POST');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: 120 requests per minute per user (optimized for tapping)
    // This allows for faster tapping with larger batches
    if (!RateLimiter::checkAndIncrement($userId, 'airdrop_tap', 120, 60)) {
        // Add rate limit headers
        $status = RateLimiter::getStatus($userId, 'airdrop_tap', 120, 60);
        header('X-RateLimit-Limit: 120');
        header('X-RateLimit-Remaining: ' . $status['remaining']);
        header('X-RateLimit-Reset: ' . $status['reset_at']);
        header('Retry-After: ' . max(1, $status['reset_at'] - time()));
        
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many requests, please try again later', 429);
        exit;
    }

    // Add rate limit headers for successful requests too
    $status = RateLimiter::getStatus($userId, 'airdrop_tap', 120, 60);
    header('X-RateLimit-Limit: 120');
    header('X-RateLimit-Remaining: ' . $status['remaining']);
    header('X-RateLimit-Reset: ' . $status['reset_at']);

    // Parse JSON input
    $data = Middleware::parseJsonBody();

    // Validate tap_count
    if (!isset($data['tap_count'])) {
        Response::jsonError('MISSING_TAP_COUNT', 'tap_count is required', 400);
        exit;
    }

    try {
        $tapCount = Validator::requirePositiveInt($data['tap_count'], 1, GhdConfig::MAX_TAPS_PER_REQUEST);
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('INVALID_TAP_COUNT', $e->getMessage(), 400);
        exit;
    }

    // Optional: Client-provided sequence number for acknowledgment
    $sequenceNumber = isset($data['sequence']) ? (int) $data['sequence'] : null;

    // Call service to earn GHD from taps
    $result = AirdropService::earnFromTaps($userId, $tapCount);

    // Prepare minimal response payload
    $wallet = $result['wallet'];
    $response = [
        'ghd_earned' => $result['ghd_earned'],
        'wallet' => [
            'usdt_balance' => (string) $wallet['usdt_balance'],
            'ghd_balance' => (string) $wallet['ghd_balance']
        ]
    ];

    // Include sequence number if provided (for client-side acknowledgment)
    if ($sequenceNumber !== null) {
        $response['sequence_ack'] = $sequenceNumber;
    }

    // Add server timestamp for sync verification
    $response['server_time'] = time();

    Response::jsonSuccess($response);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\PDOException $e) {
    Logger::error('airdrop_tap_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
} catch (\Throwable $e) {
    Logger::error('airdrop_tap_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}
