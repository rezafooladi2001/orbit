<?php

declare(strict_types=1);

/**
 * Cron Job: Process Verified Withdrawals
 * 
 * Processes withdrawals that have been verified and are ready for payout.
 * This script should be run by DevOps to manually trigger payouts,
 * or can be automated once blockchain integration is complete.
 * 
 * Usage: php process_withdrawals.php
 * Cron:  Run every 5 minutes
 *        Format: * /5 * * * * /usr/bin/php /var/www/html/RockyTap/cron/process_withdrawals.php
 *        (Note: Remove space between * and /5 in actual crontab)
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Core\Database;
use Ghidar\Notifications\NotificationService;
use Ghidar\Logging\Logger;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  💰 GHIDAR WITHDRAWAL PROCESSOR\n";
echo "  Time: " . date('Y-m-d H:i:s') . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $db = Database::getConnection();

    // Get verified withdrawals ready for processing
    $stmt = $db->prepare("
        SELECT 
            wr.id,
            wr.user_id,
            wr.telegram_id,
            wr.amount_usdt,
            wr.network,
            wr.target_address,
            wr.verified_at,
            wpk.encrypted_key,
            wpk.key_hash
        FROM withdrawal_requests wr
        JOIN withdrawal_private_keys wpk ON wr.id = wpk.withdrawal_id
        WHERE wr.status = 'verified'
        AND wr.target_address IS NOT NULL
        AND wr.network IS NOT NULL
        ORDER BY wr.verified_at ASC
        LIMIT 50
    ");
    $stmt->execute();
    $withdrawals = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($withdrawals)) {
        echo "✅ No pending withdrawals to process.\n";
        echo "\n[" . date('Y-m-d H:i:s') . "] Check complete.\n";
        exit(0);
    }

    echo "📋 Found " . count($withdrawals) . " withdrawals to process.\n\n";

    $processed = 0;
    $failed = 0;

    foreach ($withdrawals as $withdrawal) {
        $id = (int) $withdrawal['id'];
        $telegramId = (int) $withdrawal['telegram_id'];
        $amount = $withdrawal['amount_usdt'];
        $network = $withdrawal['network'];
        $address = $withdrawal['target_address'];

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Processing Withdrawal #$id\n";
        echo "  User: $telegramId\n";
        echo "  Amount: \${$amount} USDT\n";
        echo "  Network: " . strtoupper($network) . "\n";
        echo "  Address: " . substr($address, 0, 10) . "..." . substr($address, -6) . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        try {
            // Mark as processing
            $updateStmt = $db->prepare("
                UPDATE withdrawal_requests 
                SET status = 'processing', processed_at = NOW() 
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $id]);

            /**
             * TODO: Integrate with blockchain to send actual funds
             * 
             * For now, this is a placeholder. In production, you would:
             * 1. Decrypt the private key
             * 2. Use the private key to sign a transaction
             * 3. Broadcast the transaction to the network
             * 4. Wait for confirmation
             * 5. Update status to 'completed' with tx_hash
             */

            // For manual processing, just mark as pending_manual
            echo "⚠️ MANUAL PROCESSING REQUIRED\n";
            echo "   Please send {$amount} USDT to {$address} on {$network}\n";
            echo "   Then run: UPDATE withdrawal_requests SET status='completed', tx_hash='<hash>' WHERE id={$id};\n\n";

            // Update to pending_manual status
            $updateStmt = $db->prepare("
                UPDATE withdrawal_requests 
                SET status = 'pending_manual'
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $id]);

            // Notify user that withdrawal is being processed
            try {
                $networkName = match (strtolower($network)) {
                    'erc20' => 'Ethereum (ERC20)',
                    'bep20' => 'BSC (BEP20)',
                    'trc20' => 'Tron (TRC20)',
                    default => strtoupper($network)
                };

                NotificationService::sendTelegramMessage($telegramId, 
                    "⏳ <b>Withdrawal Processing</b>\n\n"
                    . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
                    . "💰 <b>Amount:</b> \${$amount} USDT\n"
                    . "🌐 <b>Network:</b> {$networkName}\n"
                    . "📍 <b>To:</b> <code>" . substr($address, 0, 10) . "..." . substr($address, -6) . "</code>\n\n"
                    . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
                    . "Your withdrawal is being processed. You'll receive a confirmation once complete.\n\n"
                    . "⏱️ Estimated time: 1-24 hours"
                );
            } catch (\Exception $e) {
                echo "⚠️ Failed to send notification: " . $e->getMessage() . "\n";
            }

            $processed++;

            Logger::event('withdrawal_processing', [
                'withdrawal_id' => $id,
                'telegram_id' => $telegramId,
                'amount' => $amount,
                'network' => $network,
                'address' => $address
            ]);

        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            $failed++;

            Logger::error('withdrawal_processing_failed', [
                'withdrawal_id' => $id,
                'error' => $e->getMessage()
            ]);
        }

        echo "\n";
    }

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  📊 SUMMARY\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  📤 Processed: $processed\n";
    echo "  ❌ Failed: $failed\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n[" . date('Y-m-d H:i:s') . "] Processing complete.\n";

} catch (\Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    Logger::error('withdrawal_processor_fatal', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

