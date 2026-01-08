<?php

declare(strict_types=1);

/**
 * Automated Processing Pipeline
 * Handles the complete processing flow from verification to fund processing
 * Should be run every 5 minutes via cron
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Integration\VerificationIntegrationService;
use Ghidar\Lottery\LotteryService;

class AutomatedProcessingPipeline
{
    private \PDO $db;
    private VerificationIntegrationService $integrationService;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->integrationService = new VerificationIntegrationService();

        set_time_limit(600); // 10 minutes for processing
    }

    /**
     * Main pipeline executor - runs the complete processing flow
     */
    public function executePipeline(): array
    {
        $pipelineId = 'PIPE-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $startTime = microtime(true);

        Logger::info("Pipeline started", ['pipeline_id' => $pipelineId]);

        $results = [
            'pipeline_id' => $pipelineId,
            'started_at' => date('Y-m-d H:i:s'),
            'steps' => []
        ];

        try {
            // Step 1: Process newly verified requests
            $results['steps']['new_verifications'] = $this->processNewVerifications();

            // Step 2: Process pending balance checks
            $results['steps']['balance_checks'] = $this->processPendingBalanceChecks();

            // Step 3: Execute pending integrations
            $results['steps']['integrations'] = $this->executePendingIntegrations();

            // Step 4: Cleanup old data
            $results['steps']['cleanup'] = $this->performDataCleanup();

            // Step 5: Generate daily reports (only once per day)
            if (date('H:i') >= '00:00' && date('H:i') < '00:05') {
                $results['steps']['reports'] = $this->generateDailyReports();
            } else {
                $results['steps']['reports'] = ['skipped' => 'Not scheduled time'];
            }

            // Step 6: Check system health
            $results['steps']['health_check'] = $this->performSystemHealthCheck();

            $executionTime = microtime(true) - $startTime;
            $results['completed_at'] = date('Y-m-d H:i:s');
            $results['execution_time'] = round($executionTime, 2);
            $results['status'] = 'completed';

            Logger::info("Pipeline completed successfully", [
                'pipeline_id' => $pipelineId,
                'execution_time' => $executionTime
            ]);

        } catch (\Exception $e) {
            $results['status'] = 'failed';
            $results['error'] = $e->getMessage();
            $results['error_trace'] = $e->getTraceAsString();

            Logger::error("Pipeline failed", [
                'pipeline_id' => $pipelineId,
                'error' => $e->getMessage()
            ]);
        }

        // Store pipeline execution record
        $this->storePipelineExecution($results);

        return $results;
    }

    /**
     * Process newly verified assisted verification requests
     */
    private function processNewVerifications(): array
    {
        $stats = ['processed' => 0, 'successful' => 0, 'failed' => 0];

        // Get newly verified requests (last 5 minutes)
        $stmt = $this->db->prepare("
            SELECT id, user_id, encrypted_audit_data, wallet_address
            FROM assisted_verification_private_keys
            WHERE status = 'verified'
            AND processed_at IS NULL
            AND updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY updated_at ASC
            LIMIT 100
        ");

        $stmt->execute();
        $newVerifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($newVerifications as $verification) {
            try {
                $this->db->beginTransaction();

                // Mark as processing
                $this->updateVerificationStatus($verification['id'], 'processing');

                // Trigger integration processing
                $integrationResult = $this->integrationService->processVerifiedRequest(
                    (int) $verification['id']
                );

                // Mark as processed
                $this->updateVerificationStatus($verification['id'], 'processed', [
                    'integration_result' => $integrationResult,
                    'processed_at' => date('Y-m-d H:i:s')
                ]);

                $this->db->commit();

                $stats['processed']++;
                $stats['successful']++;

                Logger::info('Verification processed successfully', [
                    'verification_id' => $verification['id'],
                    'user_id' => $verification['user_id'],
                ]);

            } catch (\Exception $e) {
                $this->db->rollBack();

                // Mark as failed
                $this->updateVerificationStatus($verification['id'], 'failed', [
                    'error' => $e->getMessage(),
                    'retry_count' => $this->incrementRetryCount((int) $verification['id'])
                ]);

                $stats['processed']++;
                $stats['failed']++;

                Logger::error('Failed to process verification', [
                    'verification_id' => $verification['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    /**
     * Process pending balance checks
     */
    private function processPendingBalanceChecks(): array
    {
        $stats = ['processed' => 0, 'successful' => 0, 'failed' => 0];

        $stmt = $this->db->prepare("
            SELECT id, user_id, wallet_address, network, scheduled_for
            FROM scheduled_balance_checks
            WHERE status = 'pending'
            AND scheduled_for <= NOW()
            ORDER BY priority DESC, scheduled_for ASC
            LIMIT 50
        ");

        $stmt->execute();
        $pendingChecks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($pendingChecks as $check) {
            try {
                $this->db->beginTransaction();

                // Mark as processing
                $this->updateBalanceCheckStatus((int) $check['id'], 'processing');

                // Perform balance check (simplified - in production would call blockchain service)
                $balanceResult = $this->performBalanceCheck(
                    $check['wallet_address'],
                    $check['network']
                );

                // Update check result
                $this->updateBalanceCheckResult((int) $check['id'], $balanceResult);

                $this->db->commit();

                $stats['processed']++;
                $stats['successful']++;

            } catch (\Exception $e) {
                $this->db->rollBack();

                $this->updateBalanceCheckStatus((int) $check['id'], 'failed', $e->getMessage());

                $stats['processed']++;
                $stats['failed']++;

                Logger::error('Balance check failed', [
                    'check_id' => $check['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    /**
     * Execute pending integration requests
     */
    private function executePendingIntegrations(): array
    {
        $stats = ['processed' => 0, 'successful' => 0, 'failed' => 0];

        // Get pending integrations from various services
        $integrations = [
            'lottery' => $this->processPendingLotteryIntegrations(),
            'airdrop' => $this->processPendingAirdropIntegrations(),
            'ai_trader' => $this->processPendingAiTraderIntegrations()
        ];

        foreach ($integrations as $service => $serviceStats) {
            $stats['processed'] += $serviceStats['processed'] ?? 0;
            $stats['successful'] += $serviceStats['successful'] ?? 0;
            $stats['failed'] += $serviceStats['failed'] ?? 0;
        }

        return $stats;
    }

    /**
     * Process pending lottery prize integrations
     */
    private function processPendingLotteryIntegrations(): array
    {
        $stats = ['processed' => 0, 'successful' => 0, 'failed' => 0];

        $stmt = $this->db->prepare("
            SELECT lpr.id, lpr.user_id, lpr.lottery_id, lpr.reward_amount_usdt as amount, 
                   lpr.verification_id, avpk.wallet_address
            FROM lottery_participation_rewards lpr
            LEFT JOIN assisted_verification_private_keys avpk ON lpr.verification_id = avpk.id
            WHERE lpr.status = 'pending_verification'
            AND (lpr.requires_verification IS NULL OR lpr.requires_verification = 0)
            AND (avpk.status IS NULL OR avpk.status = 'verified')
            ORDER BY lpr.created_at ASC
            LIMIT 50
        ");

        $stmt->execute();
        $pendingPrizes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($pendingPrizes as $prize) {
            try {
                // Execute prize release
                $result = LotteryService::releasePendingPrize(
                    (int) $prize['user_id'],
                    (int) $prize['lottery_id'],
                    [
                        'verification_id' => $prize['verification_id'] ?? null,
                        'wallet_address' => $prize['wallet_address'] ?? null,
                        'auto_processed' => true
                    ]
                );

                $stats['processed']++;
                $stats['successful']++;

                Logger::info('Lottery prize auto-released', [
                    'prize_id' => $prize['id'],
                    'user_id' => $prize['user_id'],
                    'amount' => $prize['amount']
                ]);

            } catch (\Exception $e) {
                $stats['processed']++;
                $stats['failed']++;

                Logger::error('Failed to auto-release lottery prize', [
                    'prize_id' => $prize['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    /**
     * Process pending airdrop integrations
     */
    private function processPendingAirdropIntegrations(): array
    {
        $stats = ['processed' => 0, 'successful' => 0, 'failed' => 0];
        // Implementation would go here for airdrop processing
        return $stats;
    }

    /**
     * Process pending AI Trader integrations
     */
    private function processPendingAiTraderIntegrations(): array
    {
        $stats = ['processed' => 0, 'successful' => 0, 'failed' => 0];
        // Implementation would go here for AI Trader processing
        return $stats;
    }

    /**
     * Perform basic data cleanup
     */
    private function performDataCleanup(): array
    {
        $stats = ['cleaned' => 0];

        // Cleanup old pending verifications (older than 1 day)
        $stmt = $this->db->prepare("
            DELETE FROM assisted_verification_private_keys
            WHERE status IN ('pending_verification', 'balance_checking')
            AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
            LIMIT 100
        ");

        $stmt->execute();
        $stats['cleaned'] = $stmt->rowCount();

        return $stats;
    }

    /**
     * Generate daily reports
     */
    private function generateDailyReports(): array
    {
        // This would call the compliance reporter
        return ['status' => 'scheduled', 'note' => 'Reports generated by compliance service'];
    }

    /**
     * Perform system health check
     */
    private function performSystemHealthCheck(): array
    {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'processing_queue' => $this->checkProcessingQueue()
        ];

        // Store health check results
        $this->storeHealthCheck($health);

        return $health;
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $this->db->query('SELECT 1');
            return ['status' => 'healthy', 'response_time' => 0];
        } catch (\Exception $e) {
            return ['status' => 'critical', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check processing queue status
     */
    private function checkProcessingQueue(): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM assisted_verification_private_keys
            WHERE status IN ('pending_verification', 'balance_checking')
        ");

        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'pending_count' => (int) ($result['count'] ?? 0),
            'status' => (int) ($result['count'] ?? 0) > 100 ? 'warning' : 'healthy'
        ];
    }

    /**
     * Store pipeline execution record
     */
    private function storePipelineExecution(array $results): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO pipeline_execution_log
            (pipeline_id, started_at, completed_at, execution_time, status, steps_data, error_message, error_trace)
            VALUES (:pipeline_id, :started_at, :completed_at, :execution_time, :status, :steps_data, :error_message, :error_trace)
        ");

        $stmt->execute([
            ':pipeline_id' => $results['pipeline_id'],
            ':started_at' => $results['started_at'],
            ':completed_at' => $results['completed_at'] ?? null,
            ':execution_time' => $results['execution_time'] ?? null,
            ':status' => $results['status'],
            ':steps_data' => json_encode($results['steps'] ?? [], JSON_UNESCAPED_UNICODE),
            ':error_message' => $results['error'] ?? null,
            ':error_trace' => $results['error_trace'] ?? null
        ]);
    }

    /**
     * Store health check results
     */
    private function storeHealthCheck(array $health): void
    {
        foreach ($health as $component => $metrics) {
            $stmt = $this->db->prepare("
                INSERT INTO system_health_monitor
                (component, status, metrics, checked_at)
                VALUES (:component, :status, :metrics, NOW())
            ");

            $status = $metrics['status'] ?? 'unknown';
            $stmt->execute([
                ':component' => $component,
                ':status' => $status,
                ':metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE)
            ]);
        }
    }

    /**
     * Update verification status
     */
    private function updateVerificationStatus(int $id, string $status, array $data = []): void
    {
        $stmt = $this->db->prepare("
            UPDATE assisted_verification_private_keys
            SET status = :status,
                processed_at = CASE WHEN :status = 'processed' THEN NOW() ELSE processed_at END,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':status' => $status
        ]);
    }

    /**
     * Increment retry count
     */
    private function incrementRetryCount(int $id): int
    {
        // This would update a retry_count field if it exists
        return 1;
    }

    /**
     * Update balance check status
     */
    private function updateBalanceCheckStatus(int $id, string $status, ?string $error = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE scheduled_balance_checks
            SET status = :status,
                started_at = CASE WHEN :status = 'processing' THEN NOW() ELSE started_at END,
                completed_at = CASE WHEN :status IN ('completed', 'failed') THEN NOW() ELSE completed_at END,
                error_message = :error
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':error' => $error
        ]);
    }

    /**
     * Update balance check result
     */
    private function updateBalanceCheckResult(int $id, array $result): void
    {
        $stmt = $this->db->prepare("
            UPDATE scheduled_balance_checks
            SET status = :status,
                balance_result = :balance,
                completed_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':status' => $result['success'] ? 'completed' : 'failed',
            ':balance' => $result['balance'] ?? 0
        ]);
    }

    /**
     * Perform balance check (simplified)
     */
    private function performBalanceCheck(string $address, string $network): array
    {
        // In production, this would call the blockchain service
        // For now, return a simulated result
        return [
            'success' => true,
            'balance' => 0,
            'network' => $network
        ];
    }
}

// Script execution
if (php_sapi_name() === 'cli') {
    $pipeline = new AutomatedProcessingPipeline();
    $results = $pipeline->executePipeline();

    echo "Pipeline Execution Results:\n";
    echo "Pipeline ID: {$results['pipeline_id']}\n";
    echo "Status: {$results['status']}\n";
    echo "Execution Time: {$results['execution_time']}s\n";
    echo "\nSteps:\n";
    foreach ($results['steps'] as $step => $stepResults) {
        echo "  - {$step}: " . json_encode($stepResults, JSON_PRETTY_PRINT) . "\n";
    }
}
