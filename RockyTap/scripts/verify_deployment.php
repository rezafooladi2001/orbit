<?php

declare(strict_types=1);

/**
 * Deployment Verification Script for Ghidar Mini-App
 * 
 * Run this script from the command line after deployment to verify everything is working:
 *   php RockyTap/scripts/verify_deployment.php
 * 
 * Or access via web browser:
 *   https://your-domain.com/RockyTap/scripts/verify_deployment.php
 */

// Determine if running from CLI or web
$isCli = (php_sapi_name() === 'cli');

// Set headers for web access
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

function output($message, $status = 'info') {
    global $isCli;
    
    $prefixes = [
        'ok' => '✓',
        'error' => '✗',
        'warning' => '⚠',
        'info' => '→'
    ];
    
    $prefix = $prefixes[$status] ?? '→';
    
    echo "{$prefix} {$message}\n";
}

function section($title) {
    echo "\n=== {$title} ===\n";
}

$errors = 0;
$warnings = 0;

echo "Ghidar Deployment Verification\n";
echo "==============================\n";
echo "Timestamp: " . date('Y-m-d H:i:s T') . "\n";

// Section 1: PHP Configuration
section('PHP Configuration');

if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    output("PHP Version: " . PHP_VERSION, 'ok');
} else {
    output("PHP Version: " . PHP_VERSION . " (requires >= 8.1)", 'error');
    $errors++;
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'bcmath', 'openssl', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        output("Extension {$ext}: loaded", 'ok');
    } else {
        output("Extension {$ext}: MISSING", 'error');
        $errors++;
    }
}

// Section 2: Directory Structure
section('Directory Structure');

$projectRoot = dirname(dirname(__DIR__));
$directories = [
    'RockyTap' => $projectRoot . '/RockyTap',
    'RockyTap/api' => $projectRoot . '/RockyTap/api',
    'RockyTap/assets' => $projectRoot . '/RockyTap/assets',
    'RockyTap/storage' => $projectRoot . '/RockyTap/storage',
    'RockyTap/storage/logs' => $projectRoot . '/RockyTap/storage/logs',
    'src' => $projectRoot . '/src',
    'vendor' => $projectRoot . '/vendor',
];

foreach ($directories as $name => $path) {
    if (is_dir($path)) {
        output("Directory {$name}: exists", 'ok');
    } else {
        output("Directory {$name}: MISSING at {$path}", 'error');
        $errors++;
    }
}

// Section 3: Key Files
section('Key Files');

$files = [
    '.env' => $projectRoot . '/.env',
    'bootstrap.php' => $projectRoot . '/bootstrap.php',
    'vendor/autoload.php' => $projectRoot . '/vendor/autoload.php',
    'RockyTap/index.php' => $projectRoot . '/RockyTap/index.php',
    'RockyTap/api/me/index.php' => $projectRoot . '/RockyTap/api/me/index.php',
    'RockyTap/api/health/index.php' => $projectRoot . '/RockyTap/api/health/index.php',
    'RockyTap/assets/index-BYqAG32B.js' => $projectRoot . '/RockyTap/assets/index-BYqAG32B.js',
    'RockyTap/assets/index-Bx_Rp-Zd.css' => $projectRoot . '/RockyTap/assets/index-Bx_Rp-Zd.css',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        output("File {$name}: exists", 'ok');
    } else {
        output("File {$name}: MISSING at {$path}", 'error');
        $errors++;
    }
}

// Section 4: Storage Permissions
section('Storage Permissions');

$storageDirs = [
    'logs' => $projectRoot . '/RockyTap/storage/logs',
    'cache' => $projectRoot . '/RockyTap/storage/cache',
    'sessions' => $projectRoot . '/RockyTap/storage/sessions',
];

foreach ($storageDirs as $name => $path) {
    if (is_dir($path) && is_writable($path)) {
        output("Storage {$name}: writable", 'ok');
    } elseif (is_dir($path)) {
        output("Storage {$name}: NOT writable", 'error');
        $errors++;
    } else {
        output("Storage {$name}: directory doesn't exist", 'warning');
        $warnings++;
    }
}

