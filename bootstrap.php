<?php

declare(strict_types=1);

/**
 * Bootstrap file for Ghidar application.
 * Loads Composer autoloader, environment variables, and initializes core components.
 */

// Define root path
define('GHIDAR_ROOT', __DIR__);

// Get request method safely (handle CLI and unusual server configurations)
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Handle CORS preflight requests immediately (before loading anything else)
if ($requestMethod === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Telegram origins that are always allowed for Mini Apps
    $telegramOrigins = [
        'https://web.telegram.org',
        'https://webk.telegram.org',
        'https://webz.telegram.org',
    ];
    
    // Check if it's a Telegram origin
    $isTelegramOrigin = false;
    foreach ($telegramOrigins as $telegramOrigin) {
        if ($origin === $telegramOrigin || strpos($origin, $telegramOrigin) === 0) {
            $isTelegramOrigin = true;
            break;
        }
    }
    
    // For preflight requests, we need to respond appropriately
    // In production, only Telegram origins and configured origins get CORS headers
    // Config is not loaded yet, so we check for a simple env indicator
    $isProduction = getenv('APP_ENV') === 'production' || 
                    (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'production');
    
    if (!empty($origin)) {
        // Allow Telegram origins always
        if ($isTelegramOrigin) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        } elseif (!$isProduction) {
            // In development, allow all origins for easier testing
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        }
        // In production with non-Telegram origin, don't set CORS headers
        // The main Middleware will handle this after config is loaded
    } elseif (!$isProduction) {
        // No origin in development - allow all
        header('Access-Control-Allow-Origin: *');
    }
    
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Telegram-Data, Telegram-Init-Data, Accept, Origin, X-PAYMENTS-CALLBACK-TOKEN');
    header('Access-Control-Max-Age: 86400'); // 24 hours
    header('Content-Length: 0');
    http_response_code(204);
    exit;
}

// Check if vendor autoload exists before requiring it
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    // Return a JSON error if autoload is missing
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'AUTOLOAD_MISSING',
            'message' => 'Composer autoload not found. Run: composer install'
        ]
    ]);
    exit;
}

// Load Composer autoloader
require_once $autoloadPath;

// Load environment configuration
use Ghidar\Config\Config;
Config::load(__DIR__);

// Set timezone
date_default_timezone_set(Config::get('APP_TIMEZONE', 'UTC'));

// Error reporting based on environment
$env = Config::get('APP_ENV', 'local');
if ($env === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

ini_set('log_errors', '1');

// Set error log path
$logDir = GHIDAR_ROOT . '/RockyTap/storage/logs';
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('error_log', $logDir . '/php_errors.log');
}

// Register global exception handler
use Ghidar\Http\ExceptionHandler;
ExceptionHandler::register();

// Validate configuration in production (fail fast on critical issues)
if ($env === 'production') {
    try {
        \Ghidar\Config\ConfigValidator::validateOnStartup();
    } catch (\RuntimeException $e) {
        // In production, log the error but don't expose details
        error_log('[CRITICAL] Configuration validation failed: ' . $e->getMessage());
        if (php_sapi_name() !== 'cli') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'SERVICE_UNAVAILABLE',
                    'message' => 'Service temporarily unavailable. Please try again later.'
                ]
            ]);
            exit;
        }
        throw $e;
    }
}
