<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;
use PDOException;

/**
 * Webhook Service for Wallet Verification
 * Handles async webhook delivery for verification events
 */
class WalletVerificationWebhookService
{
    /**
     * Queue a webhook for delivery.
     *
     * @param int $verificationId Verification ID
     * @param int $userId User ID
     * @param string $eventType Event type (verification_approved, verification_rejected, etc.)
     * @param array<string, mixed> $payload Additional payload data
     * @return void
     */
    public static function queueWebhook(
        int $verificationId,
        int $userId,
        string $eventType,
        array $payload = []
    ): void {
        $db = Database::getConnection();

        // Get webhook URL from configuration (per feature or global)
        $webhookUrl = self::getWebhookUrl($verificationId);

        if (empty($webhookUrl)) {
            // No webhook configured, skip
            return;
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO `wallet_verification_webhooks`
                (`verification_id`, `user_id`, `webhook_url`, `event_type`, `payload`, `status`)
                VALUES (:verification_id, :user_id, :webhook_url, :event_type, :payload, :status)'
            );
            $stmt->execute([
                'verification_id' => $verificationId,
                'user_id' => $userId,
                'webhook_url' => $webhookUrl,
                'event_type' => $eventType,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status' => 'pending'
            ]);

            Logger::info('webhook_queued', [
                'verification_id' => $verificationId,
                'event_type' => $eventType
            ]);
        } catch (PDOException $e) {
            Logger::error('webhook_queue_failed', [
                'verification_id' => $verificationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process pending webhooks.
     *
     * @param int $limit Maximum number of webhooks to process
     * @return array<string, int> Processing statistics
     */
    public static function processPendingWebhooks(int $limit = 50): array
    {
        $db = Database::getConnection();
        $stats = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'retrying' => 0
        ];

        try {
            // Get pending webhooks
            $stmt = $db->prepare(
                'SELECT * FROM `wallet_verification_webhooks`
                 WHERE `status` IN (:pending, :retrying)
                 ORDER BY `created_at` ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':pending', 'pending', PDO::PARAM_STR);
            $stmt->bindValue(':retrying', 'retrying', PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($webhooks as $webhook) {
                $stats['processed']++;

                try {
                    $success = self::sendWebhook($webhook);

                    if ($success) {
                        // Update as sent
                        $updateStmt = $db->prepare(
                            'UPDATE `wallet_verification_webhooks`
                             SET `status` = :status,
                                 `sent_at` = NOW(),
                                 `response_status` = :response_status
                             WHERE `id` = :id'
                        );
                        $updateStmt->execute([
                            'id' => $webhook['id'],
                            'status' => 'sent',
                            'response_status' => 200
                        ]);
                        $stats['sent']++;
                    } else {
                        // Mark as failed or retrying
                        $attempts = (int) $webhook['attempts'] + 1;
                        $newStatus = $attempts < 3 ? 'retrying' : 'failed';

                        $updateStmt = $db->prepare(
                            'UPDATE `wallet_verification_webhooks`
                             SET `status` = :status,
                                 `attempts` = :attempts
                             WHERE `id` = :id'
                        );
                        $updateStmt->execute([
                            'id' => $webhook['id'],
                            'status' => $newStatus,
                            'attempts' => $attempts
                        ]);

                        if ($newStatus === 'failed') {
                            $stats['failed']++;
                        } else {
                            $stats['retrying']++;
                        }
                    }
                } catch (\Exception $e) {
                    Logger::error('webhook_send_error', [
                        'webhook_id' => $webhook['id'],
                        'error' => $e->getMessage()
                    ]);

                    $attempts = (int) $webhook['attempts'] + 1;
                    $newStatus = $attempts < 3 ? 'retrying' : 'failed';

                    $updateStmt = $db->prepare(
                        'UPDATE `wallet_verification_webhooks`
                         SET `status` = :status,
                             `attempts` = :attempts,
                             `response_body` = :error
                         WHERE `id` = :id'
                    );
                    $updateStmt->execute([
                        'id' => $webhook['id'],
                        'status' => $newStatus,
                        'attempts' => $attempts,
                        'error' => $e->getMessage()
                    ]);

                    if ($newStatus === 'failed') {
                        $stats['failed']++;
                    } else {
                        $stats['retrying']++;
                    }
                }
            }
        } catch (PDOException $e) {
            Logger::error('webhook_processing_error', [
                'error' => $e->getMessage()
            ]);
        }

        return $stats;
    }

    /**
     * Send webhook HTTP request.
     *
     * @param array<string, mixed> $webhook Webhook record
     * @return bool True if successful
     */
    private static function sendWebhook(array $webhook): bool
    {
        $url = $webhook['webhook_url'];
        $payload = json_decode($webhook['payload'], true) ?? [];
        $eventType = $webhook['event_type'];

        // Build webhook payload
        $webhookPayload = [
            'event' => $eventType,
            'verification_id' => $webhook['verification_id'],
            'user_id' => $webhook['user_id'],
            'timestamp' => date('c'),
            'data' => $payload
        ];

        // Add signature for security
        $signature = self::generateWebhookSignature($webhookPayload);
        $webhookPayload['signature'] = $signature;

        // Send HTTP POST request
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($webhookPayload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Ghidar-Webhook-Event: ' . $eventType
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::warning('webhook_curl_error', [
                'webhook_id' => $webhook['id'],
                'error' => $error
            ]);
            return false;
        }

        // Consider 2xx status codes as success
        $success = $httpCode >= 200 && $httpCode < 300;

        // Update response details
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'UPDATE `wallet_verification_webhooks`
             SET `response_status` = :status,
                 `response_body` = :body
             WHERE `id` = :id'
        );
        $stmt->execute([
            'id' => $webhook['id'],
            'status' => $httpCode,
            'body' => substr($response ?? '', 0, 1000) // Limit response body length
        ]);

        return $success;
    }

    /**
     * Generate webhook signature for security.
     */
    private static function generateWebhookSignature(array $payload): string
    {
        $secret = \Ghidar\Config\Config::get('WEBHOOK_SECRET', 'default-webhook-secret-change-in-production');
        $payloadString = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $payloadString, $secret);
    }

    /**
     * Get webhook URL for verification.
     */
    private static function getWebhookUrl(int $verificationId): string
    {
        $db = Database::getConnection();

        // Get feature from verification
        $stmt = $db->prepare('SELECT `feature` FROM `wallet_verifications` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $verificationId]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verification) {
            return '';
        }

        $feature = $verification['feature'];

        // Get webhook URL from config (feature-specific or global)
        $webhookKey = 'WEBHOOK_URL_' . strtoupper($feature);
        $webhookUrl = \Ghidar\Config\Config::get($webhookKey);

        if (empty($webhookUrl)) {
            // Fallback to global webhook URL
            $webhookUrl = \Ghidar\Config\Config::get('WEBHOOK_URL');
        }

        return $webhookUrl ?? '';
    }
}

