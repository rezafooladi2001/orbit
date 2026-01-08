<?php

declare(strict_types=1);

namespace Ghidar\Payments;

use Ghidar\AITrader\AiTraderConfig;
use Ghidar\AITrader\AiTraderService;
use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Lottery\LotteryService;
use Ghidar\Logging\Logger;
use Ghidar\Notifications\NotificationService;
use Ghidar\Referral\ReferralService;
use PDO;
use PDOException;

/**
 * Service for managing deposit operations.
 * Handles deposit initialization and confirmation processing for different product types.
 */
class DepositService
{
    /**
     * Initialize a deposit for a user.
     * Validates inputs, creates deposit record, and gets/creates deposit address.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param string $network Network identifier ('erc20', 'bep20', 'trc20')
     * @param string $productType Product type ('wallet_topup', 'lottery_tickets', 'ai_trader')
     * @param array<string, mixed> $payload Product-specific payload data
     * @return array<string, mixed> Deposit record with address and metadata
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If product-specific validation fails
     */
    public static function initDeposit(
        int $userId,
        string $network,
        string $productType,
        array $payload
    ): array {
        // Validate network
        if (!in_array($network, PaymentsConfig::SUPPORTED_NETWORKS, true)) {
            throw new \InvalidArgumentException('Invalid network: ' . $network);
        }

        // Validate product type
        if (!in_array($productType, PaymentsConfig::SUPPORTED_PRODUCT_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid product type: ' . $productType);
        }

        $db = Database::ensureConnection();
        $expectedAmountUsdt = null;
        $meta = null;

        // Process based on product type
        switch ($productType) {
            case PaymentsConfig::PRODUCT_WALLET_TOPUP:
                // Validate amount_usdt
                if (!isset($payload['amount_usdt']) || !is_numeric($payload['amount_usdt'])) {
                    throw new \InvalidArgumentException('amount_usdt is required and must be numeric');
                }

                $amountUsdt = (string) $payload['amount_usdt'];
                $amountUsdt = number_format((float) $amountUsdt, 8, '.', '');

                // Validate amount limits
                if (bccomp($amountUsdt, PaymentsConfig::MIN_DEPOSIT_USDT, 8) < 0) {
                    throw new \InvalidArgumentException(
                        'Amount must be at least ' . PaymentsConfig::MIN_DEPOSIT_USDT . ' USDT'
                    );
                }

                if (bccomp($amountUsdt, PaymentsConfig::MAX_DEPOSIT_USDT, 8) > 0) {
                    throw new \InvalidArgumentException(
                        'Amount exceeds maximum: ' . PaymentsConfig::MAX_DEPOSIT_USDT . ' USDT'
                    );
                }

                $expectedAmountUsdt = $amountUsdt;
                break;

            case PaymentsConfig::PRODUCT_LOTTERY_TICKETS:
                // Validate lottery_id and ticket_count
                if (!isset($payload['lottery_id']) || !is_numeric($payload['lottery_id'])) {
                    throw new \InvalidArgumentException('lottery_id is required and must be numeric');
                }

                if (!isset($payload['ticket_count']) || !is_numeric($payload['ticket_count'])) {
                    throw new \InvalidArgumentException('ticket_count is required and must be numeric');
                }

                $lotteryId = (int) $payload['lottery_id'];
                $ticketCount = (int) $payload['ticket_count'];

                if ($ticketCount <= 0) {
                    throw new \InvalidArgumentException('ticket_count must be greater than 0');
                }

                // Get lottery and validate
                $lottery = LotteryService::getActiveLottery();
                if ($lottery === null) {
                    throw new \RuntimeException('No active lottery found');
                }

                if ((int) $lottery['id'] !== $lotteryId) {
                    throw new \RuntimeException('Lottery ID does not match active lottery');
                }

                if ($lottery['status'] !== 'active') {
                    throw new \RuntimeException('Lottery is not active');
                }

                // Calculate expected amount
                $ticketPriceUsdt = (string) $lottery['ticket_price_usdt'];
                $expectedAmountUsdt = bcmul($ticketPriceUsdt, (string) $ticketCount, 8);

                $meta = json_encode([
                    'lottery_id' => $lotteryId,
                    'ticket_count' => $ticketCount
                ], JSON_UNESCAPED_UNICODE);
                break;

            case PaymentsConfig::PRODUCT_AI_TRADER:
                // Validate amount_usdt
                if (!isset($payload['amount_usdt']) || !is_numeric($payload['amount_usdt'])) {
                    throw new \InvalidArgumentException('amount_usdt is required and must be numeric');
                }

                $amountUsdt = (string) $payload['amount_usdt'];
                $amountUsdt = number_format((float) $amountUsdt, 8, '.', '');

                // Validate amount limits using AiTraderConfig
                if (bccomp($amountUsdt, AiTraderConfig::MIN_DEPOSIT_USDT, 8) < 0) {
                    throw new \InvalidArgumentException(
                        'Amount must be at least ' . AiTraderConfig::MIN_DEPOSIT_USDT . ' USDT'
                    );
                }

                if (bccomp($amountUsdt, AiTraderConfig::MAX_DEPOSIT_USDT, 8) > 0) {
                    throw new \InvalidArgumentException(
                        'Amount exceeds maximum: ' . AiTraderConfig::MAX_DEPOSIT_USDT . ' USDT'
                    );
                }

                $expectedAmountUsdt = $amountUsdt;
                break;

            default:
                throw new \InvalidArgumentException('Unsupported product type: ' . $productType);
        }

        // Get or create deposit address
        $purpose = PaymentsConfig::productTypeToPurpose($productType);
        $address = BlockchainAddressService::getOrCreateAddress($userId, $network, $purpose);

        // Insert deposit record
        $stmt = $db->prepare(
            'INSERT INTO `deposits` 
             (`user_id`, `network`, `product_type`, `status`, `address`, `expected_amount_usdt`, `meta`) 
             VALUES (:user_id, :network, :product_type, :status, :address, :expected_amount_usdt, :meta)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'network' => $network,
            'product_type' => $productType,
            'status' => PaymentsConfig::DEPOSIT_STATUS_PENDING,
            'address' => $address,
            'expected_amount_usdt' => $expectedAmountUsdt,
            'meta' => $meta
        ]);

        $depositId = (int) $db->lastInsertId();

        // Fetch the created deposit
        $stmt = $db->prepare('SELECT * FROM `deposits` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $depositId]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($deposit === false) {
            throw new PDOException('Failed to retrieve created deposit');
        }

        // Parse meta for response
        $responseMeta = null;
        if ($meta !== null) {
            $responseMeta = json_decode($meta, true);
        }

        return [
            'deposit_id' => $depositId,
            'network' => $network,
            'product_type' => $productType,
            'address' => $address,
            'expected_amount_usdt' => $expectedAmountUsdt !== null ? $expectedAmountUsdt : null,
            'meta' => $responseMeta
        ];
    }

