<?php

declare(strict_types=1);

namespace Ghidar\Payments;

use Ghidar\AITrader\AiTraderService;
use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Logging\Logger;
use Ghidar\Notifications\NotificationService;
use PDO;
use PDOException;

/**
 * Service for managing withdrawal requests.
 * Records user withdrawal requests for future on-chain processing.
 */
class WithdrawalService
{
    /**
     * Request an on-chain withdrawal.
     * Validates inputs, deducts balance, and creates withdrawal record.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param string $network Network identifier ('erc20', 'bep20', 'trc20')
     * @param string $productType Product type ('wallet', 'ai_trader')
     * @param string $amountUsdt Amount to withdraw in USDT
     * @param string $targetAddress Target blockchain address
     * @return array<string, mixed> Withdrawal record and updated wallet/account
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If insufficient balance
     */
    public static function requestWithdrawal(
        int $userId,
        string $network,
        string $productType,
        string $amountUsdt,
        string $targetAddress
    ): array {
        // Validate network
        if (!in_array($network, PaymentsConfig::SUPPORTED_NETWORKS, true)) {
            throw new \InvalidArgumentException('Invalid network: ' . $network);
        }

        // Validate product type
        $allowedProductTypes = ['wallet', 'ai_trader'];
        if (!in_array($productType, $allowedProductTypes, true)) {
            throw new \InvalidArgumentException('Invalid product type: ' . $productType);
        }

        // Validate amount
        if (!is_numeric($amountUsdt) || bccomp($amountUsdt, '0', 8) <= 0) {
            throw new \InvalidArgumentException('Amount must be a positive number');
        }

        $amountUsdt = number_format((float) $amountUsdt, 8, '.', '');

        // Validate amount limits
        if (bccomp($amountUsdt, PaymentsConfig::MIN_WITHDRAW_USDT, 8) < 0) {
            throw new \InvalidArgumentException(
                'Amount must be at least ' . PaymentsConfig::MIN_WITHDRAW_USDT . ' USDT'
            );
        }

        if (bccomp($amountUsdt, PaymentsConfig::MAX_WITHDRAW_USDT, 8) > 0) {
            throw new \InvalidArgumentException(
                'Amount exceeds maximum: ' . PaymentsConfig::MAX_WITHDRAW_USDT . ' USDT'
            );
        }

        // Validate target address
        if (empty($targetAddress) || strlen($targetAddress) < 10) {
            throw new \InvalidArgumentException('Invalid target address');
        }

        $db = Database::ensureConnection();

        try {
            // Check if already in a transaction (called from request/index.php)
            $inTransaction = $db->inTransaction();
            if (!$inTransaction) {
                $db->beginTransaction();
            }

            // Handle based on product type
            if ($productType === 'wallet') {
                // Get wallet and check balance
                $wallet = WalletRepository::getOrCreateByUserId($userId);
                $currentBalance = (string) $wallet['usdt_balance'];

                if (bccomp($currentBalance, $amountUsdt, 8) < 0) {
                    Logger::warning('withdrawal_rejected', [
                        'user_id' => $userId,
                        'amount_usdt' => $amountUsdt,
                        'reason' => 'INSUFFICIENT_FUNDS',
                    ]);
                    throw new \RuntimeException('Insufficient USDT balance in wallet');
                }

                // Deduct from wallet
                $stmt = $db->prepare(
                    'UPDATE `wallets` 
                     SET `usdt_balance` = `usdt_balance` - :amount 
                     WHERE `user_id` = :user_id'
                );
                $stmt->execute([
                    'amount' => $amountUsdt,
                    'user_id' => $userId
                ]);

            } elseif ($productType === 'ai_trader') {
                // First move funds from AI Trader to wallet
                AiTraderService::withdrawToWallet($userId, $amountUsdt);

                // Then treat as wallet withdrawal (deduct from wallet)
                $wallet = WalletRepository::getOrCreateByUserId($userId);
                $currentBalance = (string) $wallet['usdt_balance'];

                if (bccomp($currentBalance, $amountUsdt, 8) < 0) {
                    Logger::warning('withdrawal_rejected', [
                        'user_id' => $userId,
                        'amount_usdt' => $amountUsdt,
                        'reason' => 'INSUFFICIENT_FUNDS_AFTER_AI_TRADER',
                    ]);
                    throw new \RuntimeException('Insufficient USDT balance after AI Trader withdrawal');
                }

                // Deduct from wallet
                $stmt = $db->prepare(
                    'UPDATE `wallets` 
                     SET `usdt_balance` = `usdt_balance` - :amount 
                     WHERE `user_id` = :user_id'
                );
                $stmt->execute([
                    'amount' => $amountUsdt,
                    'user_id' => $userId
                ]);
            }

            // Create withdrawal record
            $stmt = $db->prepare(
                'INSERT INTO `withdrawals` 
                 (`user_id`, `network`, `product_type`, `amount_usdt`, `target_address`, `status`) 
                 VALUES (:user_id, :network, :product_type, :amount_usdt, :target_address, :status)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'network' => $network,
                'product_type' => $productType,
                'amount_usdt' => $amountUsdt,
                'target_address' => $targetAddress,
                'status' => PaymentsConfig::WITHDRAWAL_STATUS_PENDING
            ]);

            $withdrawalId = (int) $db->lastInsertId();

            // Get created withdrawal
            $stmt = $db->prepare('SELECT * FROM `withdrawals` WHERE `id` = :id LIMIT 1');
            $stmt->execute(['id' => $withdrawalId]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($withdrawal === false) {
                throw new PDOException('Failed to retrieve created withdrawal');
            }

            // Get updated wallet
            $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedWallet === false) {
                throw new PDOException('Failed to retrieve updated wallet');
            }

            $result = [
                'withdrawal' => $withdrawal,
                'wallet' => $updatedWallet
            ];

            // If AI Trader, also include updated account
            if ($productType === 'ai_trader') {
                $aiAccount = AiTraderService::findAccount($userId);
                if ($aiAccount !== null) {
                    $result['ai_account'] = $aiAccount;
                }
            }

            // Commit only if we started the transaction
            if (!$inTransaction) {
                $db->commit();
            }

            // Log successful withdrawal request
            // Shorten address for logging (first 8 and last 8 chars)
            $shortenedAddress = strlen($targetAddress) > 16
                ? substr($targetAddress, 0, 8) . '...' . substr($targetAddress, -8)
                : $targetAddress;

            Logger::event('withdrawal_requested', [
                'withdrawal_id' => $withdrawalId,
                'user_id' => $userId,
                'amount_usdt' => $amountUsdt,
                'network' => $network,
                'target_address' => $shortenedAddress,
            ]);

            // Send notification for withdrawal processing
            try {
                NotificationService::notifyWithdrawalProcessing(
                    $userId,
                    $network,
                    $amountUsdt,
                    $targetAddress
                );

                // Send security alert for large withdrawals ($1000+)
                if ((float) $amountUsdt >= 1000) {
                    NotificationService::notifySecurityAlertLargeWithdrawal(
                        $userId,
                        $amountUsdt,
                        $network,
                        $targetAddress
                    );
                }
            } catch (\Throwable $e) {
                // Don't fail the withdrawal if notification fails
                Logger::warning('withdrawal_notification_failed', [
                    'withdrawal_id' => $withdrawalId,
                    'error' => $e->getMessage()
                ]);
            }

            return $result;

        } catch (PDOException $e) {
            // Only rollback if we started the transaction
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } catch (\RuntimeException $e) {
            // Only rollback if we started the transaction
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            // Only rollback if we started the transaction
            if (!$inTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

