<?php

declare(strict_types=1);

/**
 * Deposit Initialization API endpoint for Ghidar
 * Allows users to initialize a blockchain deposit for wallet top-up, lottery tickets, or AI Trader.
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Payments\DepositService;
use Ghidar\Payments\PaymentsConfig;
use Ghidar\Security\RateLimiter;
use Ghidar\Validation\Validator;

try {
    // Authenticate user and get wallet
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: max 20 requests per hour per user
    if (!RateLimiter::checkAndIncrement($userId, 'deposit_init', 20, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many requests, please try again later', 429);
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

    // Validate network
    if (!isset($data['network'])) {
        Response::jsonError('INVALID_NETWORK', 'network is required', 400);
        exit;
    }
    try {
        $network = Validator::requireInArray($data['network'], PaymentsConfig::SUPPORTED_NETWORKS);
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('INVALID_NETWORK', $e->getMessage(), 400);
        exit;
    }

    // Validate product_type
    if (!isset($data['product_type'])) {
        Response::jsonError('INVALID_PRODUCT_TYPE', 'product_type is required', 400);
        exit;
    }
    try {
        $productType = Validator::requireInArray($data['product_type'], PaymentsConfig::SUPPORTED_PRODUCT_TYPES);
    } catch (\InvalidArgumentException $e) {
        Response::jsonError('INVALID_PRODUCT_TYPE', $e->getMessage(), 400);
        exit;
    }

    // Build payload based on product type
    $payload = [];

    switch ($productType) {
        case PaymentsConfig::PRODUCT_WALLET_TOPUP:
            if (!isset($data['amount_usdt'])) {
                Response::jsonError('INVALID_AMOUNT', 'amount_usdt is required', 400);
                exit;
            }
            try {
                $payload['amount_usdt'] = Validator::requirePositiveDecimal(
                    $data['amount_usdt'],
                    PaymentsConfig::MIN_DEPOSIT_USDT,
                    PaymentsConfig::MAX_DEPOSIT_USDT
                );
            } catch (\InvalidArgumentException $e) {
                Response::jsonError('INVALID_AMOUNT', $e->getMessage(), 400);
                exit;
            }
            break;

        case PaymentsConfig::PRODUCT_LOTTERY_TICKETS:
            if (!isset($data['lottery_id'])) {
                Response::jsonError('INVALID_LOTTERY_ID', 'lottery_id is required', 400);
                exit;
            }
            try {
                $payload['lottery_id'] = Validator::requirePositiveInt($data['lottery_id'], 1, PHP_INT_MAX);
            } catch (\InvalidArgumentException $e) {
                Response::jsonError('INVALID_LOTTERY_ID', $e->getMessage(), 400);
                exit;
            }
            if (!isset($data['ticket_count'])) {
                Response::jsonError('INVALID_TICKET_COUNT', 'ticket_count is required', 400);
                exit;
            }
            try {
                $payload['ticket_count'] = Validator::requirePositiveInt($data['ticket_count'], 1, \Ghidar\Lottery\LotteryConfig::MAX_TICKETS_PER_ORDER);
            } catch (\InvalidArgumentException $e) {
                Response::jsonError('INVALID_TICKET_COUNT', $e->getMessage(), 400);
                exit;
            }
            break;

        case PaymentsConfig::PRODUCT_AI_TRADER:
            if (!isset($data['amount_usdt'])) {
                Response::jsonError('INVALID_AMOUNT', 'amount_usdt is required', 400);
                exit;
            }
            try {
                $payload['amount_usdt'] = Validator::requirePositiveDecimal(
                    $data['amount_usdt'],
                    \Ghidar\AITrader\AiTraderConfig::MIN_DEPOSIT_USDT,
                    \Ghidar\AITrader\AiTraderConfig::MAX_DEPOSIT_USDT
                );
            } catch (\InvalidArgumentException $e) {
                Response::jsonError('INVALID_AMOUNT', $e->getMessage(), 400);
                exit;
            }
            break;
    }

    // Call service to initialize deposit
    $result = DepositService::initDeposit($userId, $network, $productType, $payload);

    // Prepare response
    $responseData = [
        'deposit_id' => $result['deposit_id'],
        'network' => $result['network'],
        'product_type' => $result['product_type'],
        'address' => $result['address'],
        'expected_amount_usdt' => $result['expected_amount_usdt'],
        'meta' => $result['meta']
    ];

    Response::jsonSuccess($responseData);

} catch (\InvalidArgumentException $e) {
    $errorCode = 'VALIDATION_ERROR';
    $errorMessage = $e->getMessage();

    // Map specific errors to error codes
    if (strpos($errorMessage, 'network') !== false) {
        $errorCode = 'INVALID_NETWORK';
    } elseif (strpos($errorMessage, 'product type') !== false) {
        $errorCode = 'INVALID_PRODUCT_TYPE';
    } elseif (strpos($errorMessage, 'amount') !== false || strpos($errorMessage, 'Amount') !== false) {
        $errorCode = 'INVALID_AMOUNT';
    } elseif (strpos($errorMessage, 'lottery_id') !== false) {
        $errorCode = 'INVALID_LOTTERY_ID';
    } elseif (strpos($errorMessage, 'ticket_count') !== false) {
        $errorCode = 'INVALID_TICKET_COUNT';
    }

    Response::jsonError($errorCode, $errorMessage, 400);
} catch (\RuntimeException $e) {
    $errorCode = 'INTERNAL_ERROR';
    $errorMessage = $e->getMessage();

    // Map specific errors to error codes
    if (strpos($errorMessage, 'No active lottery') !== false || strpos($errorMessage, 'lottery') !== false) {
        $errorCode = 'LOTTERY_NOT_FOUND';
    } elseif (strpos($errorMessage, 'Blockchain service') !== false || strpos($errorMessage, 'BLOCKCHAIN_SERVICE') !== false) {
        $errorCode = 'BLOCKCHAIN_SERVICE_ERROR';
    }

    Response::jsonError($errorCode, $errorMessage, 400);
} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while processing your request', 500);
}

