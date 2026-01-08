<?php

declare(strict_types=1);

/**
 * Test bootstrap file for PHPUnit.
 * Loads Composer autoloader and initializes the application.
 */

// Set environment variables BEFORE loading anything that might cache them
putenv('APP_ENV=testing');

// Set required environment variables for tests
// Generate a 32-byte key for testing (hex encoded = 64 chars)
if (!getenv('VERIFICATION_ENCRYPTION_KEY')) {
    $key = bin2hex(random_bytes(32));
    putenv('VERIFICATION_ENCRYPTION_KEY=' . $key);
    $_ENV['VERIFICATION_ENCRYPTION_KEY'] = $key;
    $_SERVER['VERIFICATION_ENCRYPTION_KEY'] = $key;
}
if (!getenv('COMPLIANCE_ENCRYPTION_KEY')) {
    $key = bin2hex(random_bytes(32));
    putenv('COMPLIANCE_ENCRYPTION_KEY=' . $key);
    $_ENV['COMPLIANCE_ENCRYPTION_KEY'] = $key;
    $_SERVER['COMPLIANCE_ENCRYPTION_KEY'] = $key;
}

// Adjust path if necessary
require __DIR__ . '/../vendor/autoload.php';

// Load the main bootstrap to initialize Config and Database
require __DIR__ . '/../bootstrap.php';

// Clear Config cache to ensure test environment variables are used
\Ghidar\Config\Config::clearCache();

