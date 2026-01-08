<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Ghidar\Core\Database;
use PDO;

/**
 * Base test case for all Ghidar tests.
 * Provides database setup and cleanup helpers.
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Schema creation flag - ensures schema is created only once per test suite.
     */
    private static bool $schemaCreated = false;

    /**
     * Set up database schema once before all tests run.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!self::$schemaCreated) {
            // Ensure connection is alive before creating schema
            Database::ensureConnection();
            
            // Include the create_tables script
            // The script now uses bootstrap.php and Database::getConnection()
            ob_start();
            try {
                require __DIR__ . '/../RockyTap/database/create_tables.php';
            } catch (\Throwable $e) {
                ob_end_clean();
                throw new \RuntimeException('Failed to create database schema: ' . $e->getMessage(), 0, $e);
            }
            ob_end_clean();

            self::$schemaCreated = true;
        } else {
            // Ensure connection is alive even if schema was already created
            Database::ensureConnection();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure connection is alive (reconnect if needed)
        Database::ensureConnection();

        // Truncate test tables before each test
        $this->truncateTestTables();
    }

    /**
     * Truncate test tables to ensure clean state.
     */
    protected function truncateTestTables(): void
    {
        // Ensure connection is alive before truncating
        $pdo = Database::ensureConnection();

        // Disable foreign key checks temporarily
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        } catch (\PDOException $e) {
            // If connection is lost, reconnect and retry
            if (strpos($e->getMessage(), 'gone away') !== false || $e->getCode() == 2006) {
                $pdo = Database::ensureConnection();
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            } else {
                throw $e;
            }
        }

        // Truncate tables in order (respecting foreign key dependencies)
        $tables = [
            'referral_rewards',
            'api_rate_limits',
            'lottery_winners',
            'lottery_tickets',
            'lotteries',
            'ai_trader_actions',
            'ai_performance_history',
            'ai_accounts',
            'withdrawals',
            'deposits',
            'blockchain_addresses',
            'airdrop_actions',
            'wallets',
            'user_tasks',
            'tasks',
            'user_missions',
            'missions',
            'leaguesTasks',
            'refTasks',
            'sending',
            'users',
        ];

        foreach ($tables as $table) {
            try {
                $pdo->exec("TRUNCATE TABLE `{$table}`");
            } catch (\PDOException $e) {
                // Table might not exist, or connection might be lost
                if (strpos($e->getMessage(), 'gone away') !== false || $e->getCode() == 2006) {
                    // Reconnect and retry this table
                    $pdo = Database::ensureConnection();
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    try {
                        $pdo->exec("TRUNCATE TABLE `{$table}`");
                    } catch (\PDOException $retryE) {
                        // Table might not exist, which is okay
                    }
                }
                // Otherwise ignore - table might not exist
            }
        }

        // Re-enable foreign key checks
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\PDOException $e) {
            // If connection is lost, reconnect and retry
            if (strpos($e->getMessage(), 'gone away') !== false || $e->getCode() == 2006) {
                $pdo = Database::ensureConnection();
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
    }

    /**
     * Get PDO connection for direct database access in tests.
     */
    protected function getPdo(): PDO
    {
        return Database::ensureConnection();
    }
}

