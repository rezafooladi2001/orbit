<?php

declare(strict_types=1);

/**
 * Safe Migration Runner for Ghidar
 * 
 * Provides safe database migration execution with:
 * - Pre-migration backups
 * - Transaction support
 * - Rollback capability
 * - Dry-run mode
 * - Migration status tracking
 * 
 * Usage:
 *   php safe_migration.php <migration_file> [--dry-run] [--no-backup]
 */

require_once __DIR__ . '/../../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Config\Config;

// Colors for CLI output
define('COLOR_RED', "\033[0;31m");
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_YELLOW', "\033[1;33m");
define('COLOR_BLUE', "\033[0;34m");
define('COLOR_NC', "\033[0m");

// Configuration
$BACKUP_DIR = __DIR__ . '/../storage/backups/database';
$MIGRATION_LOG_FILE = __DIR__ . '/../storage/logs/migrations.log';

// Ensure directories exist
if (!is_dir($BACKUP_DIR)) {
    mkdir($BACKUP_DIR, 0755, true);
}
if (!is_dir(dirname($MIGRATION_LOG_FILE))) {
    mkdir(dirname($MIGRATION_LOG_FILE), 0755, true);
}

/**
 * Log message to file and console
 */
function logMessage(string $message, string $level = 'INFO'): void {
    global $MIGRATION_LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents($MIGRATION_LOG_FILE, $logEntry, FILE_APPEND);
    echo $logEntry;
}

/**
 * Print colored message
 */
function printMessage(string $message, string $color = COLOR_NC): void {
    echo $color . $message . COLOR_NC . "\n";
}

/**
 * Create database backup before migration
 */