// Section 5: Environment Configuration
section('Environment Configuration');

// Try to load bootstrap
try {
    require_once $projectRoot . '/vendor/autoload.php';
    
    // Manually load .env
    $envPath = $projectRoot . '/.env';
    if (file_exists($envPath)) {
        $dotenv = \Dotenv\Dotenv::createImmutable($projectRoot);
        $dotenv->load();
        output(".env file: loaded", 'ok');
    } else {
        output(".env file: MISSING", 'error');
        $errors++;
    }
    
    // Check required environment variables
    $requiredEnv = [
        'APP_ENV' => 'Application environment',
        'DB_HOST' => 'Database host',
        'DB_DATABASE' => 'Database name',
        'DB_USERNAME' => 'Database username',
        'DB_PASSWORD' => 'Database password',
        'TELEGRAM_BOT_TOKEN' => 'Telegram bot token',
    ];
    
    foreach ($requiredEnv as $var => $description) {
        $value = $_ENV[$var] ?? null;
        if ($value !== null && $value !== '') {
            if ($var === 'DB_PASSWORD' || $var === 'TELEGRAM_BOT_TOKEN') {
                output("{$var}: [SET - " . strlen($value) . " chars]", 'ok');
            } else {
                output("{$var}: {$value}", 'ok');
            }
        } else {
            output("{$var}: NOT SET ({$description})", 'error');
            $errors++;
        }
    }
    
} catch (\Exception $e) {
    output("Bootstrap failed: " . $e->getMessage(), 'error');
    $errors++;
}

// Section 6: Database Connection
section('Database Connection');

try {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $database = $_ENV['DB_DATABASE'] ?? '';
    $username = $_ENV['DB_USERNAME'] ?? '';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    $sslCa = $_ENV['DB_SSL_CA'] ?? null;
    
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];
    
    if ($sslCa && file_exists($sslCa)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    
    $pdo = new PDO($dsn, $username, $password, $options);
    $stmt = $pdo->query('SELECT 1');
    output("Database connection: success", 'ok');
    
    // Check tables
    $tables = ['users', 'wallets', 'missions', 'tasks'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `{$table}`");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            output("Table {$table}: exists ({$result['cnt']} rows)", 'ok');
        } catch (\PDOException $e) {
            output("Table {$table}: MISSING or error", 'error');
            $errors++;
        }
    }
    
} catch (\PDOException $e) {
    output("Database connection: FAILED - " . $e->getMessage(), 'error');
    $errors++;
}

// Section 7: Telegram Configuration
section('Telegram Configuration');

$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$botUsername = $_ENV['TELEGRAM_BOT_USERNAME'] ?? null;

if ($botToken && strlen($botToken) > 40) {
    output("Bot token: valid format ({" . strlen($botToken) . "} chars)", 'ok');
} elseif ($botToken) {
    output("Bot token: seems short ({" . strlen($botToken) . "} chars), verify it's correct", 'warning');
    $warnings++;
} else {
    output("Bot token: NOT SET", 'error');
    $errors++;
}

if ($botUsername) {
    output("Bot username: {$botUsername}", 'ok');
} else {
    output("Bot username: NOT SET", 'warning');
    $warnings++;
}

// Summary
section('Summary');

echo "\n";
if ($errors === 0 && $warnings === 0) {
    output("All checks passed! Deployment looks good.", 'ok');
} elseif ($errors === 0) {
    output("No errors but {$warnings} warning(s). Review warnings above.", 'warning');
} else {
    output("{$errors} error(s) and {$warnings} warning(s). Fix errors before deployment.", 'error');
}
echo "\n";
echo "Next Steps:\n";
echo "1. If errors, fix them and re-run this script\n";
echo "2. Access /RockyTap/api/health/ in browser to test API\n";
echo "3. Access /RockyTap/api/debug/ for detailed diagnostics\n";
echo "4. Open the mini-app in Telegram to test full flow\n";
echo "5. Check logs at RockyTap/storage/logs/ghidar.log for errors\n";
echo "\n";


