<?php

declare(strict_types=1);

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

/**
 * Healthcheck endpoint for infrastructure monitoring.
 * Returns system health status including database, disk space, and PHP info.
 * 
 * This endpoint is public (no auth required) for use by load balancers and monitoring tools.
 */

// Start output buffering immediately
ob_start();

// Set JSON content type early
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request immediately
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    ob_end_clean();
    http_response_code(204);
    exit;
}

// Ensure we always output JSON even on fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // Clear any buffered output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'FATAL_ERROR',
                'message' => 'A server error occurred'
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
});

$response = null;
$statusCode = 200;

try {
    // Try to load bootstrap
    $bootstrapPath = __DIR__ . '/../../bootstrap.php';
    if (!file_exists($bootstrapPath)) {
        throw new RuntimeException('Bootstrap file not found');
    }
    
    require_once $bootstrapPath;

    $checks = [
        'database' => false,
        'storage' => false,
        'php' => true
    ];

    $details = [];
    $overall = 'ok';

    // Check 1: Database connectivity
    try {
        $db = Database::ensureConnection();
        $stmt = $db->prepare('SELECT 1 as health');
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result !== false) {
            $checks['database'] = true;
            $details['database'] = 'connected';
        } else {
            $details['database'] = 'query failed';
            $overall = 'degraded';
        }
    } catch (\PDOException $e) {
        $details['database'] = 'connection failed: ' . $e->getMessage();
        $overall = 'unhealthy';
        try {
            Logger::error('healthcheck_db_failed', ['error' => $e->getMessage()]);
        } catch (\Throwable $logError) {
            // Ignore logging errors
        }
    }

    // Check 2: Storage/disk space
    $logDir = defined('GHIDAR_ROOT') ? GHIDAR_ROOT . '/RockyTap/storage/logs' : __DIR__ . '/../../storage/logs';
    
    if (is_dir($logDir) && is_writable($logDir)) {
        $checks['storage'] = true;
        $details['storage'] = 'writable';
        
        // Check available disk space (warn if less than 100MB)
        $freeSpace = disk_free_space($logDir);
        if ($freeSpace !== false) {
            $freeSpaceMB = round($freeSpace / 1024 / 1024, 2);
            $details['disk_free_mb'] = $freeSpaceMB;
            
            if ($freeSpaceMB < 100) {
                $overall = $overall === 'unhealthy' ? 'unhealthy' : 'degraded';
            }
        }
    } else {
        $details['storage'] = 'not writable';
        $overall = $overall === 'unhealthy' ? 'unhealthy' : 'degraded';
    }

    // Check 3: PHP info
    $details['php_version'] = PHP_VERSION;
    $details['memory_limit'] = ini_get('memory_limit');
    $details['max_execution_time'] = (int) ini_get('max_execution_time');
    
    // Required extensions check
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'bcmath'];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    if (!empty($missingExtensions)) {
        $details['missing_extensions'] = $missingExtensions;
        $overall = 'unhealthy';
    }

    // Check 4: Environment
    $details['environment'] = Config::get('APP_ENV', 'unknown');
    $details['timezone'] = date_default_timezone_get();
    $details['timestamp'] = date('Y-m-d H:i:s');

    // Final status
    $statusCode = match ($overall) {
        'ok' => 200,
        'degraded' => 200,
        'unhealthy' => 503
    };

    $response = [
        'success' => $overall !== 'unhealthy',
        'data' => [
            'status' => $overall,
            'checks' => $checks,
            'details' => $details
        ],
        'error' => null
    ];

} catch (\Throwable $e) {
    $statusCode = 500;
    $response = [
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'HEALTHCHECK_FAILED',
            'message' => 'Healthcheck failed: ' . $e->getMessage()
        ]
    ];
}

// Clear buffer and output response
ob_end_clean();
http_response_code($statusCode);
header('Content-Type: application/json; charset=utf-8');

// Ensure we have a valid response
if ($response === null) {
    $response = [
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'NO_RESPONSE',
            'message' => 'No response was generated'
        ]
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