    /**
     * Handle confirmed deposit callback from blockchain-service.
     * Updates deposit status, credits wallet, and processes product-specific actions.
     *
     * @param int $depositId Deposit ID
     * @param string $network Network identifier
     * @param string $txHash Transaction hash
     * @param string $amountUsdt Actual amount received in USDT
     * @return array<string, mixed> Updated deposit, wallet, and product-specific data
     * @throws PDOException If database operation fails
     * @throws \RuntimeException If validation fails or deposit cannot be processed
     */
    public static function handleConfirmedDeposit(
        int $depositId,
        string $network,
        string $txHash,
        string $amountUsdt
    ): array {
        // Validate inputs
        if (empty($txHash)) {
            throw new \RuntimeException('Transaction hash is required');
        }

        if (!is_numeric($amountUsdt) || bccomp($amountUsdt, '0', 8) <= 0) {
            throw new \RuntimeException('Amount must be a positive number');
        }

        $amountUsdt = number_format((float) $amountUsdt, 8, '.', '');

        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Load deposit
            $stmt = $db->prepare('SELECT * FROM `deposits` WHERE `id` = :id LIMIT 1');
            $stmt->execute(['id' => $depositId]);
            $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($deposit === false) {
                throw new \RuntimeException('Deposit not found');
            }

            // Validate deposit status - prevent double processing
            if ($deposit['status'] !== PaymentsConfig::DEPOSIT_STATUS_PENDING) {
                $db->rollBack();
                Logger::warning('deposit_double_process_or_invalid', [
                    'deposit_id' => $depositId,
                    'user_id' => (int) $deposit['user_id'],
                    'status' => $deposit['status'] ?? null,
                ]);
                throw new \RuntimeException(
                    'Deposit already processed (status: ' . $deposit['status'] . ')'
                );
            }

            // Validate network match
            if ($deposit['network'] !== $network) {
                $db->rollBack();
                throw new \RuntimeException('Network mismatch');
            }

            // Validate amount if expected_amount_usdt is set
            if ($deposit['expected_amount_usdt'] !== null) {
                $expectedAmount = (string) $deposit['expected_amount_usdt'];
                if (bccomp($amountUsdt, $expectedAmount, 8) < 0) {
                    throw new \RuntimeException(
                        'Received amount (' . $amountUsdt . ') is less than expected (' . $expectedAmount . ')'
                    );
                }
            }

            $userId = (int) $deposit['user_id'];
            $productType = $deposit['product_type'];

            // Update deposit status
            $stmt = $db->prepare(
                'UPDATE `deposits` 
                 SET `status` = :status, 
                     `actual_amount_usdt` = :actual_amount_usdt,
                     `tx_hash` = :tx_hash,
                     `confirmed_at` = NOW()
                 WHERE `id` = :id'
            );
            $stmt->execute([
                'status' => PaymentsConfig::DEPOSIT_STATUS_CONFIRMED,
                'actual_amount_usdt' => $amountUsdt,
                'tx_hash' => $txHash,
                'id' => $depositId
            ]);

            // Credit user's wallet
            $wallet = WalletRepository::getOrCreateByUserId($userId);
            $stmt = $db->prepare(
                'UPDATE `wallets` 
                 SET `usdt_balance` = `usdt_balance` + :amount 
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'amount' => $amountUsdt,
                'user_id' => $userId
            ]);

