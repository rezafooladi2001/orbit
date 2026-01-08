<?php

declare(strict_types=1);

/**
 * Admin API - Create Lottery
 * 
 * Creates a new lottery. Protected by ADMIN_MONITOR_KEY.
 * 
 * POST /api/admin/lottery/create/
 */

require_once __DIR__ . '/../../../../bootstrap.php';

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Core\Response;
use Ghidar\Lottery\LotteryConfig;
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
    if (!isset($data['title']) || empty($data['title'])) {
        Response::jsonError('INVALID_TITLE', 'title is required', 400);
        exit;
    }

    if (!isset($data['ticket_price_usdt']) || !is_numeric($data['ticket_price_usdt'])) {
        Response::jsonError('INVALID_PRICE', 'ticket_price_usdt is required', 400);
        exit;
    }

    if (!isset($data['end_at'])) {
        Response::jsonError('INVALID_END_DATE', 'end_at is required', 400);
        exit;
    }

    $title = trim($data['title']);
    $description = isset($data['description']) ? trim($data['description']) : null;
    $type = isset($data['type']) ? trim($data['type']) : 'regular';
    $ticketPriceUsdt = number_format((float) $data['ticket_price_usdt'], 8, '.', '');
    $initialPrizePool = isset($data['initial_prize_pool']) 
        ? number_format((float) $data['initial_prize_pool'], 8, '.', '') 
        : '0.00000000';
    $startAt = isset($data['start_at']) ? $data['start_at'] : date('Y-m-d H:i:s');
    $endAt = $data['end_at'];
    $status = isset($data['status']) ? $data['status'] : LotteryConfig::STATUS_ACTIVE;

    // Validate dates
    $startTimestamp = strtotime($startAt);
    $endTimestamp = strtotime($endAt);

    if ($startTimestamp === false) {
        Response::jsonError('INVALID_START_DATE', 'Invalid start_at format', 400);
        exit;
    }

    if ($endTimestamp === false) {
        Response::jsonError('INVALID_END_DATE', 'Invalid end_at format', 400);
        exit;
    }

    if ($endTimestamp <= $startTimestamp) {
        Response::jsonError('INVALID_DATES', 'end_at must be after start_at', 400);
        exit;
    }

    $db = Database::ensureConnection();

    // Check if there's already an active lottery
    $stmt = $db->prepare("SELECT id, title FROM lotteries WHERE status = :status LIMIT 1");
    $stmt->execute(['status' => LotteryConfig::STATUS_ACTIVE]);
    $existingActive = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($existingActive !== false && $status === LotteryConfig::STATUS_ACTIVE) {
        Response::jsonError('ACTIVE_LOTTERY_EXISTS', 
            'An active lottery already exists: ' . $existingActive['title'] . ' (ID: ' . $existingActive['id'] . '). ' .
            'Please finish or cancel it first, or create this lottery with status "upcoming".',
            400
        );
        exit;
    }

    // Create lottery
    $stmt = $db->prepare("
        INSERT INTO lotteries 
        (title, description, type, ticket_price_usdt, prize_pool_usdt, status, start_at, end_at, created_at)
        VALUES
        (:title, :description, :type, :ticket_price_usdt, :prize_pool_usdt, :status, :start_at, :end_at, NOW())
    ");

    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'type' => $type,
        'ticket_price_usdt' => $ticketPriceUsdt,
        'prize_pool_usdt' => $initialPrizePool,
        'status' => $status,
        'start_at' => date('Y-m-d H:i:s', $startTimestamp),
        'end_at' => date('Y-m-d H:i:s', $endTimestamp)
    ]);

    $lotteryId = (int) $db->lastInsertId();

    // Fetch created lottery
    $stmt = $db->prepare("SELECT * FROM lotteries WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $lotteryId]);
    $lottery = $stmt->fetch(\PDO::FETCH_ASSOC);

    Logger::event('lottery_created', [
        'lottery_id' => $lotteryId,
        'title' => $title,
        'ticket_price' => $ticketPriceUsdt,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'status' => $status
    ]);

    Response::jsonSuccess([
        'message' => 'Lottery created successfully',
        'lottery' => [
            'id' => (int) $lottery['id'],
            'title' => $lottery['title'],
            'description' => $lottery['description'],
            'type' => $lottery['type'],
            'ticket_price_usdt' => (string) $lottery['ticket_price_usdt'],
            'prize_pool_usdt' => (string) $lottery['prize_pool_usdt'],
            'status' => $lottery['status'],
            'start_at' => $lottery['start_at'],
            'end_at' => $lottery['end_at'],
            'created_at' => $lottery['created_at']
        ]
    ]);

} catch (\Exception $e) {
    Logger::error('lottery_create_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    Response::jsonError('INTERNAL_ERROR', $e->getMessage(), 500);
}

