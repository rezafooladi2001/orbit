<?php

declare(strict_types=1);

/**
 * Deposit Callback API endpoint for Ghidar
 * Called by blockchain-service when a confirmed deposit is detected.
 * This is a server-to-server endpoint protected by PAYMENTS_CALLBACK_TOKEN.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Config\Config;
use Ghidar\Core\Response;
use Ghidar\Payments\DepositService;
use Ghidar\Payments\PaymentsConfig;

try {
    // Verify callback token (server-to-server authentication)
    $headers = getallheaders();
    if ($headers === false) {
        Response::jsonError('UNAUTHORIZED', 'Invalid request headers', 401);
        exit;
    }

    $callbackToken = null;
    if (isset($headers['X-PAYMENTS-CALLBACK-TOKEN'])) {
        $callbackToken = $headers['X-PAYMENTS-CALLBACK-TOKEN'];
    } elseif (isset($headers['x-payments-callback-token'])) {
        $callbackToken = $headers['x-payments-callback-token'];
    }

    if ($callbackToken === null) {
        Response::jsonError('UNAUTHORIZED', 'Missing X-PAYMENTS-CALLBACK-TOKEN header', 401);
        exit;
    }

    $expectedToken = Config::get('PAYMENTS_CALLBACK_TOKEN');
    if ($expectedToken === null || $expectedToken === '') {
        Response::jsonError('INTERNAL_ERROR', 'Callback token not configured', 500);
        exit;
    }

    if (!hash_equals($expectedToken, $callbackToken)) {
        Response::jsonError('UNAUTHORIZED', 'Invalid callback token', 401);
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

    // Validate required fields
    if (!isset($data['deposit_id']) || !is_numeric($data['deposit_id'])) {
        Response::jsonError('INVALID_DEPOSIT_ID', 'deposit_id is required and must be numeric', 400);
        exit;
    }

    if (!isset($data['network']) || !is_string($data['network'])) {
        Response::jsonError('INVALID_NETWORK', 'network is required and must be a string', 400);
        exit;
    }

    if (!isset($data['tx_hash']) || !is_string($data['tx_hash']) || empty($data['tx_hash'])) {
        Response::jsonError('INVALID_TX_HASH', 'tx_hash is required and must be a non-empty string', 400);
        exit;
    }

    if (!isset($data['amount_usdt']) || !is_numeric($data['amount_usdt'])) {
        Response::jsonError('INVALID_AMOUNT', 'amount_usdt is required and must be numeric', 400);
        exit;
    }

    $depositId = (int) $data['deposit_id'];
    $network = $data['network'];
    $txHash = $data['tx_hash'];
    $amountUsdt = (string) $data['amount_usdt'];

    // Validate network
    if (!in_array($network, PaymentsConfig::SUPPORTED_NETWORKS, true)) {
        Response::jsonError('INVALID_NETWORK', 'Unsupported network', 400);
        exit;
    }

    // Validate deposit_id is positive
    if ($depositId <= 0) {
        Response::jsonError('INVALID_DEPOSIT_ID', 'deposit_id must be a positive integer', 400);
        exit;
    }

    // Call service to handle confirmed deposit
    $result = DepositService::handleConfirmedDeposit($depositId, $network, $txHash, $amountUsdt);

    // Prepare response
    $responseData = [
        'deposit' => [
            'id' => (int) $result['deposit']['id'],
            'user_id' => (int) $result['deposit']['user_id'],
            'network' => $result['deposit']['network'],
            'product_type' => $result['deposit']['product_type'],
            'status' => $result['deposit']['status'],
            'address' => $result['deposit']['address'],
            'expected_amount_usdt' => $result['deposit']['expected_amount_usdt'] !== null ? (string) $result['deposit']['expected_amount_usdt'] : null,
            'actual_amount_usdt' => $result['deposit']['actual_amount_usdt'] !== null ? (string) $result['deposit']['actual_amount_usdt'] : null,
            'tx_hash' => $result['deposit']['tx_hash'],
            'confirmed_at' => $result['deposit']['confirmed_at']
        ],
        'wallet' => [
            'usdt_balance' => (string) $result['wallet']['usdt_balance'],
            'ghd_balance' => (string) $result['wallet']['ghd_balance']
        ]
    ];

    // Add product-specific action if present
    if ($result['product_action'] !== null) {
        $responseData['product_action'] = $result['product_action'];
    }

    Response::jsonSuccess($responseData);

} catch (\InvalidArgumentException $e) {
    Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    $errorCode = 'PROCESSING_ERROR';
    $errorMessage = $e->getMessage();

    // Map specific errors to error codes
    if (strpos($errorMessage, 'not found') !== false || strpos($errorMessage, 'Deposit not found') !== false) {
        $errorCode = 'DEPOSIT_NOT_FOUND';
    } elseif (strpos($errorMessage, 'not in pending') !== false || strpos($errorMessage, 'already confirmed') !== false) {
        $errorCode = 'DEPOSIT_ALREADY_PROCESSED';
    } elseif (strpos($errorMessage, 'Network mismatch') !== false) {
        $errorCode = 'NETWORK_MISMATCH';
    } elseif (strpos($errorMessage, 'less than expected') !== false || strpos($errorMessage, 'Insufficient balance') !== false) {
        $errorCode = 'INSUFFICIENT_AMOUNT';
    }

    Response::jsonError($errorCode, $errorMessage, 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

