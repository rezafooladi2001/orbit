<?php
/**
 * Legacy configuration file for backward compatibility.
 * New code should use Ghidar\Config\Config directly.
 * This file loads from environment variables via Config class.
 */

// Bootstrap application if not already loaded
if (!defined('GHIDAR_ROOT')) {
    require_once __DIR__ . '/../../bootstrap.php';
}

use Ghidar\Config\Config;

header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Load configuration from environment variables
$apiKey = Config::get('TELEGRAM_BOT_TOKEN', 'your_telegram_api_key');
$botUsername = Config::get('TELEGRAM_BOT_USERNAME', 'my_bot_name');
$base_url = Config::get('APP_URL', 'https://yoururlhere.com');
$web_app = $base_url . '/RockyTap/ghidar/'; // Ghidar Mini App entry point

// Database configuration
$DB = [
    'dbname' => Config::get('DB_DATABASE', 'your_database'),
    'username' => Config::get('DB_USERNAME', 'your_database_user'),
    'password' => Config::get('DB_PASSWORD', 'Your_database_password')
];

// Admin user IDs (comma-separated in .env)
$admins_user_id = Config::getIntArray('ADMIN_TELEGRAM_IDS', []);

// Legacy support for old config key
if (empty($admins_user_id)) {
    $admins_user_id = Config::getIntArray('ADMINS_USER_ID', []);
}