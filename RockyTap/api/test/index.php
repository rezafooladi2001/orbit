<?php

declare(strict_types=1);

/**
 * Test endpoint for debugging API connectivity.
 * This is a simple endpoint that doesn't require authentication.
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Response;

// Set CORS headers immediately
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Telegram-Data, telegram-data, Accept, Origin');

// Gather request info
$requestInfo = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'origin' => $origin,
    'headers' => [
        'Content-Type' => $_SERVER['HTTP_CONTENT_TYPE'] ?? null,
        'Telegram-Data' => isset($_SERVER['HTTP_TELEGRAM_DATA']) ? 'present (' . strlen($_SERVER['HTTP_TELEGRAM_DATA']) . ' chars)' : 'not present',
        'Accept' => $_SERVER['HTTP_ACCEPT'] ?? null,
    ],
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
];

Response::jsonSuccess([
    'message' => 'API test endpoint is working!',
    'request' => $requestInfo,
]);

