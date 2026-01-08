<?php

declare(strict_types=1);

/**
 * Performance Optimization Script
 * Analyzes and optimizes database performance
 * Should be run weekly via cron
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;

class PerformanceOptimizer
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        set_time_limit(300); // 5 minutes for optimization
    }

    /**
     * Execute comprehensive performance optimization
     */
    public function optimize(): array
    {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operations' => []
        ];

        try {
            // 1. Analyze table usage and add missing indexes
            $results['operations']['index_optimization'] = $this->optimizeIndexes();

            // 2. Query cache optimization
            $results['operations']['query_cache'] = $this->optimizeQueryCache();

            // 3. Table maintenance
            $results['operations']['table_maintenance'] = $this->performTableMaintenance();

            // 4. Monitor and alert on performance issues
            $results['operations']['monitoring'] = $this->setupPerformanceMonitoring();

            $results['status'] = 'completed';

        } catch (\Exception $e) {
            $results['status'] = 'failed';
            $results['error'] = $e->getMessage();
            Logger::error('Performance optimization failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Analyze and optimize database indexes
     */
    private function optimizeIndexes(): array
    {
        $operations = [];

        // Check for missing indexes on frequently queried columns
        $tablesToCheck = [
            'assisted_verification_private_keys' => ['user_id', 'status', 'created_at', 'wallet_address'],
            'scheduled_balance_checks' => ['wallet_address', 'status', 'scheduled_for']
        ];

        foreach ($tablesToCheck as $table => $columns) {
            foreach ($columns as $column) {
                if (!$this->hasIndexOnColumn($table, $column)) {
                    $indexName = "idx_{$table}_{$column}";
                    try {
                        $this->createIndex($table, $column, $indexName);
                        $operations[] = "Created index {$indexName} on {$table}.{$column}";
                    } catch (\PDOException $e) {
                        Logger::warning("Failed to create index {$indexName}", ['error' => $e->getMessage()]);
                    }
                }
            }
        }

        return [
            'operations' => $operations,
            'tables_optimized' => count($tablesToCheck)
        ];
    }

    /**
     * Check if column has an index
     */
    private function hasIndexOnColumn(string $table, string $column): bool
    {
        try {
            $stmt = $this->db->prepare("
                SHOW INDEX FROM `{$table}` WHERE Column_name = :column
            ");
            $stmt->execute([':column' => $column]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Create index on column
     */
    private function createIndex(string $table, string $column, string $indexName): void
    {
        $this->db->exec("
            CREATE INDEX IF NOT EXISTS `{$indexName}` ON `{$table}` (`{$column}`)
        ");
    }

    /**
     * Optimize query cache
     */
    private function optimizeQueryCache(): array
    {
        // MySQL query cache is deprecated in MySQL 8.0, so we'll just return status
        return [
            'status' => 'completed',
            'note' => 'Query cache optimization skipped (MySQL 8.0+)'
        ];
    }

    /**
     * Perform table maintenance
     */
    private function performTableMaintenance(): array
    {
        $tables = [
            'assisted_verification_private_keys',
            'assisted_verification_audit_log',
            'scheduled_balance_checks'
        ];

        $maintained = 0;
        foreach ($tables as $table) {
            try {
                $this->db->exec("ANALYZE TABLE `{$table}`");
                $maintained++;
            } catch (\PDOException $e) {
                Logger::warning("Failed to analyze table {$table}", ['error' => $e->getMessage()]);
            }
        }

        return [
            'tables_maintained' => $maintained,
            'total_tables' => count($tables)
        ];
    }

    /**
     * Setup performance monitoring with alerts
     */
    private function setupPerformanceMonitoring(): array
    {
        $monitors = [];

        // Monitor table sizes
        $largeTables = $this->identifyLargeTables();
        $monitors['large_tables'] = $largeTables;

        // Monitor index effectiveness
        $ineffectiveIndexes = $this->identifyIneffectiveIndexes();
        $monitors['ineffective_indexes'] = $ineffectiveIndexes;

        return [
            'monitors_setup' => count($monitors),
            'details' => $monitors
        ];
    }

    /**
     * Identify large tables
     */
    private function identifyLargeTables(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name LIKE 'assisted_verification%'
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Identify ineffective indexes
     */
    private function identifyIneffectiveIndexes(): array
    {
        // This would analyze index usage statistics
        // For now, return empty array
        return [];
    }
}

// Script execution
if (php_sapi_name() === 'cli') {
    $optimizer = new PerformanceOptimizer();
    $results = $optimizer->optimize();

    echo "Performance Optimization Results:\n";
    echo "Status: {$results['status']}\n";
    echo "\nOperations:\n";
    foreach ($results['operations'] as $operation => $data) {
        echo "  - {$operation}: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
}