            // Get updated wallet
            $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedWallet === false) {
                throw new PDOException('Failed to retrieve updated wallet');
            }

            $result = [
                'deposit' => null,
                'wallet' => $updatedWallet,
                'product_action' => null
            ];

            // Product-specific follow-up logic
            switch ($productType) {
                case PaymentsConfig::PRODUCT_WALLET_TOPUP:
                    // No further action needed - wallet already credited
                    break;

                case PaymentsConfig::PRODUCT_LOTTERY_TICKETS:
                    // Parse meta to get lottery_id and ticket_count
                    $meta = $deposit['meta'];
                    if ($meta === null) {
                        throw new \RuntimeException('Lottery deposit missing meta data');
                    }

                    $metaData = json_decode($meta, true);
                    if (!is_array($metaData) || !isset($metaData['lottery_id']) || !isset($metaData['ticket_count'])) {
                        throw new \RuntimeException('Invalid lottery deposit meta data');
                    }

                    $lotteryId = (int) $metaData['lottery_id'];
                    $ticketCount = (int) $metaData['ticket_count'];

                    // Calculate required cost
                    $lottery = LotteryService::getActiveLottery();
                    if ($lottery === null || (int) $lottery['id'] !== $lotteryId) {
                        throw new \RuntimeException('Lottery not found or no longer active');
                    }

                    $ticketPriceUsdt = (string) $lottery['ticket_price_usdt'];
                    $requiredCost = bcmul($ticketPriceUsdt, (string) $ticketCount, 8);

                    // Check if wallet has enough balance (should have, but double-check)
                    $currentBalance = (string) $updatedWallet['usdt_balance'];
                    if (bccomp($currentBalance, $requiredCost, 8) < 0) {
                        // Roll back transaction - underpaid deposit
                        $db->rollBack();
                        throw new \RuntimeException(
                            'Insufficient balance for ticket purchase. Required: ' . $requiredCost . 
                            ', Available: ' . $currentBalance
                        );
                    }

                    // Purchase tickets (this will deduct from wallet)
                    $ticketResult = LotteryService::purchaseTicketsFromBalance($userId, $ticketCount);
                    $result['product_action'] = [
                        'type' => 'lottery_tickets_purchased',
                        'ticket_count' => $ticketCount,
                        'lottery_id' => $lotteryId
                    ];
                    break;

                case PaymentsConfig::PRODUCT_AI_TRADER:
                    // Move funds from wallet to AI Trader account
                    AiTraderService::depositFromWallet($userId, $amountUsdt);
                    $result['product_action'] = [
                        'type' => 'ai_trader_deposited',
                        'amount_usdt' => $amountUsdt
                    ];
                    break;
            }

            // Get updated deposit
            $stmt = $db->prepare('SELECT * FROM `deposits` WHERE `id` = :id LIMIT 1');
            $stmt->execute(['id' => $depositId]);
            $updatedDeposit = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedDeposit === false) {
                throw new PDOException('Failed to retrieve updated deposit');
            }

            $result['deposit'] = $updatedDeposit;

            $db->commit();

            // Log successful deposit confirmation
            Logger::event('deposit_confirmed', [
                'deposit_id' => $depositId,
                'user_id' => $userId,
                'product_type' => $productType,
                'amount_usdt' => $amountUsdt,
                'network' => $network,
                'tx_hash' => $txHash,
            ]);

            // Register referral revenue (after commit, in separate transaction for safety)
            try {
                // Map product_type to referral source_type
                $sourceType = match ($productType) {
                    PaymentsConfig::PRODUCT_WALLET_TOPUP => 'wallet_deposit',
                    PaymentsConfig::PRODUCT_AI_TRADER => 'ai_trader_deposit',
                    PaymentsConfig::PRODUCT_LOTTERY_TICKETS => 'lottery_purchase',
                    default => null
                };

                if ($sourceType !== null) {
                    ReferralService::registerRevenue(
                        $userId,
                        $sourceType,
                        $amountUsdt,
                        $depositId
                    );
                }
            } catch (\Throwable $e) {
                // Log error but don't break the main flow
                Logger::error('referral_revenue_registration_failed', [
                    'deposit_id' => $depositId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                error_log("ReferralService: Failed to register revenue for deposit {$depositId}: " . $e->getMessage());
            }

            // Send notification to user (after commit to ensure data is persisted)
            // IMPORTANT: We need to get the user's Telegram ID, not the internal user_id
            $userStmt = $db->prepare('SELECT telegram_id FROM users WHERE id = :id LIMIT 1');
            $userStmt->execute(['id' => $userId]);
            $userRecord = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userRecord !== false && isset($userRecord['telegram_id'])) {
                $telegramId = (int) $userRecord['telegram_id'];
                $notificationMeta = $deposit['meta'] !== null ? json_decode($deposit['meta'], true) : [];
                NotificationService::notifyDepositConfirmed(
                    $telegramId,  // Use Telegram ID, NOT internal user_id!
                    $network,
                    $amountUsdt,
                    $productType,
                    is_array($notificationMeta) ? $notificationMeta : []
                );

                // Check if this is the user's first deposit - send milestone notification
                try {
                    $firstDepositCheck = $db->prepare(
                        'SELECT COUNT(*) as deposit_count 
                         FROM deposits 
                         WHERE user_id = :user_id AND status = :status'
                    );
                    $firstDepositCheck->execute([
                        'user_id' => $userId,
                        'status' => PaymentsConfig::DEPOSIT_STATUS_CONFIRMED
                    ]);
                    $depositCountResult = $firstDepositCheck->fetch(PDO::FETCH_ASSOC);
                    $depositCount = (int) ($depositCountResult['deposit_count'] ?? 0);

                    if ($depositCount === 1) {
                        // This is their first deposit - send milestone notification
                        NotificationService::notifyMilestoneAchieved(
                            $telegramId,
                            'first_deposit',
                            ['amount' => $amountUsdt]
                        );
                    }
                } catch (\Throwable $e) {
                    // Don't fail if milestone check fails
                    Logger::warning('first_deposit_milestone_check_failed', [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Logger::warning('deposit_notification_skipped_no_telegram_id', [
                    'user_id' => $userId,
                    'deposit_id' => $depositId
                ]);
            }

            return $result;

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Try to get userId from deposit if available
            $errorUserId = null;
            try {
                $stmt = $db->prepare('SELECT `user_id` FROM `deposits` WHERE `id` = :id LIMIT 1');
                $stmt->execute(['id' => $depositId]);
                $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($deposit !== false) {
                    $errorUserId = (int) $deposit['user_id'];
                }
            } catch (\Throwable $ignored) {
                // Ignore errors when trying to get userId for logging
            }
            Logger::error('deposit_confirmed_failed', [
                'deposit_id' => $depositId,
                'user_id' => $errorUserId,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\RuntimeException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Try to get userId from deposit if available
            $errorUserId = null;
            try {
                $stmt = $db->prepare('SELECT `user_id` FROM `deposits` WHERE `id` = :id LIMIT 1');
                $stmt->execute(['id' => $depositId]);
                $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($deposit !== false) {
                    $errorUserId = (int) $deposit['user_id'];
                }
            } catch (\Throwable $ignored) {
                // Ignore errors when trying to get userId for logging
            }
            Logger::error('deposit_confirmed_failed', [
                'deposit_id' => $depositId,
                'user_id' => $errorUserId,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

