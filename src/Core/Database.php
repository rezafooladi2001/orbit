<?php

declare(strict_types=1);

namespace Ghidar\Core;

use Ghidar\Config\Config;
use PDO;
use PDOException;

/**
 * Database connection manager for Ghidar application.
 * Provides PDO connection singleton with connection pooling, automatic reconnection,
 * and comprehensive error handling for production reliability.
 */
class Database
{
    private static ?PDO $connection = null;
    
    /**
     * Maximum number of reconnection attempts.
     */
    private const MAX_RECONNECT_ATTEMPTS = 3;
    
    /**
     * Base delay for exponential backoff (milliseconds).
     */
    private const BASE_RECONNECT_DELAY_MS = 100;
    
    /**
     * Connection timeout in seconds.
     */
    private const CONNECTION_TIMEOUT = 5;
    
    /**
     * MySQL error codes that indicate connection issues.
     * 2006 = Server has gone away
     * 2013 = Lost connection during query
     * 2014 = Commands out of sync
     * 2015 = Cannot connect to local MySQL server
     * 2002 = Can't connect to local MySQL server through socket
     * 2003 = Can't connect to MySQL server
     * 2005 = Unknown MySQL server host
     * HY000 = General error (often connection related)
     */
    private const CONNECTION_ERROR_CODES = [
        2002, 2003, 2005, 2006, 2013, 2014, 2015, 'HY000'
    ];
    
    /**
     * Error message patterns that indicate connection issues.
     */
    private const CONNECTION_ERROR_PATTERNS = [
        'gone away',
        'lost connection',
        'server has gone away',
        'connection refused',
        'connection reset',
        'no connection',
        'connection timed out',
        'can\'t connect',
        'cannot connect',
    ];

    /**
     * Get PDO database connection.
     * Creates connection on first call and reuses it.
     * Includes connection pooling and timeout configuration.
     *
     * @return PDO Database connection
     * @throws PDOException If connection fails after all retry attempts
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        return self::createConnection();
    }
    
    /**
     * Create a new database connection with retry logic.
     *
     * @return PDO Database connection
     * @throws PDOException If connection fails after all retry attempts
     */
    private static function createConnection(): PDO
    {
        $host = Config::get('DB_HOST', 'localhost');
        $port = Config::getInt('DB_PORT', 3306);
        $database = Config::get('DB_DATABASE');
        $username = Config::get('DB_USERNAME');
        $password = Config::get('DB_PASSWORD');

        // Database and username are required, but password can be empty (for local development)
        if ($database === null || $username === null) {
            throw new PDOException('Database configuration is incomplete: DB_DATABASE and DB_USERNAME are required');
        }
        
        // Allow empty password - use empty string if null
        $password = $password ?? '';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $options = self::buildConnectionOptions();
        
        $lastException = null;
        
        // Retry connection with exponential backoff
        for ($attempt = 1; $attempt <= self::MAX_RECONNECT_ATTEMPTS; $attempt++) {
            try {
                self::$connection = new PDO($dsn, $username, $password, $options);
                
                // Set session variables for better connection stability
                self::configureSession(self::$connection);
                
                return self::$connection;
            } catch (PDOException $e) {
                $lastException = $e;
                
                // Only retry on connection errors
                if (!self::isConnectionError($e) || $attempt >= self::MAX_RECONNECT_ATTEMPTS) {
                    break;
                }
                
                // Exponential backoff: 100ms, 200ms, 400ms...
                $delayMs = self::BASE_RECONNECT_DELAY_MS * pow(2, $attempt - 1);
                usleep($delayMs * 1000);
            }
        }
        
        throw $lastException ?? new PDOException('Failed to establish database connection');
    }
    
    /**
     * Build PDO connection options array.
     *
     * @return array<int, mixed> PDO options
     */
    private static function buildConnectionOptions(): array
    {
        // Check if persistent connections are enabled
        $usePersistent = Config::getBool('DB_PERSISTENT', false);
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => self::CONNECTION_TIMEOUT,
            PDO::ATTR_PERSISTENT => $usePersistent,
        ];
        
        // MySQL-specific options for connection stability
        // Use the new PHP 8.5+ constant if available, otherwise fall back to deprecated one
        if (class_exists('Pdo\Mysql') && defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')) {
            $options[\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY] = true;
        } elseif (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            // Suppress deprecation warning for older constant
            @$options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }
        
