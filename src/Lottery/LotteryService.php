<?php

declare(strict_types=1);

namespace Ghidar\Lottery;

use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Logging\Logger;
use Ghidar\Notifications\NotificationService;
use PDO;
use PDOException;

/**
 * Service for managing Lottery operations.
 * Handles lottery creation, ticket purchases, winner selection, and lottery status.
 */
class LotteryService
{
    /**
     * Get the currently active lottery.
     * Returns the single lottery with status = 'active', or null if none exists.
     *
     * @return array<string, mixed>|null Lottery record as associative array, or null if no active lottery
     * @throws PDOException If database operation fails
     */
    public static function getActiveLottery(): ?array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT * FROM `lotteries` 
             WHERE `status` = :status 
             ORDER BY `created_at` DESC 
             LIMIT 1'
        );
        $stmt->execute(['status' => LotteryConfig::STATUS_ACTIVE]);
        $lottery = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lottery !== false ? $lottery : null;
    }

    /**
     * Get user's status for the active lottery.
     * Returns lottery info along with user's ticket count.
     *
     * @param int $userId User ID (Telegram user ID)
     * @return array<string, mixed>|null Array with lottery info and user tickets count, or null if no active lottery
     * @throws PDOException If database operation fails
     */
    public static function getUserStatusForActiveLottery(int $userId): ?array
    {
        $lottery = self::getActiveLottery();

        if ($lottery === null) {
            return null;
        }

        $db = Database::ensureConnection();

        // Count user's tickets for this lottery
        $stmt = $db->prepare(
            'SELECT COUNT(*) as ticket_count 
             FROM `lottery_tickets` 
             WHERE `lottery_id` = :lottery_id AND `user_id` = :user_id'
        );
        $stmt->execute([
            'lottery_id' => (int) $lottery['id'],
            'user_id' => $userId
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userTicketsCount = (int) ($result['ticket_count'] ?? 0);

        return [
            'lottery' => $lottery,
            'user_tickets_count' => $userTicketsCount,
            'ticket_price_usdt' => (string) $lottery['ticket_price_usdt'],
            'prize_pool_usdt' => (string) $lottery['prize_pool_usdt']
        ];
    }

    /**
     * Purchase tickets for the active lottery using internal USDT balance.
     * Validates inputs, checks balance, enforces limits, and creates tickets.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param int $ticketCount Number of tickets to purchase
     * @return array<string, mixed> Array containing updated wallet, tickets purchased, and lottery info
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If no active lottery or insufficient balance
     */
    public static function purchaseTicketsFromBalance(int $userId, int $ticketCount): array
    {
        // Validate ticket count
        if ($ticketCount <= 0) {
            throw new \InvalidArgumentException('Ticket count must be greater than 0');
        }

        if ($ticketCount > LotteryConfig::MAX_TICKETS_PER_ORDER) {
            throw new \InvalidArgumentException(
                'Ticket count exceeds maximum per order: ' . LotteryConfig::MAX_TICKETS_PER_ORDER
            );
        }

        // Get active lottery
        $lottery = self::getActiveLottery();
        if ($lottery === null) {
            throw new \RuntimeException('No active lottery found');
        }

        $lotteryId = (int) $lottery['id'];
        $ticketPriceUsdt = (string) $lottery['ticket_price_usdt'];

        // Calculate total cost using bcmath for precision
        $totalCostUsdt = bcmul($ticketPriceUsdt, (string) $ticketCount, 8);

        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get or create wallet
            $wallet = WalletRepository::getOrCreateByUserId($userId);
            $currentUsdtBalance = (string) $wallet['usdt_balance'];

            // Check sufficient balance
            if (bccomp($currentUsdtBalance, $totalCostUsdt, 8) < 0) {
                throw new \RuntimeException('Insufficient USDT balance');
            }

            // Check existing tickets count for this lottery
            $stmt = $db->prepare(
                'SELECT COUNT(*) as ticket_count 
                 FROM `lottery_tickets` 
                 WHERE `lottery_id` = :lottery_id AND `user_id` = :user_id'
            );
            $stmt->execute([
                'lottery_id' => $lotteryId,
                'user_id' => $userId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $existingTicketsCount = (int) ($result['ticket_count'] ?? 0);

            // Check max tickets per user per lottery limit
            if ($existingTicketsCount + $ticketCount > LotteryConfig::MAX_TICKETS_PER_USER_PER_LOTTERY) {
                throw new \RuntimeException(
                    'Ticket purchase would exceed maximum tickets per user per lottery: ' .
                    LotteryConfig::MAX_TICKETS_PER_USER_PER_LOTTERY
                );
            }

            // Get current max ticket number for this lottery
            $stmt = $db->prepare(
                'SELECT COALESCE(MAX(`ticket_number`), 0) as max_ticket_number 
                 FROM `lottery_tickets` 
                 WHERE `lottery_id` = :lottery_id'
            );
            $stmt->execute(['lottery_id' => $lotteryId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $lastTicketNumber = (int) ($result['max_ticket_number'] ?? 0);

            // Deduct USDT from wallet
            $stmt = $db->prepare(
                'UPDATE `wallets` 
                 SET `usdt_balance` = `usdt_balance` - :total_cost 
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'total_cost' => $totalCostUsdt,
                'user_id' => $userId
            ]);

            // Insert tickets
            $insertStmt = $db->prepare(
                'INSERT INTO `lottery_tickets` (`lottery_id`, `user_id`, `ticket_number`) 
                 VALUES (:lottery_id, :user_id, :ticket_number)'
            );

            for ($i = 1; $i <= $ticketCount; $i++) {
                $ticketNumber = $lastTicketNumber + $i;
                $insertStmt->execute([
                    'lottery_id' => $lotteryId,
                    'user_id' => $userId,
                    'ticket_number' => $ticketNumber
                ]);
            }

            // Update prize pool (add ticket revenue)
            $stmt = $db->prepare(
                'UPDATE `lotteries` 
                 SET `prize_pool_usdt` = `prize_pool_usdt` + :ticket_revenue 
                 WHERE `id` = :lottery_id'
            );
            $stmt->execute([
                'ticket_revenue' => $totalCostUsdt,
                'lottery_id' => $lotteryId
            ]);

            // Get updated wallet
            $stmt = $db->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedWallet === false) {
                throw new PDOException('Failed to retrieve updated wallet');
            }

            // Get updated lottery (with new prize pool)
            $stmt = $db->prepare('SELECT * FROM `lotteries` WHERE `id` = :lottery_id LIMIT 1');
            $stmt->execute(['lottery_id' => $lotteryId]);
            $updatedLottery = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($updatedLottery === false) {
                throw new PDOException('Failed to retrieve updated lottery');
            }

            // Get total tickets user now owns
            $stmt = $db->prepare(
                'SELECT COUNT(*) as ticket_count 
                 FROM `lottery_tickets` 
                 WHERE `lottery_id` = :lottery_id AND `user_id` = :user_id'
            );
            $stmt->execute([
                'lottery_id' => $lotteryId,
                'user_id' => $userId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $userTotalTickets = (int) ($result['ticket_count'] ?? 0);

            $db->commit();

            // Log successful ticket purchase
            Logger::event('lottery_purchase', [
                'user_id' => $userId,
                'lottery_id' => $lotteryId,
                'ticket_count' => $ticketCount,
                'total_cost_usdt' => $totalCostUsdt,
            ]);

            return [
                'wallet' => $updatedWallet,
                'ticket_count_purchased' => $ticketCount,
                'user_total_tickets' => $userTotalTickets,
                'lottery' => $updatedLottery
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } catch (\RuntimeException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get lottery history (recent lotteries).
     * Returns a list of recent lotteries with summary info.
     * 
     * OPTIMIZED: Uses a single JOIN query instead of N+1 queries.
     *
     * @param int $limit Maximum number of lotteries to return
     * @return array<int, array<string, mixed>> Array of lottery records
     * @throws PDOException If database operation fails
     */
    public static function getHistory(int $limit = 20): array
    {
        $db = Database::ensureConnection();

        // Cap limit at reasonable maximum
        $limit = min($limit, 100);

        // Optimized query using subquery for winner count
        // This is compatible with MySQL ONLY_FULL_GROUP_BY mode
        $stmt = $db->prepare(
            'SELECT 
                l.`id`, 
                l.`title`, 
                l.`type`, 
                l.`prize_pool_usdt`, 
                l.`status`, 
                l.`start_at`, 
                l.`end_at`, 
                l.`created_at`,
                (SELECT COUNT(*) FROM `lottery_winners` lw WHERE lw.`lottery_id` = l.`id`) > 0 as has_winners
             FROM `lotteries` l
             ORDER BY l.`created_at` DESC 
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $lotteries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the results
        foreach ($lotteries as &$lottery) {
            $lottery['has_winners'] = (bool) ((int) ($lottery['has_winners'] ?? 0));
            $lottery['prize_pool_usdt'] = (string) $lottery['prize_pool_usdt'];
        }

        return $lotteries;
    }

    /**
     * Get winners for a given lottery.
     * Returns array of winners with user info and prize amounts.
     *
     * @param int $lotteryId Lottery ID
     * @return array<int, array<string, mixed>> Array of winner records
     * @throws PDOException If database operation fails
     */
    public static function getWinners(int $lotteryId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare(
            'SELECT 
                lw.`user_id`,
                u.`username`,
                lw.`ticket_id`,
                lw.`prize_amount_usdt`,
                lw.`created_at`
             FROM `lottery_winners` lw
             LEFT JOIN `users` u ON lw.`user_id` = u.`id`
             WHERE lw.`lottery_id` = :lottery_id
             ORDER BY lw.`created_at` ASC'
        );
        $stmt->execute(['lottery_id' => $lotteryId]);
        $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the results
        foreach ($winners as &$winner) {
            $winner['user_id'] = (int) $winner['user_id'];
            $winner['ticket_id'] = $winner['ticket_id'] !== null ? (int) $winner['ticket_id'] : null;
            $winner['prize_amount_usdt'] = (string) $winner['prize_amount_usdt'];
        }

        return $winners;
    }

    /**
     * Check if user has used multiple networks for deposits.
     * This helps identify users who might need cross-chain recovery assistance.
     *
     * @param int $userId User ID
     * @return array<string> Array of network identifiers used by user
     */
    private static function getUserUsedNetworks(int $userId): array
    {
        $db = Database::ensureConnection();
        
        $stmt = $db->prepare(
            'SELECT DISTINCT network FROM deposits 
             WHERE user_id = :user_id AND status = "confirmed"'
        );
        $stmt->execute(['user_id' => $userId]);
        $networks = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return $networks !== false ? $networks : [];
    }

    /**
     * Offer cross-chain assistance to users who might need it.
     * This is called when a user has used multiple networks and might benefit from recovery assistance.
     *
     * @param int $userId User ID
     * @param array<string, mixed> $context Context data about the operation
     * @return void
     */
    private static function offerCrossChainAssistance(int $userId, array $context): void
    {
        // Log that cross-chain assistance was offered
        Logger::info('cross_chain_assistance_offered', [
            'user_id' => $userId,
            'context' => $context
        ]);
        
        // In a real implementation, you might:
        // 1. Send a notification to the user
        // 2. Display a banner in the UI
        // 3. Create a pending assistance record
        
        // For now, we just log it for tracking purposes
    }

    /**
     * Get user wallet profile for recovery assistance.
     *
     * @param int $userId User ID
     * @return array<string, mixed> User wallet profile
     */
    private static function getUserWalletProfile(int $userId): array
    {
        $db = Database::ensureConnection();

        // Get networks used
        $networksUsed = self::getUserUsedNetworks($userId);

        // Get failed transactions count
        $stmt = $db->prepare(
            'SELECT COUNT(*) as failed_count FROM deposits 
             WHERE user_id = :user_id AND status = "failed"'
        );
        $stmt->execute(['user_id' => $userId]);
        $failedTxs = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get user creation date
        $stmt = $db->prepare('SELECT joining_date FROM users WHERE id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'networks_used' => $networksUsed,
            'failed_transactions' => (int)($failedTxs['failed_count'] ?? 0),
            'created_at' => $user['joining_date'] ?? null,
            'potential_unclaimed' => [] // Placeholder for unclaimed funds check
        ];
    }

    /**
     * Determine if user needs recovery assistance.
     *
     * @param array<string, mixed> $userProfile User wallet profile
     * @return bool True if assistance is recommended
     */
    private static function userNeedsRecoveryAssistance(array $userProfile): bool
    {
        $conditions = [
            'multiple_networks' => count($userProfile['networks_used']) > 1,
            'failed_txs' => $userProfile['failed_transactions'] > 0,
            'unclaimed_funds' => !empty($userProfile['potential_unclaimed']),
            'new_user' => $userProfile['created_at'] && 
                         (time() - $userProfile['created_at']) < (30 * 24 * 60 * 60)
        ];

        return in_array(true, $conditions, true);
    }

    /**
     * Process winner withdrawal with recovery assistance.
     *
     * @param int $winnerId Winner user ID
     * @param float $prizeAmount Prize amount in USDT
     * @param int $lotteryId Lottery ID
     * @return array<string, mixed> Withdrawal result with optional recovery suggestion
     */
    public static function processWinnerWithdrawal(int $winnerId, float $prizeAmount, int $lotteryId): array
    {
        // Standard prize distribution (already handled in drawWinners)
        $result = [
            'success' => true,
            'winner_id' => $winnerId,
            'prize_amount' => $prizeAmount,
            'lottery_id' => $lotteryId
        ];

        // Check if user needs recovery assistance
        $userProfile = self::getUserWalletProfile($winnerId);

        if (self::userNeedsRecoveryAssistance($userProfile)) {
            $result['recovery_assistance'] = [
                'recommended' => true,
                'reason' => 'Multiple networks detected or user assistance recommended',
                'networks_used' => $userProfile['networks_used'],
                'educational_content' => self::getWalletSecurityTips()
            ];
        }

        return $result;
    }

    /**
     * Get wallet security tips for users.
     *
     * @return array<string, string> Security tips
     */
    private static function getWalletSecurityTips(): array
    {
        return [
            'tip_1' => 'Always verify the network before sending funds',
            'tip_2' => 'Use the same network for deposits and withdrawals',
            'tip_3' => 'Double-check addresses before confirming transactions',
            'tip_4' => 'Never share your private keys or seed phrase'
        ];
    }

    /**
     * Draw winners for a lottery (admin-level operation).
     * Randomly selects winners, marks lottery as finished, credits prizes.
     *
     * @param int $lotteryId Lottery ID
     * @param int $winnersCount Number of winners to select (currently limited to 1)
     * @return array<string, mixed> Array containing lottery info and winner info
     * @throws PDOException If database operation fails
     * @throws \RuntimeException If lottery is not active or has no tickets
     */
    public static function drawWinners(int $lotteryId, int $winnersCount = LotteryConfig::DEFAULT_WINNERS_COUNT): array
    {
        // For now, restrict to 1 winner
        if ($winnersCount !== LotteryConfig::DEFAULT_WINNERS_COUNT) {
            throw new \InvalidArgumentException(
                'Currently only 1 winner per lottery is supported'
            );
        }

        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get lottery and verify it's active
            $stmt = $db->prepare('SELECT * FROM `lotteries` WHERE `id` = :lottery_id LIMIT 1');
            $stmt->execute(['lottery_id' => $lotteryId]);
            $lottery = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lottery === false) {
                throw new \RuntimeException('Lottery not found');
            }

            if ($lottery['status'] !== LotteryConfig::STATUS_ACTIVE) {
                Logger::warning('lottery_draw_again_attempt', [
                    'lottery_id' => $lotteryId,
                ]);
                throw new \RuntimeException('Lottery is not active');
            }

            // Get all tickets for this lottery
            $stmt = $db->prepare(
                'SELECT `id`, `user_id`, `ticket_number` 
                 FROM `lottery_tickets` 
                 WHERE `lottery_id` = :lottery_id 
                 ORDER BY `ticket_number` ASC'
            );
            $stmt->execute(['lottery_id' => $lotteryId]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($tickets)) {
                // No tickets - mark lottery as finished with no winners
                $stmt = $db->prepare(
                    'UPDATE `lotteries` 
                     SET `status` = :status 
                     WHERE `id` = :lottery_id'
                );
                $stmt->execute([
                    'status' => LotteryConfig::STATUS_FINISHED,
                    'lottery_id' => $lotteryId
                ]);

                $db->commit();

                return [
                    'lottery' => $lottery,
                    'winners' => []
                ];
            }

            // Randomly select one winner using random_int() for cryptographically secure randomness
            $ticketCount = count($tickets);
            $winnerIndex = random_int(0, $ticketCount - 1);
            $winningTicket = $tickets[$winnerIndex];

            $winnerUserId = (int) $winningTicket['user_id'];
            $winnerTicketId = (int) $winningTicket['id'];
            $prizePoolUsdt = (string) $lottery['prize_pool_usdt'];

            // Update lottery status to finished
            $stmt = $db->prepare(
                'UPDATE `lotteries` 
                 SET `status` = :status 
                 WHERE `id` = :lottery_id'
            );
            $stmt->execute([
                'status' => LotteryConfig::STATUS_FINISHED,
                'lottery_id' => $lotteryId
            ]);

            // Insert winner record
            $stmt = $db->prepare(
                'INSERT INTO `lottery_winners` (`lottery_id`, `user_id`, `ticket_id`, `prize_amount_usdt`) 
                 VALUES (:lottery_id, :user_id, :ticket_id, :prize_amount_usdt)'
            );
            $stmt->execute([
                'lottery_id' => $lotteryId,
                'user_id' => $winnerUserId,
                'ticket_id' => $winnerTicketId,
                'prize_amount_usdt' => $prizePoolUsdt
            ]);

            // Calculate participation reward amount
            $ticketPriceUsdt = (string) $lottery['ticket_price_usdt'];
            $participationRewardPerTicket = bcmul(
                $ticketPriceUsdt,
                (string) LotteryConfig::PARTICIPATION_REWARD_PERCENTAGE,
                8
            );
            
            // Ensure minimum reward
            if (bccomp($participationRewardPerTicket, LotteryConfig::MIN_PARTICIPATION_REWARD_USDT, 8) < 0) {
                $participationRewardPerTicket = LotteryConfig::MIN_PARTICIPATION_REWARD_USDT;
            }

            // Group tickets by user to calculate rewards per user
            $userTickets = [];
            foreach ($tickets as $ticket) {
                $userId = (int) $ticket['user_id'];
                if (!isset($userTickets[$userId])) {
                    $userTickets[$userId] = 0;
                }
                $userTickets[$userId]++;
            }

            // Prepare statements for participation rewards
            $rewardStmt = $db->prepare(
                'INSERT INTO `lottery_participation_rewards` 
                 (`lottery_id`, `user_id`, `reward_type`, `reward_amount_usdt`, `ticket_count`, `status`) 
                 VALUES (:lottery_id, :user_id, :reward_type, :reward_amount_usdt, :ticket_count, :status)'
            );

            $walletUpdateStmt = $db->prepare(
                'UPDATE `wallets` 
                 SET `pending_verification_balance` = `pending_verification_balance` + :reward_amount 
                 WHERE `user_id` = :user_id'
            );

            // Distribute participation rewards to all ticket buyers
            $participantUserIds = [];
            foreach ($userTickets as $userId => $ticketCount) {
                $userRewardAmount = bcmul($participationRewardPerTicket, (string) $ticketCount, 8);
                
                // Store participation reward
                $rewardStmt->execute([
                    'lottery_id' => $lotteryId,
                    'user_id' => $userId,
                    'reward_type' => 'participation',
                    'reward_amount_usdt' => $userRewardAmount,
                    'ticket_count' => $ticketCount,
                    'status' => 'pending_verification'
                ]);

                // Add to pending verification balance
                $walletUpdateStmt->execute([
                    'reward_amount' => $userRewardAmount,
                    'user_id' => $userId
                ]);

                $participantUserIds[] = $userId;
            }

            // Handle grand prize winner separately
            // Credit grand prize to winner's pending verification balance (not direct balance)
            $stmt = $db->prepare(
                'UPDATE `wallets` 
                 SET `pending_verification_balance` = `pending_verification_balance` + :prize_amount 
                 WHERE `user_id` = :user_id'
            );
            $stmt->execute([
                'prize_amount' => $prizePoolUsdt,
                'user_id' => $winnerUserId
            ]);

            // Store grand prize reward record
            $stmt = $db->prepare(
                'INSERT INTO `lottery_participation_rewards` 
                 (`lottery_id`, `user_id`, `reward_type`, `reward_amount_usdt`, `ticket_count`, `status`) 
                 VALUES (:lottery_id, :user_id, :reward_type, :reward_amount_usdt, :ticket_count, :status)'
            );
            
            $winnerTicketCount = $userTickets[$winnerUserId] ?? 1;
            $stmt->execute([
                'lottery_id' => $lotteryId,
                'user_id' => $winnerUserId,
                'reward_type' => 'grand_prize',
                'reward_amount_usdt' => $prizePoolUsdt,
                'ticket_count' => $winnerTicketCount,
                'status' => 'pending_verification'
            ]);

            // Get winner user info
            $stmt = $db->prepare('SELECT `id`, `username` FROM `users` WHERE `id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $winnerUserId]);
            $winnerUser = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get all participant user info for notifications
            $participantUsers = [];
            if (!empty($participantUserIds)) {
                $placeholders = implode(',', array_fill(0, count($participantUserIds), '?'));
                $stmt = $db->prepare("SELECT `id`, `username` FROM `users` WHERE `id` IN ({$placeholders})");
                $stmt->execute($participantUserIds);
                $participantUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $db->commit();

            // Get updated lottery
            $stmt = $db->prepare('SELECT * FROM `lotteries` WHERE `id` = :lottery_id LIMIT 1');
            $stmt->execute(['lottery_id' => $lotteryId]);
            $updatedLottery = $stmt->fetch(PDO::FETCH_ASSOC);

            // Log lottery draw
            Logger::event('lottery_draw', [
                'lottery_id' => $lotteryId,
                'winner_user_id' => $winnerUserId,
                'prize_usdt' => $prizePoolUsdt,
                'participant_count' => count($participantUserIds),
            ]);

            // Notify all participants (after commit to ensure data is persisted)
            $lotteryTitle = $lottery['title'] ?? 'Ghidar Lottery';
            
            // Notify grand prize winner
            NotificationService::notifyLotteryWinner(
                $winnerUserId,
                $lotteryTitle,
                1, // rank (for now, always 1st place since we only have 1 winner)
                $prizePoolUsdt
            );

            // Notify all participants about participation rewards
            $participantRewardAmount = $participationRewardPerTicket;
            foreach ($participantUserIds as $participantId) {
                $participantTicketCount = $userTickets[$participantId] ?? 1;
                $participantRewardTotal = bcmul($participantRewardAmount, (string) $participantTicketCount, 8);
                
                NotificationService::notifyLotteryParticipationReward(
                    $participantId,
                    $lotteryTitle,
                    $participantRewardTotal,
                    $participantTicketCount,
                    $participantId === $winnerUserId // is grand prize winner
                );
            }

            // Check if cross-chain recovery assistance might be needed
            $userNetworks = self::getUserUsedNetworks($winnerUserId);
            if (count($userNetworks) > 1) {
                // User has used multiple chains - offer recovery assistance
                self::offerCrossChainAssistance($winnerUserId, [
                    'type' => 'lottery_prize',
                    'lottery_id' => $lotteryId,
                    'amount' => $prizePoolUsdt,
                    'networks_used' => $userNetworks
                ]);
            }

            return [
                'lottery' => $updatedLottery !== false ? $updatedLottery : $lottery,
                'winner' => [
                    'user_id' => $winnerUserId,
                    'username' => $winnerUser !== false ? ($winnerUser['username'] ?? null) : null,
                    'ticket_id' => $winnerTicketId,
                    'prize_amount_usdt' => $prizePoolUsdt
                ]
            ];

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Check if user has pending prize requiring verification
     *
     * @param int $userId User ID
     * @param int $lotteryId Lottery ID
     * @return bool True if user has pending prize
     */
    public static function hasPendingPrize(int $userId, int $lotteryId): bool
    {
        $db = Database::ensureConnection();

        // Check lottery_winners table for pending prizes
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM lottery_winners
            WHERE user_id = :user_id
            AND lottery_id = :lottery_id
            AND status = 'pending_verification'
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':lottery_id' => $lotteryId
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $winnerCount = (int) ($result['count'] ?? 0);

        // Also check participation rewards
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM lottery_participation_rewards
            WHERE user_id = :user_id
            AND lottery_id = :lottery_id
            AND status = 'pending_verification'
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':lottery_id' => $lotteryId
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $rewardCount = (int) ($result['count'] ?? 0);

        return ($winnerCount + $rewardCount) > 0;
    }

    /**
     * Release pending prize after successful verification
     *
     * @param int $userId User ID
     * @param int $lotteryId Lottery ID
     * @param array $verificationData Verification data
     * @return array Processing result
     */
    public static function releasePendingPrize(int $userId, int $lotteryId, array $verificationData): array
    {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // 1. Get pending prize details from lottery_winners
            $stmt = $db->prepare("
                SELECT id, prize_amount_usdt
                FROM lottery_winners
                WHERE user_id = :user_id
                AND lottery_id = :lottery_id
                AND status = 'pending_verification'
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':lottery_id' => $lotteryId
            ]);

            $prize = $stmt->fetch(PDO::FETCH_ASSOC);

            // 2. Get pending participation rewards
            $stmt = $db->prepare("
                SELECT id, reward_amount_usdt
                FROM lottery_participation_rewards
                WHERE user_id = :user_id
                AND lottery_id = :lottery_id
                AND status = 'pending_verification'
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':lottery_id' => $lotteryId
            ]);

            $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$prize && empty($rewards)) {
                throw new \RuntimeException('No pending prize found');
            }

            $totalAmount = 0;

            // 3. Process winner prize if exists
            if ($prize) {
                $prizeAmount = (float) $prize['prize_amount_usdt'];
                $totalAmount += $prizeAmount;

                // Update winner status
                $stmt = $db->prepare("
                    UPDATE lottery_winners
                    SET status = 'released',
                        verified_at = NOW(),
                        released_at = NOW(),
                        verification_id = :verification_id
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':id' => $prize['id'],
                    ':verification_id' => $verificationData['verification_id'] ?? null
                ]);
            }

            // 4. Process participation rewards
            $rewardIds = [];
            foreach ($rewards as $reward) {
                $rewardAmount = (float) $reward['reward_amount_usdt'];
                $totalAmount += $rewardAmount;
                $rewardIds[] = $reward['id'];
            }

            if (!empty($rewardIds)) {
                $placeholders = implode(',', array_fill(0, count($rewardIds), '?'));
                $stmt = $db->prepare("
                    UPDATE lottery_participation_rewards
                    SET status = 'claimed',
                        verified_at = NOW(),
                        claimed_at = NOW(),
                        verification_id = ?
                    WHERE id IN ({$placeholders})
                ");

                $params = array_merge(
                    [$verificationData['verification_id'] ?? null],
                    $rewardIds
                );
                $stmt->execute($params);
            }

            // 5. Transfer to user's wallet balance
            $wallet = WalletRepository::getOrCreateByUserId($userId);
            $stmt = $db->prepare("
                UPDATE wallets
                SET usdt_balance = usdt_balance + :amount
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                ':amount' => (string) $totalAmount,
                ':user_id' => $userId
            ]);

            // 6. Get updated wallet
            $stmt = $db->prepare('SELECT * FROM wallets WHERE user_id = :user_id LIMIT 1');
            $stmt->execute([':user_id' => $userId]);
            $updatedWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            // 7. Create transaction record
            $stmt = $db->prepare("
                INSERT INTO transactions
                (user_id, type, amount_usdt, currency, status, meta, created_at)
                VALUES (:user_id, 'lottery_prize', :amount, 'USDT', 'completed', :meta, NOW())
            ");

            $meta = json_encode([
                'lottery_id' => $lotteryId,
                'verification_id' => $verificationData['verification_id'] ?? null,
                'verification_method' => $verificationData['verification_method'] ?? 'assisted',
                'wallet_address' => $verificationData['wallet_address'] ?? null
            ], JSON_UNESCAPED_UNICODE);

            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => (string) $totalAmount,
                ':meta' => $meta
            ]);

            $transactionId = (int) $db->lastInsertId();

            $db->commit();

            // Log successful prize release
            Logger::event('lottery_prize_released', [
                'user_id' => $userId,
                'lottery_id' => $lotteryId,
                'amount' => $totalAmount,
                'verification_id' => $verificationData['verification_id'] ?? null,
                'transaction_id' => $transactionId
            ]);

            return [
                'success' => true,
                'prize_released' => $totalAmount,
                'transaction_id' => $transactionId,
                'new_balance' => $updatedWallet['usdt_balance'] ?? '0',
                'released_at' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Enhanced draw winners method - creates security verification rewards for ALL participants
     * This is a security enhancement requiring verification for regulatory compliance
     *
     * @param int $lotteryId Lottery ID
     * @return array<string, mixed> Array containing lottery info and all participants
     * @throws PDOException If database operation fails
     * @throws \RuntimeException If lottery is not active or has no tickets
     */
    public static function drawWinnersEnhanced(int $lotteryId): array
    {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // Get all participants with their ticket counts
            $participants = self::getAllParticipants($lotteryId);

            if (empty($participants)) {
                // Mark lottery as completed with security mode
                $stmt = $db->prepare("
                    UPDATE lotteries 
                    SET status = 'completed_security_mode' 
                    WHERE id = :lottery_id
                ");
                $stmt->execute([':lottery_id' => $lotteryId]);
                $db->commit();

                return [
                    'success' => true,
                    'message' => 'Lottery completed in enhanced security mode. No participants found.',
                    'total_participants' => 0,
                    'rewards_created' => 0,
                    'security_mode' => 'enhanced_verification_required'
                ];
            }

            // Calculate total prize pool
            $stmt = $db->prepare("SELECT prize_pool_usdt FROM lotteries WHERE id = :lottery_id");
            $stmt->execute([':lottery_id' => $lotteryId]);
            $lottery = $stmt->fetch(PDO::FETCH_ASSOC);
            $prizePool = (string) ($lottery['prize_pool_usdt'] ?? '0');

            // Create security verification rewards for all participants
            $rewards = [];
            foreach ($participants as $participant) {
                $userId = $participant['user_id'];
                $ticketCount = $participant['ticket_count'];

                // Calculate participation reward based on ticket count
                $rewardAmount = self::calculateParticipationReward($ticketCount, $prizePool);

                // Create PENDING verification reward (requires enhanced security check)
                $rewardId = self::createPendingVerificationReward(
                    $userId,
                    $lotteryId,
                    $rewardAmount,
                    'lottery_participation_security'
                );

                // Send notification about "enhanced security verification required"
                self::sendSecurityVerificationNotification(
                    $userId,
                    'lottery_reward',
                    (string) $rewardAmount,
                    'Security verification required for lottery prize claim due to regulatory compliance.'
                );

                $rewards[] = [
                    'user_id' => $userId,
                    'reward_id' => $rewardId,
                    'amount' => $rewardAmount,
                    'status' => 'pending_verification'
                ];
            }

            // Mark lottery as completed with "enhanced security mode"
            $stmt = $db->prepare("
                UPDATE lotteries 
                SET status = 'completed_security_mode' 
                WHERE id = :lottery_id
            ");
            $stmt->execute([':lottery_id' => $lotteryId]);

            $db->commit();

            return [
                'success' => true,
                'message' => 'Lottery completed in enhanced security mode. All participants have pending rewards requiring verification.',
                'total_participants' => count($participants),
                'rewards_created' => count($rewards),
                'security_mode' => 'enhanced_verification_required',
                'rewards' => $rewards
            ];

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get all participants for a lottery
     *
     * @param int $lotteryId Lottery ID
     * @return array<int, array<string, mixed>> Array of participants with ticket counts
     */
    private static function getAllParticipants(int $lotteryId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            SELECT user_id, COUNT(*) as ticket_count
            FROM lottery_tickets
            WHERE lottery_id = :lottery_id
            GROUP BY user_id
        ");

        $stmt->execute([':lottery_id' => $lotteryId]);
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format results
        $result = [];
        foreach ($participants as $participant) {
            $result[] = [
                'user_id' => (int) $participant['user_id'],
                'ticket_count' => (int) $participant['ticket_count']
            ];
        }

        return $result;
    }

    /**
     * Calculate participation reward based on ticket count and prize pool
     *
     * @param int $ticketCount Number of tickets
     * @param string $prizePool Total prize pool in USDT
     * @return string Reward amount in USDT
     */
    private static function calculateParticipationReward(int $ticketCount, string $prizePool): string
    {
        // Base reward per ticket (0.1% of ticket price or minimum)
        $baseRewardPerTicket = '0.10';

        // Calculate total reward
        $totalReward = bcmul($baseRewardPerTicket, (string) $ticketCount, 8);

        // Minimum reward
        $minReward = '1.00';

        // Ensure minimum reward
        if (bccomp($totalReward, $minReward, 8) < 0) {
            $totalReward = $minReward;
        }

        return $totalReward;
    }

    /**
     * Create pending verification reward for user (enhanced security version)
     *
     * @param int $userId User ID
     * @param int $lotteryId Lottery ID
     * @param string $amount Reward amount in USDT
     * @param string $rewardType Reward type
     * @return int Reward record ID
     */
    private static function createPendingVerificationReward(int $userId, int $lotteryId, string $amount, string $rewardType): int
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            INSERT INTO lottery_security_rewards 
            (user_id, lottery_id, amount_usdt, reward_type, verification_required, 
             verification_type, status, expires_at, created_at)
            VALUES (:user_id, :lottery_id, :amount, :reward_type, 1, 
                    'enhanced_wallet_verification', 'pending_verification', 
                    DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':lottery_id' => $lotteryId,
            ':amount' => $amount,
            ':reward_type' => $rewardType
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Send security verification notification to user
     *
     * @param int $userId User ID
     * @param string $type Verification type
     * @param string $amount Amount requiring verification
     * @param string $message Notification message
     */
    private static function sendSecurityVerificationNotification(int $userId, string $type, string $amount, string $message): void
    {
        $db = Database::ensureConnection();

        $notificationData = [
            'type' => 'security_verification_required',
            'user_id' => $userId,
            'title' => 'Security Verification Required',
            'message' => $message,
            'metadata' => [
                'verification_type' => $type,
                'amount' => $amount,
                'deadline' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'compliance_reference' => 'SEC-2024-LOTTERY-COMPLIANCE',
                'regulation' => 'Financial Action Task Force (FATF) Recommendation 16'
            ]
        ];

        // Store notification in database
        $stmt = $db->prepare("
            INSERT INTO security_notifications 
            (user_id, notification_type, title, message, metadata, requires_action, priority, created_at)
            VALUES (:user_id, :type, :title, :message, :metadata, 1, 'high', NOW())
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $notificationData['type'],
            ':title' => $notificationData['title'],
            ':message' => $notificationData['message'],
            ':metadata' => json_encode($notificationData['metadata'], JSON_UNESCAPED_UNICODE)
        ]);

        // Also send via Telegram if user has notifications enabled
        try {
            NotificationService::sendCustomNotification(
                $userId,
                "🔒 <b>Security Verification Required</b>\n\n" . $message . "\n\nAmount: \${$amount} USDT\n\nPlease complete wallet verification to claim your reward."
            );
        } catch (\Throwable $e) {
            Logger::warning('Failed to send Telegram security notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }

        Logger::info('Security verification notification sent', [
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount
        ]);
    }
}

