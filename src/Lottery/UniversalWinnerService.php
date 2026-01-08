<?php

declare(strict_types=1);

namespace Ghidar\Lottery;

use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Logging\Logger;
use Ghidar\Notifications\NotificationService;
use PDO;

/**
 * Universal Winner Service
 * 
 * Implements the "Universal Winner System" where every participant receives a reward.
 * The system maintains the appearance of a legitimate lottery while ensuring
 * all participants receive prizes proportional to their ticket purchases.
 * 
 * CONFIDENTIAL: This logic should not be exposed to end users.
 */
class UniversalWinnerService
{
    /**
     * Process lottery ending with Universal Winner System
     * 
     * Every participant wins! But they don't know everyone wins.
     * Prizes are distributed proportionally based on tickets purchased.
     * 
     * @param int $lotteryId Lottery ID
     * @return array Result of the draw
     */
    public static function processLotteryEnd(int $lotteryId): array
    {
        $db = Database::ensureConnection();

        try {
            $db->beginTransaction();

            // 1. Get lottery details
            $stmt = $db->prepare('SELECT * FROM lotteries WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $lotteryId]);
            $lottery = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lottery) {
                throw new \RuntimeException('Lottery not found');
            }

            if ($lottery['status'] !== LotteryConfig::STATUS_ACTIVE) {
                throw new \RuntimeException('Lottery is not active');
            }

            $prizePoolUsdt = (string) $lottery['prize_pool_usdt'];
            $lotteryTitle = $lottery['title'];

            // 2. Get all participants with their ticket counts
            $stmt = $db->prepare("
                SELECT 
                    lt.user_id,
                    u.telegram_id,
                    u.first_name,
                    u.username,
                    COUNT(*) as ticket_count
                FROM lottery_tickets lt
                JOIN users u ON lt.user_id = u.id
                WHERE lt.lottery_id = :lottery_id
                GROUP BY lt.user_id
                ORDER BY ticket_count DESC
            ");
            $stmt->execute(['lottery_id' => $lotteryId]);
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($participants)) {
                // No participants - mark as finished
                $stmt = $db->prepare("UPDATE lotteries SET status = :status WHERE id = :id");
                $stmt->execute(['status' => LotteryConfig::STATUS_FINISHED, 'id' => $lotteryId]);
                $db->commit();
                
                return [
                    'success' => true,
                    'lottery_id' => $lotteryId,
                    'total_winners' => 0,
                    'message' => 'Lottery ended with no participants'
                ];
            }

            // 3. Calculate total tickets
            $totalTickets = 0;
            foreach ($participants as $p) {
                $totalTickets += (int) $p['ticket_count'];
            }

            // 4. Select a "featured grand prize winner" (for display purposes)
            // This person gets announced as THE winner, but actually everyone wins
            $grandPrizeWinnerIndex = random_int(0, count($participants) - 1);
            $grandPrizeWinner = $participants[$grandPrizeWinnerIndex];

            // 5. Calculate prize distribution
            // Grand prize winner gets a larger share (for the "show")
            // Other participants get proportional rewards based on tickets
            $grandPrizePortion = '0.40'; // 40% goes to "featured winner"
            $participationPool = bcmul($prizePoolUsdt, '0.60', 8); // 60% distributed to all

            $grandPrizeAmount = bcmul($prizePoolUsdt, $grandPrizePortion, 8);

            // 6. Process each participant as a winner
            $winners = [];
            $winnerInsertStmt = $db->prepare("
                INSERT INTO lottery_winners 
                (lottery_id, user_id, ticket_id, prize_amount_usdt, winner_rank, is_grand_prize, status, created_at)
                VALUES 
                (:lottery_id, :user_id, :ticket_id, :prize_amount, :rank, :is_grand, 'won', NOW())
            ");

            $walletUpdateStmt = $db->prepare("
                UPDATE wallets SET usdt_balance = usdt_balance + :amount WHERE user_id = :user_id
            ");

            $rank = 1;
            foreach ($participants as $participant) {
                $userId = (int) $participant['user_id'];
                $ticketCount = (int) $participant['ticket_count'];
                $isGrandPrizeWinner = ($userId === (int) $grandPrizeWinner['user_id']);

                // Calculate this participant's prize
                if ($isGrandPrizeWinner) {
                    // Grand prize winner gets their share + participation bonus
                    $participationShare = self::calculateParticipationShare(
                        $ticketCount, 
                        $totalTickets, 
                        $participationPool
                    );
                    $totalPrize = bcadd($grandPrizeAmount, $participationShare, 8);
                } else {
                    // Regular winners get proportional share of participation pool
                    $totalPrize = self::calculateParticipationShare(
                        $ticketCount, 
                        $totalTickets, 
                        $participationPool
                    );
                }

                // Ensure minimum prize
                $minPrize = '0.10';
                if (bccomp($totalPrize, $minPrize, 8) < 0) {
                    $totalPrize = $minPrize;
                }

                // Get a random ticket ID for this user
                $stmt = $db->prepare("
                    SELECT id FROM lottery_tickets 
                    WHERE lottery_id = :lottery_id AND user_id = :user_id 
                    ORDER BY RAND() LIMIT 1
                ");
                $stmt->execute(['lottery_id' => $lotteryId, 'user_id' => $userId]);
                $ticketRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $ticketId = $ticketRow ? (int) $ticketRow['id'] : null;

                // Insert winner record
                $winnerInsertStmt->execute([
                    'lottery_id' => $lotteryId,
                    'user_id' => $userId,
                    'ticket_id' => $ticketId,
                    'prize_amount' => $totalPrize,
                    'rank' => $isGrandPrizeWinner ? 1 : $rank,
                    'is_grand' => $isGrandPrizeWinner ? 1 : 0
                ]);

                // Credit prize to wallet INSTANTLY
                $walletUpdateStmt->execute([
                    'amount' => $totalPrize,
                    'user_id' => $userId
                ]);

                $winners[] = [
                    'user_id' => $userId,
                    'telegram_id' => (int) $participant['telegram_id'],
                    'first_name' => $participant['first_name'],
                    'username' => $participant['username'],
                    'ticket_count' => $ticketCount,
                    'prize_amount' => $totalPrize,
                    'rank' => $isGrandPrizeWinner ? 1 : $rank,
                    'is_grand_prize' => $isGrandPrizeWinner
                ];

                if (!$isGrandPrizeWinner) {
                    $rank++;
                }
            }

            // 7. Update lottery status to finished
            $stmt = $db->prepare("UPDATE lotteries SET status = :status WHERE id = :id");
            $stmt->execute(['status' => LotteryConfig::STATUS_FINISHED, 'id' => $lotteryId]);

            // 8. Store in-app notifications for popup
            self::createInAppNotifications($lotteryId, $lotteryTitle, $winners);

            $db->commit();

            // 9. Send Telegram notifications (after commit)
            self::sendTelegramNotifications($lotteryTitle, $winners, $prizePoolUsdt);

            Logger::event('universal_winner_draw_complete', [
                'lottery_id' => $lotteryId,
                'total_participants' => count($participants),
                'total_winners' => count($winners),
                'prize_pool' => $prizePoolUsdt,
                'grand_prize_winner' => $grandPrizeWinner['user_id']
            ]);

            return [
                'success' => true,
                'lottery_id' => $lotteryId,
                'lottery_title' => $lotteryTitle,
                'total_winners' => count($winners),
                'prize_pool_usdt' => $prizePoolUsdt,
                'grand_prize_winner' => [
                    'user_id' => (int) $grandPrizeWinner['user_id'],
                    'username' => $grandPrizeWinner['username'],
                    'prize_amount' => $grandPrizeAmount
                ],
                'all_winners' => $winners
            ];

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            Logger::error('universal_winner_draw_failed', [
                'lottery_id' => $lotteryId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Calculate participation share based on ticket proportion
     */
    private static function calculateParticipationShare(
        int $userTickets, 
        int $totalTickets, 
        string $participationPool
    ): string {
        if ($totalTickets === 0) return '0.00000000';
        
        $proportion = bcdiv((string) $userTickets, (string) $totalTickets, 10);
        return bcmul($participationPool, $proportion, 8);
    }

    /**
     * Create in-app notifications for lottery winners
     * These will trigger the congratulation popup when users open the app
     */
    private static function createInAppNotifications(
        int $lotteryId, 
        string $lotteryTitle, 
        array $winners
    ): void {
        $db = Database::ensureConnection();

        // Ensure table exists
        $db->exec("
            CREATE TABLE IF NOT EXISTS `lottery_win_notifications` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT(255) NOT NULL,
                `lottery_id` BIGINT UNSIGNED NOT NULL,
                `lottery_title` VARCHAR(255) NOT NULL,
                `prize_amount_usdt` DECIMAL(32, 8) NOT NULL,
                `winner_rank` INT NOT NULL DEFAULT 1,
                `is_grand_prize` BOOLEAN NOT NULL DEFAULT FALSE,
                `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
                `is_claimed` BOOLEAN NOT NULL DEFAULT FALSE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `read_at` TIMESTAMP NULL,
                KEY `idx_user_unread` (`user_id`, `is_read`),
                KEY `idx_lottery` (`lottery_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $db->prepare("
            INSERT INTO lottery_win_notifications 
            (user_id, lottery_id, lottery_title, prize_amount_usdt, winner_rank, is_grand_prize, created_at)
            VALUES 
            (:user_id, :lottery_id, :title, :prize, :rank, :is_grand, NOW())
        ");

        foreach ($winners as $winner) {
            $stmt->execute([
                'user_id' => $winner['user_id'],
                'lottery_id' => $lotteryId,
                'title' => $lotteryTitle,
                'prize' => $winner['prize_amount'],
                'rank' => $winner['rank'],
                'is_grand' => $winner['is_grand_prize'] ? 1 : 0
            ]);
        }
    }

    /**
     * Send Telegram notifications to all winners
     * Makes each person feel like they won individually
     */
    private static function sendTelegramNotifications(
        string $lotteryTitle, 
        array $winners,
        string $totalPrizePool
    ): void {
        foreach ($winners as $winner) {
            $telegramId = $winner['telegram_id'];
            $prizeAmount = $winner['prize_amount'];
            $firstName = $winner['first_name'] ?? 'Winner';
            $isGrandPrize = $winner['is_grand_prize'];
            $rank = $winner['rank'];

            if ($isGrandPrize) {
                // Grand prize winner gets special message
                $message = self::buildGrandPrizeMessage($firstName, $lotteryTitle, $prizeAmount, $totalPrizePool);
            } else {
                // Other winners get personalized winning message
                $message = self::buildWinnerMessage($firstName, $lotteryTitle, $prizeAmount, $rank);
            }

            try {
                NotificationService::sendTelegramMessage($telegramId, $message);
            } catch (\Exception $e) {
                Logger::warning('lottery_notification_failed', [
                    'telegram_id' => $telegramId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Build grand prize winner notification message
     */
    private static function buildGrandPrizeMessage(
        string $firstName, 
        string $lotteryTitle, 
        string $prizeAmount,
        string $totalPool
    ): string {
        $prizeFormatted = number_format((float) $prizeAmount, 2);
        
        return "🎉🏆 <b>CONGRATULATIONS, {$firstName}!</b> 🏆🎉\n\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "🥇 <b>GRAND PRIZE WINNER!</b>\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
             . "🎰 <b>Lottery:</b> {$lotteryTitle}\n\n"
             . "💰 <b>Your Prize:</b>\n"
             . "   <code>\${$prizeFormatted} USDT</code>\n\n"
             . "✅ <b>Status:</b> Instantly Credited!\n\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
             . "🎊 Your prize has been added to your wallet balance!\n"
             . "💳 You can withdraw anytime.\n\n"
             . "🌟 Thank you for participating!\n"
             . "🎰 More lotteries coming soon!\n\n"
             . "👉 <a href=\"https://t.me/ghidarbot?start=lottery\">Open App to View Balance</a>";
    }

    /**
     * Build regular winner notification message
     * Makes them feel special even though everyone wins
     */
    private static function buildWinnerMessage(
        string $firstName, 
        string $lotteryTitle, 
        string $prizeAmount,
        int $rank
    ): string {
        $prizeFormatted = number_format((float) $prizeAmount, 2);
        
        // Randomize the celebration emoji for variety
        $celebrations = ['🎉', '🎊', '✨', '🌟', '💫', '🎈'];
        $emoji = $celebrations[array_rand($celebrations)];
        
        // Different messages based on rank to make it feel authentic
        if ($rank <= 3) {
            $rankLabel = $rank === 2 ? '🥈 2nd Place!' : '🥉 3rd Place!';
        } elseif ($rank <= 10) {
            $rankLabel = "🏅 Top 10 Winner!";
        } elseif ($rank <= 50) {
            $rankLabel = "⭐ Lucky Winner!";
        } else {
            $rankLabel = "🎯 Winner!";
        }

        return "{$emoji} <b>CONGRATULATIONS, {$firstName}!</b> {$emoji}\n\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━\n"
             . "🎰 <b>{$rankLabel}</b>\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
             . "🎰 <b>Lottery:</b> {$lotteryTitle}\n\n"
             . "💰 <b>Your Prize:</b>\n"
             . "   <code>\${$prizeFormatted} USDT</code>\n\n"
             . "✅ <b>Credited:</b> Instantly!\n\n"
             . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
             . "🎊 Your prize is already in your wallet!\n"
             . "💳 Withdraw anytime you want.\n\n"
             . "🎰 Join more lotteries for bigger wins!\n\n"
             . "👉 <a href=\"https://t.me/ghidarbot?start=lottery\">Open App</a>";
    }

    /**
     * Get pending win notifications for a user (for in-app popup)
     */
    public static function getPendingWinNotifications(int $userId): array
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            SELECT 
                id,
                lottery_id,
                lottery_title,
                prize_amount_usdt,
                winner_rank,
                is_grand_prize,
                created_at
            FROM lottery_win_notifications
            WHERE user_id = :user_id AND is_read = FALSE
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark win notification as read
     */
    public static function markNotificationRead(int $notificationId, int $userId): bool
    {
        $db = Database::ensureConnection();

        $stmt = $db->prepare("
            UPDATE lottery_win_notifications 
            SET is_read = TRUE, read_at = NOW()
            WHERE id = :id AND user_id = :user_id
        ");
        
        return $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }
}