        // Set connect timeout for MySQL
        // Use the new PHP 8.5+ constant if available, otherwise use deprecated one with @ to suppress warning
        if (class_exists('Pdo\Mysql') && defined('Pdo\Mysql::ATTR_INIT_COMMAND')) {
            $options[\Pdo\Mysql::ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        } elseif (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            @$options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        // Add SSL/TLS support for TiDB Cloud and other SSL-enabled databases
        self::addSslOptions($options);
        
        return $options;
    }
    
    /**
     * Add SSL options to connection options array.
     *
     * @param array<int, mixed> &$options PDO options array (by reference)
     */
    private static function addSslOptions(array &$options): void
    {
        $sslCa = Config::get('DB_SSL_CA');
        
        if ($sslCa && is_string($sslCa) && file_exists($sslCa)) {
            self::setSslCaOption($options, $sslCa);
            return;
        }
        
        // For TiDB Cloud, SSL is required even if CA file is not found
        // Try to use system certificates
        $systemCerts = [
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/ssl/cert.pem',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];
        
        foreach ($systemCerts as $certPath) {
            if (file_exists($certPath)) {
                self::setSslCaOption($options, $certPath);
                break;
            }
        }
    }
    
    /**
     * Set SSL CA option based on PHP version.
     *
     * @param array<int, mixed> &$options PDO options array (by reference)
     * @param string $certPath Path to CA certificate
     */
    private static function setSslCaOption(array &$options, string $certPath): void
    {
        // Use new constant for PHP 8.5+, fallback to old constant for compatibility
        if (PHP_VERSION_ID >= 80500 && class_exists('\Pdo\Mysql')) {
            $options[\Pdo\Mysql::ATTR_SSL_CA] = $certPath;
            $options[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
        } else {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $certPath;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }
    
    /**
     * Configure MySQL session variables for connection stability.
     *
     * @param PDO $pdo Database connection
     */
    private static function configureSession(PDO $pdo): void
    {
        try {
            // Set wait_timeout to prevent premature connection closure
            // Default MySQL wait_timeout is 28800 (8 hours), but cloud providers may have shorter timeouts
            $waitTimeout = Config::getInt('DB_WAIT_TIMEOUT', 300); // 5 minutes default
            $pdo->exec("SET SESSION wait_timeout = {$waitTimeout}");
            
            // Set interactive_timeout as well
            $pdo->exec("SET SESSION interactive_timeout = {$waitTimeout}");
            
            // Enable strict mode for better data integrity
            $sqlMode = Config::get('DB_SQL_MODE');
            if ($sqlMode !== null) {
                $pdo->exec("SET SESSION sql_mode = '{$sqlMode}'");
            }
        } catch (PDOException $e) {
            // Log but don't fail - these are optimizations, not requirements
            error_log("[Database] Warning: Could not configure session: " . $e->getMessage());
        }
    }

    /**
     * Close database connection.
     */
    public static function close(): void
    {
        self::$connection = null;
    }

    /**
     * Reset database connection (forces reconnection on next getConnection() call).
     */
    public static function resetConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Check if connection is alive and reconnect if needed.
     * Handles "MySQL server has gone away" and other connection errors.
     * This should be used for critical operations that require guaranteed connectivity.
     *
     * @return PDO Valid database connection
     * @throws PDOException If reconnection fails after all attempts
     */
    public static function ensureConnection(): PDO
    {
        // If no connection exists, create one
        if (self::$connection === null) {
            return self::getConnection();
        }

        // Test if existing connection is alive
        if (self::isConnectionAlive(self::$connection)) {
            return self::$connection;
        }
        
        // Connection is dead, attempt reconnection with retry logic
        return self::reconnectWithBackoff();
    }
    
    /**
     * Test if a database connection is alive.
     *
     * @param PDO $pdo Connection to test
     * @return bool True if connection is alive
     * @throws PDOException If a non-connection error occurs (e.g., permission denied)
     */
    private static function isConnectionAlive(PDO $pdo): bool
    {
        try {
            // Use a cheap query to test connection
            $pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            // Only treat actual connection errors as "dead connection"
            // Other errors (permission denied, resource limits, etc.) should be re-thrown
            // to prevent unnecessary reconnection and potential transaction state loss
            if (self::isConnectionError($e)) {
                return false;
            }
            
            // Non-connection error - re-throw to avoid masking the real problem
            throw $e;
        }
    }
    
    /**
     * Reconnect to database with exponential backoff.
     *
     * @return PDO New database connection
     * @throws PDOException If reconnection fails after all attempts
     */
    private static function reconnectWithBackoff(): PDO
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= self::MAX_RECONNECT_ATTEMPTS; $attempt++) {
            try {
                // Reset the connection
                self::resetConnection();
                
                // Attempt to create a new connection
                return self::createConnection();
            } catch (PDOException $e) {
                $lastException = $e;
                
                // Log the reconnection attempt
                error_log(sprintf(
                    "[Database] Reconnection attempt %d/%d failed: %s",
                    $attempt,
                    self::MAX_RECONNECT_ATTEMPTS,
                    $e->getMessage()
                ));
                
                // Don't retry if it's not a connection error
                if (!self::isConnectionError($e)) {
                    break;
                }
                
                // Don't sleep after the last attempt
                if ($attempt < self::MAX_RECONNECT_ATTEMPTS) {
                    // Exponential backoff with jitter
                    $delayMs = self::BASE_RECONNECT_DELAY_MS * pow(2, $attempt - 1);
                    $jitter = rand(0, (int)($delayMs * 0.25)); // Add up to 25% jitter
                    usleep(($delayMs + $jitter) * 1000);
                }
            }
        }
        
        throw $lastException ?? new PDOException('Failed to reconnect to database after ' . self::MAX_RECONNECT_ATTEMPTS . ' attempts');
    }
    
    /**
     * Check if an exception indicates a connection error.
     *
     * @param PDOException $e Exception to check
     * @return bool True if this is a connection error that warrants retry
     */
    private static function isConnectionError(PDOException $e): bool
    {
        $errorCode = $e->getCode();
        $errorMessage = strtolower($e->getMessage());
        
        // Check error code
        if (in_array($errorCode, self::CONNECTION_ERROR_CODES, false)) {
            return true;
        }
        
        // Check error message patterns
        foreach (self::CONNECTION_ERROR_PATTERNS as $pattern) {
            if (strpos($errorMessage, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Execute a callback with automatic reconnection on connection errors.
     * Useful for wrapping critical database operations.
     *
     * @template T
     * @param callable(): T $callback The callback to execute
     * @return T The callback's return value
     * @throws PDOException If the operation fails after reconnection attempts
     */
    public static function withConnection(callable $callback)
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= self::MAX_RECONNECT_ATTEMPTS; $attempt++) {
            try {
                $pdo = self::ensureConnection();
                return $callback($pdo);
            } catch (PDOException $e) {
                $lastException = $e;
                
                if (!self::isConnectionError($e) || $attempt >= self::MAX_RECONNECT_ATTEMPTS) {
                    break;
                }
                
                // Reset connection for retry
                self::resetConnection();
                
                // Exponential backoff
                $delayMs = self::BASE_RECONNECT_DELAY_MS * pow(2, $attempt - 1);
                usleep($delayMs * 1000);
            }
        }
        
        throw $lastException;
    }
    
    /**
     * Execute a transaction with automatic reconnection support.
     *
     * @template T
     * @param callable(PDO): T $callback The callback to execute within transaction
     * @return T The callback's return value
     * @throws PDOException If the operation fails
     */
    public static function transaction(callable $callback)
    {
        $pdo = self::ensureConnection();
        
        $pdo->beginTransaction();
        
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get connection statistics for monitoring.
     *
     * @return array<string, mixed> Connection statistics
     */
    public static function getConnectionStats(): array
    {
        if (self::$connection === null) {
            return [
                'connected' => false,
                'server_info' => null,
                'connection_status' => null,
            ];
        }
        
        try {
            return [
                'connected' => true,
                'server_info' => self::$connection->getAttribute(PDO::ATTR_SERVER_INFO),
                'connection_status' => self::$connection->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                'server_version' => self::$connection->getAttribute(PDO::ATTR_SERVER_VERSION),
                'driver_name' => self::$connection->getAttribute(PDO::ATTR_DRIVER_NAME),
                'persistent' => self::$connection->getAttribute(PDO::ATTR_PERSISTENT),
            ];
        } catch (PDOException $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Health check for the database connection.
     *
     * @return array{healthy: bool, message: string, latency_ms?: float}
     */
    public static function healthCheck(): array
    {
        $startTime = microtime(true);
        
        try {
            $pdo = self::ensureConnection();
            $pdo->query('SELECT 1');
            
            $latencyMs = (microtime(true) - $startTime) * 1000;
            
            return [
                'healthy' => true,
                'message' => 'Database connection is healthy',
                'latency_ms' => round($latencyMs, 2),
            ];
        } catch (PDOException $e) {
            return [
                'healthy' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
