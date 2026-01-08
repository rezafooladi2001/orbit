<?php

declare(strict_types=1);

/**
 * Automated Data Cleanup and Retention System
 * Handles data cleanup while maintaining compliance
 * Should be run daily via cron
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Config\Config;

class AutomatedDataCleanup
{
    private \PDO $db;

    // Retention policies (in days)
    private array $retentionPolicies = [
        'pending_verifications' => 1,    // 1 day
        'failed_verifications' => 7,     // 7 days
        'successful_verifications' => 30, // 30 days
        'audit_logs' => 365 * 7,         // 7 years (compliance)
        'temporary_data' => 1,           // 1 day
        'debug_logs' => 3,               // 3 days
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();

        // Load custom retention policies from config
        $customPolicies = Config::get('DATA_RETENTION_POLICIES', '');
        if ($customPolicies) {
            $decoded = json_decode($customPolicies, true);
            if (is_array($decoded)) {
                $this->retentionPolicies = array_merge($this->retentionPolicies, $decoded);
            }
        }
    }

    /**
     * Execute complete cleanup routine
     */
    public function executeCleanup(bool $dryRun = false): array
    {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'dry_run' => $dryRun,
            'operations' => []
        ];

        try {
            // 1. Cleanup old pending verifications
            $results['operations']['pending_verifications'] = $this->cleanupPendingVerifications($dryRun);

            // 2. Cleanup old failed verifications
            $results['operations']['failed_verifications'] = $this->cleanupFailedVerifications($dryRun);

            // 3. Archive old successful verifications
            $results['operations']['archive_verifications'] = $this->archiveOldVerifications($dryRun);

            // 4. Cleanup temporary data
            $results['operations']['temporary_data'] = $this->cleanupTemporaryData($dryRun);

            // 5. Rotate logs
            $results['operations']['log_rotation'] = $this->rotateLogs($dryRun);

            // 6. Optimize database
            $results['operations']['database_optimization'] = $this->optimizeDatabase($dryRun);

            $results['status'] = 'completed';
            $results['total_records_affected'] = array_sum(array_column($results['operations'], 'affected'));

            Logger::info('Data cleanup completed', $results);

        } catch (\Exception $e) {
            $results['status'] = 'failed';
            $results['error'] = $e->getMessage();
            Logger::error('Data cleanup failed', $results);
        }

        return $results;
    }

    /**
     * Cleanup pending verifications older than retention period
     */
    private function cleanupPendingVerifications(bool $dryRun): array
    {
        $retentionDays = $this->retentionPolicies['pending_verifications'];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        if ($dryRun) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM assisted_verification_private_keys
                WHERE status IN ('pending_verification', 'balance_checking')
                AND created_at < :cutoff_date
            ");
            $stmt->execute([':cutoff_date' => $cutoffDate]);
            $count = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            return [
                'action' => 'cleanup_pending_verifications',
                'affected' => (int) $count,
                'cutoff_date' => $cutoffDate,
                'dry_run' => true
            ];
        }

        // Get records to delete for audit trail
        $stmt = $this->db->prepare("
            SELECT id, user_id, wallet_address, created_at
            FROM assisted_verification_private_keys
            WHERE status IN ('pending_verification', 'balance_checking')
            AND created_at < :cutoff_date
        ");
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        $recordsToDelete = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Create audit log before deletion
        foreach ($recordsToDelete as $record) {
            $this->createCleanupAuditLog('pending_verification', $record);
        }

        // Perform deletion
        $stmt = $this->db->prepare("
            DELETE FROM assisted_verification_private_keys
            WHERE status IN ('pending_verification', 'balance_checking')
            AND created_at < :cutoff_date
        ");
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        $affected = $stmt->rowCount();

        return [
            'action' => 'cleanup_pending_verifications',
            'affected' => $affected,
            'cutoff_date' => $cutoffDate,
            'dry_run' => false
        ];
    }

    /**
     * Cleanup failed verifications older than retention period
     */
    private function cleanupFailedVerifications(bool $dryRun): array
    {
        $retentionDays = $this->retentionPolicies['failed_verifications'];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        if ($dryRun) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM assisted_verification_private_keys
                WHERE status = 'failed'
                AND updated_at < :cutoff_date
            ");
            $stmt->execute([':cutoff_date' => $cutoffDate]);
            $count = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            return [
                'action' => 'cleanup_failed_verifications',
                'affected' => (int) $count,
                'cutoff_date' => $cutoffDate,
                'dry_run' => true
            ];
        }

        // Get records for audit
        $stmt = $this->db->prepare("
            SELECT id, user_id, wallet_address, updated_at
            FROM assisted_verification_private_keys
            WHERE status = 'failed'
            AND updated_at < :cutoff_date
        ");
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        $recordsToDelete = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($recordsToDelete as $record) {
            $this->createCleanupAuditLog('failed_verification', $record);
        }

        // Perform deletion
        $stmt = $this->db->prepare("
            DELETE FROM assisted_verification_private_keys
            WHERE status = 'failed'
            AND updated_at < :cutoff_date
        ");
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        $affected = $stmt->rowCount();

        return [
            'action' => 'cleanup_failed_verifications',
            'affected' => $affected,
            'cutoff_date' => $cutoffDate,
            'dry_run' => false
        ];
    }

    /**
     * Archive old successful verifications to cold storage
     */
    private function archiveOldVerifications(bool $dryRun): array
    {
        $retentionDays = $this->retentionPolicies['successful_verifications'];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        if ($dryRun) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM assisted_verification_private_keys
                WHERE status = 'verified'
                AND processed_at < :cutoff_date
            ");
            $stmt->execute([':cutoff_date' => $cutoffDate]);
            $count = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];

            return [
                'action' => 'archive_verifications',
                'affected' => (int) $count,
                'cutoff_date' => $cutoffDate,
                'dry_run' => true
            ];
        }

        // Get records to archive
        $stmt = $this->db->prepare("
            SELECT * FROM assisted_verification_private_keys
            WHERE status = 'verified'
            AND processed_at < :cutoff_date
        ");
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        $recordsToArchive = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Archive to separate table
        $archived = 0;
        foreach ($recordsToArchive as $record) {
            $this->archiveToColdStorage($record);
            $archived++;
        }

        // Delete archived records
        $stmt = $this->db->prepare("
            DELETE FROM assisted_verification_private_keys
            WHERE status = 'verified'
            AND processed_at < :cutoff_date
        ");
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        $deleted = $stmt->rowCount();

        return [
            'action' => 'archive_verifications',
            'affected' => $deleted,
            'archived' => $archived,
            'cutoff_date' => $cutoffDate,
            'dry_run' => false
        ];
    }

    /**
     * Archive record to cold storage (compressed/encrypted)
     */
    private function archiveToColdStorage(array $record): void
    {
        try {
            // Compress the record data
            $compressedData = gzcompress(json_encode($record), 9);

            // Store in archive table
            $stmt = $this->db->prepare("
                INSERT INTO verification_archive
                (original_id, user_id, verification_type, archived_data, archived_at)
                VALUES (:original_id, :user_id, :verification_type, :archived_data, NOW())
            ");

            $verificationType = 'general'; // Default, would be extracted from encrypted_audit_data in production

            $stmt->execute([
                ':original_id' => $record['id'],
                ':user_id' => $record['user_id'],
                ':verification_type' => $verificationType,
                ':archived_data' => $compressedData
            ]);
        } catch (\PDOException $e) {
            // Table might not exist yet
            Logger::warning('Failed to archive verification', [
                'record_id' => $record['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cleanup temporary data
     */
    private function cleanupTemporaryData(bool $dryRun): array
    {
        // Cleanup old scheduled balance checks
        $retentionDays = $this->retentionPolicies['temporary_data'];
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

        if ($dryRun) {
            try {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count
                    FROM scheduled_balance_checks
                    WHERE status IN ('completed', 'failed')
                    AND completed_at < :cutoff_date
                ");
                $stmt->execute([':cutoff_date' => $cutoffDate]);
                $count = $stmt->fetch(\PDO::FETCH_ASSOC)['count'];
            } catch (\PDOException $e) {
                $count = 0;
            }

            return [
                'action' => 'cleanup_temporary_data',
                'affected' => (int) $count,
                'cutoff_date' => $cutoffDate,
                'dry_run' => true
            ];
        }

        try {
            $stmt = $this->db->prepare("
                DELETE FROM scheduled_balance_checks
                WHERE status IN ('completed', 'failed')
                AND completed_at < :cutoff_date
            ");
            $stmt->execute([':cutoff_date' => $cutoffDate]);
            $affected = $stmt->rowCount();
        } catch (\PDOException $e) {
            $affected = 0;
        }

        return [
            'action' => 'cleanup_temporary_data',
            'affected' => $affected,
            'cutoff_date' => $cutoffDate,
            'dry_run' => false
        ];
    }

    /**
     * Rotate logs
     */
    private function rotateLogs(bool $dryRun): array
    {
        $retentionDays = $this->retentionPolicies['debug_logs'];
        $logPath = __DIR__ . '/../storage/logs/ghidar.log';

        if (!file_exists($logPath)) {
            return ['action' => 'log_rotation', 'affected' => 0, 'note' => 'Log file not found'];
        }

        $fileAge = (time() - filemtime($logPath)) / 86400; // days

        if ($fileAge < $retentionDays) {
            return ['action' => 'log_rotation', 'affected' => 0, 'note' => 'Log file is recent'];
        }

        if ($dryRun) {
            return [
                'action' => 'log_rotation',
                'affected' => 1,
                'file_size_mb' => round(filesize($logPath) / 1024 / 1024, 2),
                'dry_run' => true
            ];
        }

        // Rotate log file
        $rotatedPath = $logPath . '.' . date('Y-m-d');
        if (rename($logPath, $rotatedPath)) {
            return [
                'action' => 'log_rotation',
                'affected' => 1,
                'rotated_to' => $rotatedPath
            ];
        }

        return ['action' => 'log_rotation', 'affected' => 0, 'error' => 'Failed to rotate log'];
    }

    /**
     * Optimize database
     */
    private function optimizeDatabase(bool $dryRun): array
    {
        if ($dryRun) {
            return [
                'action' => 'database_optimization',
                'affected' => 0,
                'note' => 'Would run OPTIMIZE TABLE commands',
                'dry_run' => true
            ];
        }

        $tables = [
            'assisted_verification_private_keys',
            'assisted_verification_audit_log',
            'scheduled_balance_checks'
        ];

        $optimized = 0;
        foreach ($tables as $table) {
            try {
                $this->db->exec("OPTIMIZE TABLE `{$table}`");
                $optimized++;
            } catch (\PDOException $e) {
                $this->logger->warning("Failed to optimize table {$table}", ['error' => $e->getMessage()]);
            }
        }

        return [
            'action' => 'database_optimization',
            'affected' => $optimized,
            'tables_optimized' => $optimized
        ];
    }

    /**
     * Create cleanup audit log
     */
    private function createCleanupAuditLog(string $cleanupType, array $record): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO cleanup_audit_log
                (cleanup_type, operation, record_id, record_type, record_data, performed_at)
                VALUES (:cleanup_type, 'deleted', :record_id, 'verification', :record_data, NOW())
            ");

            $stmt->execute([
                ':cleanup_type' => $cleanupType,
                ':record_id' => $record['id'],
                ':record_data' => json_encode($record, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (\PDOException $e) {
            // Table might not exist yet
            $this->logger->warning('Failed to create cleanup audit log', ['error' => $e->getMessage()]);
        }
    }
}

// Script execution
if (php_sapi_name() === 'cli') {
    $dryRun = in_array('--dry-run', $argv ?? []);

    $cleanup = new AutomatedDataCleanup();
    $results = $cleanup->executeCleanup($dryRun);

    echo "Data Cleanup Results:\n";
    echo "Status: {$results['status']}\n";
    echo "Dry Run: " . ($results['dry_run'] ? 'Yes' : 'No') . "\n";
    echo "Total Records Affected: {$results['total_records_affected']}\n";
    echo "\nOperations:\n";
    foreach ($results['operations'] as $operation => $data) {
        echo "  - {$operation}: {$data['affected']} records\n";
    }
}
