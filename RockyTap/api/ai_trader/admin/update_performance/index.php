<?php

declare(strict_types=1);

/**
 * AI Trader Admin Update Performance API endpoint for Ghidar
 * Allows external AI/trading systems to update user performance via secure admin token.
 * This endpoint is for backend/admin use only.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\AITrader\AiTraderService;
use Ghidar\Config\Config;
use Ghidar\Core\Response;

try {
    // Check admin token from header or query parameter
    $adminToken = null;

    // Try header first (case-insensitive)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if ($headers !== false) {
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-ai-trader-admin-token') {
                    $adminToken = $value;
                    break;
                }
            }
        }
    }

    // Fallback: check $_SERVER for HTTP_ prefixed headers (for CLI/FastCGI)
    if ($adminToken === null && isset($_SERVER['HTTP_X_AI_TRADER_ADMIN_TOKEN'])) {
        $adminToken = $_SERVER['HTTP_X_AI_TRADER_ADMIN_TOKEN'];
    }

    // Fallback to query parameter
    if ($adminToken === null && isset($_GET['token'])) {
        $adminToken = $_GET['token'];
    }

    // Validate admin token
    $expectedToken = Config::get('AI_TRADER_ADMIN_TOKEN');
    if ($expectedToken === null || $expectedToken === '') {
        Response::jsonError('CONFIG_ERROR', 'Admin token not configured', 500);
        exit;
    }

    if ($adminToken === null || $adminToken !== $expectedToken) {
        Response::jsonError('UNAUTHORIZED', 'Invalid admin token', 401);
        exit;
    }

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate user_id
    if (!isset($data['user_id']) || !is_numeric($data['user_id']) || (int) $data['user_id'] <= 0) {
        Response::jsonError('INVALID_USER_ID', 'user_id is required and must be a positive integer', 400);
        exit;
    }

    $userId = (int) $data['user_id'];

    // Validate new_balance_usdt
    if (!isset($data['new_balance_usdt']) || !is_numeric($data['new_balance_usdt'])) {
        Response::jsonError('INVALID_BALANCE', 'new_balance_usdt is required and must be a number', 400);
        exit;
    }

    $newBalanceUsdt = (string) $data['new_balance_usdt'];

    // Validate new_balance_usdt >= 0
    if (bccomp($newBalanceUsdt, '0', 8) < 0) {
        Response::jsonError('INVALID_BALANCE', 'new_balance_usdt must be non-negative', 400);
        exit;
    }

    // Validate optional pnl_delta_usdt
    $pnlDeltaUsdt = null;
    if (isset($data['pnl_delta_usdt'])) {
        if (!is_numeric($data['pnl_delta_usdt'])) {
            Response::jsonError('INVALID_PNL_DELTA', 'pnl_delta_usdt must be a number', 400);
            exit;
        }
        $pnlDeltaUsdt = (string) $data['pnl_delta_usdt'];
    }

    // Get optional meta
    $meta = null;
    if (isset($data['meta']) && is_array($data['meta'])) {
        $meta = $data['meta'];
    }

    // Call service to record performance snapshot
    $result = AiTraderService::recordPerformanceSnapshot($userId, $newBalanceUsdt, $pnlDeltaUsdt, $meta);

    // Prepare response
    $aiAccount = $result['ai_account'];
    $responseData = [
        'ai_trader' => [
            'total_deposited_usdt' => (string) $aiAccount['total_deposited_usdt'],
            'current_balance_usdt' => (string) $aiAccount['current_balance_usdt'],
            'realized_pnl_usdt' => (string) $aiAccount['realized_pnl_usdt']
        ]
    ];

    Response::jsonSuccess($responseData);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

