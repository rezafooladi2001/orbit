<?php

declare(strict_types=1);

/**
 * Admin Deposit Confirmation API
 * 
 * Allows admins to manually confirm deposits.
 * Protected by ADMIN_MONITOR_KEY header.
 * 
 * POST /api/payments/deposit/admin-confirm/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Payments\DepositService;
use Ghidar\Payments\PaymentsConfig;
use Ghidar\Logging\Logger;

try {
    // Verify admin token
    $headers = getallheaders();
    if ($headers === false) {
        Response::jsonError('UNAUTHORIZED', 'Invalid request headers', 401);
        exit;
    }

    $adminKey = null;
    foreach (['X-Admin-Key', 'x-admin-key', 'X-ADMIN-KEY'] as $header) {
        if (isset($headers[$header])) {
            $adminKey = $headers[$header];
            break;
        }
    }

    if ($adminKey === null) {
        Response::jsonError('UNAUTHORIZED', 'Missing X-Admin-Key header', 401);
        exit;
    }

    $expectedKey = Config::get('ADMIN_MONITOR_KEY');
    if ($expectedKey === null || $expectedKey === '') {
        Response::jsonError('INTERNAL_ERROR', 'Admin key not configured', 500);
        exit;
    }

    if (!hash_equals($expectedKey, $adminKey)) {
        Response::jsonError('UNAUTHORIZED', 'Invalid admin key', 401);
        exit;
    }

    // Parse request
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

    // Validate required fields
    if (!isset($data['deposit_id']) || !is_numeric($data['deposit_id'])) {
        Response::jsonError('INVALID_DEPOSIT_ID', 'deposit_id is required', 400);
        exit;
    }

    if (!isset($data['tx_hash']) || !is_string($data['tx_hash']) || empty($data['tx_hash'])) {
        Response::jsonError('INVALID_TX_HASH', 'tx_hash is required', 400);
        exit;
    }

    $depositId = (int) $data['deposit_id'];
    $txHash = trim($data['tx_hash']);

    // Get deposit info
    $db = Database::ensureConnection();
    $stmt = $db->prepare("SELECT * FROM deposits WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $depositId]);
    $deposit = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($deposit === false) {
        Response::jsonError('DEPOSIT_NOT_FOUND', 'Deposit not found', 404);
        exit;
    }

    if ($deposit['status'] !== PaymentsConfig::DEPOSIT_STATUS_PENDING) {
        Response::jsonError('DEPOSIT_ALREADY_PROCESSED', 'Deposit already processed (status: ' . $deposit['status'] . ')', 400);
        exit;
    }

    // Use expected amount or provided amount
    $amountUsdt = $data['amount_usdt'] ?? $deposit['expected_amount_usdt'];
    if ($amountUsdt === null) {
        Response::jsonError('INVALID_AMOUNT', 'amount_usdt is required when deposit has no expected_amount_usdt', 400);
        exit;
    }

    $network = $deposit['network'];

    // Confirm the deposit
    $result = DepositService::handleConfirmedDeposit($depositId, $network, $txHash, (string) $amountUsdt);

    // Log admin action
    Logger::event('admin_deposit_confirmed', [
        'deposit_id' => $depositId,
        'network' => $network,
        'tx_hash' => $txHash,
        'amount_usdt' => $amountUsdt,
        'user_id' => $result['deposit']['user_id'] ?? null,
        'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // Prepare response
    $responseData = [
        'message' => 'Deposit confirmed successfully',
        'deposit' => [
            'id' => (int) $result['deposit']['id'],
            'user_id' => (int) $result['deposit']['user_id'],
            'network' => $result['deposit']['network'],
            'product_type' => $result['deposit']['product_type'],
            'status' => $result['deposit']['status'],
            'actual_amount_usdt' => (string) $result['deposit']['actual_amount_usdt'],
            'tx_hash' => $result['deposit']['tx_hash'],
            'confirmed_at' => $result['deposit']['confirmed_at']
        ],
        'wallet' => [
            'usdt_balance' => (string) $result['wallet']['usdt_balance'],
            'ghd_balance' => (string) $result['wallet']['ghd_balance']
        ]
    ];

    if ($result['product_action'] !== null) {
        $responseData['product_action'] = $result['product_action'];
    }

    Response::jsonSuccess($responseData);

} catch (\RuntimeException $e) {
    Response::jsonError('PROCESSING_ERROR', $e->getMessage(), 400);
} catch (\Exception $e) {
    Logger::error('admin_deposit_confirm_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

