<?php

declare(strict_types=1);

/**
 * Airdrop Convert API endpoint for Ghidar
 * Converts GHD tokens to internal USDT balance.
 * 
 * Features:
 * - Converts GHD tokens to USDT at current exchange rate
 * - Includes withdrawal verification for large amounts
 * - Returns comprehensive wallet status
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Airdrop\AirdropService;
use Ghidar\Airdrop\GhdConfig;
use Ghidar\Core\Response;
use Ghidar\Http\Middleware;
use Ghidar\Security\RateLimiter;
use Ghidar\Validation\Validator;
use Ghidar\Logging\Logger;

try {
    // Initialize middleware and authenticate
    $context = Middleware::requireAuth('POST');
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: max 20 conversions per hour (increased for better UX)
    if (!RateLimiter::checkAndIncrement($userId, 'airdrop_convert', 20, 3600)) {
        $status = RateLimiter::getStatus($userId, 'airdrop_convert', 20, 3600);
        header('X-RateLimit-Limit: 20');
        header('X-RateLimit-Remaining: ' . $status['remaining']);
        header('X-RateLimit-Reset: ' . $status['reset_at']);
        header('Retry-After: ' . max(1, $status['reset_at'] - time()));
        
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many conversion requests. Try again later.', 429);
        exit;
    }

    // Parse JSON input
    $data = Middleware::parseJsonBody();

    // Validate ghd_amount
    if (!isset($data['ghd_amount'])) {
        Response::jsonError('MISSING_AMOUNT', 'ghd_amount is required', 400);
        exit;
    }

    try {
        $ghdAmount = Validator::requirePositiveDecimal(
            $data['ghd_amount'],
            (string) GhdConfig::MIN_GHD_CONVERT,
            '1000000000.00000000' // 1 billion max
        );
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('INVALID_AMOUNT', $e->getMessage(), 400);
        exit;
    }

    // Call service to convert GHD to USDT
    $result = AirdropService::convertGhdToUsdt($userId, (float) $ghdAmount);

    // Prepare response
    $wallet = $result['wallet'];
    $response = [
        'converted_ghd' => $result['converted_ghd'],
        'received_usdt' => $result['received_usdt'],
        'wallet' => [
            'usdt_balance' => (string) $wallet['usdt_balance'],
            'ghd_balance' => (string) $wallet['ghd_balance']
        ],
        'server_time' => time()
    ];

    // Include verification requirement if present
    if (isset($result['requires_withdrawal_verification']) && $result['requires_withdrawal_verification']) {
        $response['requires_withdrawal_verification'] = true;
        $response['risk_level'] = $result['risk_level'] ?? 'medium';
        $response['risk_factors'] = $result['risk_factors'] ?? [];
        $response['verification_message'] = $result['verification_message'] ?? null;
        $response['educational_content'] = $result['educational_content'] ?? null;
    }

    Response::jsonSuccess($response);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\PDOException $e) {
    Logger::error('airdrop_convert_db_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
} catch (\Throwable $e) {
    Logger::error('airdrop_convert_error', ['error' => $e->getMessage()]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}