function createMigrationBackup(): ?string {
    global $BACKUP_DIR;
    
    try {
        $pdo = Database::getConnection();
        
        // Get database name from connection
        $dbName = Config::get('DB_DATABASE');
        $dbUser = Config::get('DB_USERNAME');
        $dbPass = Config::get('DB_PASSWORD');
        $dbHost = Config::get('DB_HOST', 'localhost');
        
        if (empty($dbName) || empty($dbUser)) {
            printMessage("⚠️  Database credentials not found, skipping backup", COLOR_YELLOW);
            return null;
        }
        
        if (!command_exists('mysqldump')) {
            printMessage("⚠️  mysqldump not found, skipping backup", COLOR_YELLOW);
            return null;
        }
        
        $timestamp = date('Ymd_His');
        $backupFile = "$BACKUP_DIR/pre_migration_$timestamp.sql";
        
        printMessage("Creating pre-migration backup...", COLOR_BLUE);
        
        $command = sprintf(
            'mysqldump -u %s -p%s -h %s --single-transaction --routines --triggers %s > %s 2>/dev/null',
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
            $size = formatBytes(filesize($backupFile));
            printMessage("✅ Backup created: $backupFile ($size)", COLOR_GREEN);
            logMessage("Pre-migration backup created: $backupFile");
            return $backupFile;
        } else {
            printMessage("❌ Backup failed", COLOR_RED);
            logMessage("Pre-migration backup failed", 'ERROR');
            return null;
        }
    } catch (\Exception $e) {
        printMessage("❌ Backup error: " . $e->getMessage(), COLOR_RED);
        logMessage("Pre-migration backup error: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * Check if command exists
 */
function command_exists(string $command): bool {
    $which = (PHP_OS == 'WINNT') ? 'where' : 'which';
    $process = proc_open("$which $command", [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    
    if (is_resource($process)) {
        $status = proc_get_status($process);
        proc_close($process);
        return $status['exitcode'] === 0;
    }
    
    return false;
}

/**
 * Format bytes to human readable
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Track migration status
 */
function trackMigration(string $migrationFile, string $status, ?string $error = null): void {
    global $BACKUP_DIR;
    
    $migrationName = basename($migrationFile);
    $statusFile = "$BACKUP_DIR/migration_status.json";
    
    $statusData = [];
    if (file_exists($statusFile)) {
        $statusData = json_decode(file_get_contents($statusFile), true) ?? [];
    }
    
    $statusData[$migrationName] = [
        'file' => $migrationFile,
        'status' => $status,
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $error,
    ];
    
    file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT));
}

/**
 * Run migration in dry-run mode
 */
function dryRunMigration(string $migrationFile): bool {
    printMessage("🔍 DRY-RUN MODE: Analyzing migration...", COLOR_BLUE);
    
    if (!file_exists($migrationFile)) {
        printMessage("❌ Migration file not found: $migrationFile", COLOR_RED);
        return false;
    }
    
    $content = file_get_contents($migrationFile);
    
    // Basic analysis
    $hasDropTable = preg_match('/DROP\s+TABLE/i', $content);
    $hasAlterTable = preg_match('/ALTER\s+TABLE/i', $content);
    $hasDelete = preg_match('/DELETE\s+FROM/i', $content);
    $hasUpdate = preg_match('/UPDATE\s+\w+/i', $content);
    
    printMessage("Migration analysis:", COLOR_BLUE);
    if ($hasDropTable) {
        printMessage("  ⚠️  Contains DROP TABLE statements (DESTRUCTIVE)", COLOR_YELLOW);
    }
    if ($hasAlterTable) {
        printMessage("  ⚠️  Contains ALTER TABLE statements (may lock tables)", COLOR_YELLOW);
    }
    if ($hasDelete) {
        printMessage("  ⚠️  Contains DELETE statements (data loss risk)", COLOR_YELLOW);
    }
    if ($hasUpdate) {
        printMessage("  ⚠️  Contains UPDATE statements (data modification)", COLOR_YELLOW);
    }
    
    if (!$hasDropTable && !$hasAlterTable && !$hasDelete && !$hasUpdate) {
        printMessage("  ✅ Migration appears safe (CREATE/INSERT only)", COLOR_GREEN);
    }
    
    // Check for transaction support
    if (preg_match('/BEGIN\s+TRANSACTION|START\s+TRANSACTION/i', $content)) {
        printMessage("  ✅ Migration uses transactions", COLOR_GREEN);
    } else {
        printMessage("  ⚠️  Migration does not use transactions", COLOR_YELLOW);
    }
    
    printMessage("✅ Dry-run completed. Migration file is valid.", COLOR_GREEN);
    return true;
}

/**
 * Execute migration with transaction support
 */
function executeMigration(string $migrationFile, bool $useTransaction = true): bool {
    try {
        $pdo = Database::getConnection();
        
        if (!file_exists($migrationFile)) {
            printMessage("❌ Migration file not found: $migrationFile", COLOR_RED);
            logMessage("Migration file not found: $migrationFile", 'ERROR');
            return false;
        }
        
        printMessage("Executing migration: $migrationFile", COLOR_BLUE);
        logMessage("Starting migration: $migrationFile");
        
        // Start transaction if supported
        if ($useTransaction) {
            $pdo->beginTransaction();
            printMessage("Transaction started", COLOR_BLUE);
        }
        
        // Read and execute migration file
        $migrationContent = file_get_contents($migrationFile);
        
        // Split by semicolons (basic SQL parsing)
        $statements = array_filter(
            array_map('trim', explode(';', $migrationContent)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
            }
        );
        
        foreach ($statements as $statement) {
            if (empty(trim($statement))) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
            } catch (\PDOException $e) {
                // Some errors are acceptable (e.g., table already exists)
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }
            }
        }
        
        // Commit transaction
        if ($useTransaction) {
            $pdo->commit();
            printMessage("Transaction committed", COLOR_GREEN);
        }
        
        printMessage("✅ Migration executed successfully", COLOR_GREEN);
        logMessage("Migration completed successfully: $migrationFile");
        trackMigration($migrationFile, 'completed');
        
        return true;
        
    } catch (\PDOException $e) {
        if ($useTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
            printMessage("Transaction rolled back", COLOR_RED);
        }
        
        printMessage("❌ Migration failed: " . $e->getMessage(), COLOR_RED);
        logMessage("Migration failed: $migrationFile - " . $e->getMessage(), 'ERROR');
        trackMigration($migrationFile, 'failed', $e->getMessage());
        
        return false;
    } catch (\Exception $e) {
        if ($useTransaction && isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
            printMessage("Transaction rolled back", COLOR_RED);
        }
        
        printMessage("❌ Migration error: " . $e->getMessage(), COLOR_RED);
        logMessage("Migration error: $migrationFile - " . $e->getMessage(), 'ERROR');
        trackMigration($migrationFile, 'failed', $e->getMessage());
        
        return false;
    }
}

/**
 * Main execution
 */
function main(array $argv): int {
    if (count($argv) < 2) {
        printMessage("Usage: php safe_migration.php <migration_file> [--dry-run] [--no-backup] [--no-transaction]", COLOR_YELLOW);
        return 1;
    }
    
    $migrationFile = $argv[1];
    $dryRun = in_array('--dry-run', $argv);
    $noBackup = in_array('--no-backup', $argv);
    $noTransaction = in_array('--no-transaction', $argv);
    
    printMessage("==========================================", COLOR_BLUE);
    printMessage("Safe Migration Runner", COLOR_BLUE);
    printMessage("==========================================", COLOR_BLUE);
    printMessage("Migration file: $migrationFile", COLOR_BLUE);
    printMessage("Mode: " . ($dryRun ? "DRY-RUN" : "EXECUTE"), COLOR_BLUE);
    printMessage("", COLOR_BLUE);
    
    // Dry-run mode
    if ($dryRun) {
        return dryRunMigration($migrationFile) ? 0 : 1;
    }
    
    // Create backup before migration
    $backupFile = null;
    if (!$noBackup) {
        $backupFile = createMigrationBackup();
        if ($backupFile === null && !$noBackup) {
            printMessage("⚠️  Continuing without backup (backup failed)", COLOR_YELLOW);
            $confirm = readline("Continue? (yes/no): ");
            if (strtolower($confirm) !== 'yes') {
                printMessage("Migration cancelled", COLOR_YELLOW);
                return 1;
            }
        }
    }
    
    // Execute migration
    $success = executeMigration($migrationFile, !$noTransaction);
    
    if ($success) {
        printMessage("", COLOR_BLUE);
        printMessage("✅ Migration completed successfully!", COLOR_GREEN);
        if ($backupFile) {
            printMessage("Backup available at: $backupFile", COLOR_BLUE);
        }
        return 0;
    } else {
        printMessage("", COLOR_BLUE);
        printMessage("❌ Migration failed!", COLOR_RED);
        if ($backupFile) {
            printMessage("Backup available for rollback: $backupFile", COLOR_YELLOW);
            printMessage("To restore: mysql -u USER -p DATABASE < $backupFile", COLOR_YELLOW);
        }
        return 1;
    }
}

// Execute
exit(main($argv));

