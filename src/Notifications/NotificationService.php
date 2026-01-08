<?php

declare(strict_types=1);

namespace Ghidar\Notifications;

use Ghidar\Telegram\BotClient;
use Ghidar\Core\Database;
use PDO;

/**
 * Notification Service for Ghidar
 * Sends Telegram notifications to users for key events.
 */
class NotificationService
{
    private static ?BotClient $bot = null;

    /**
     * Get BotClient instance (singleton pattern).
     */
    private static function getBot(): BotClient
    {
        if (self::$bot === null) {
            self::$bot = new BotClient();
        }
        return self::$bot;
    }

    /**
     * Send notification when user marks deposit as sent (pending confirmation).
     *
     * @param int $userId User's Telegram ID
     * @param string $network Blockchain network (ERC20, BEP20, TRC20)
     * @param string $amountUsdt Amount in USDT
     * @param string $address Deposit address
     * @param int $depositId Deposit ID for reference
     */
    public static function notifyDepositPending(
        int $userId,
        string $network,
        string $amountUsdt,
        string $address,
        int $depositId
    ): void {
        try {
            $networkName = match (strtolower($network)) {
                'erc20' => 'Ethereum (ERC20)',
                'bep20' => 'BSC (BEP20)',
                'trc20' => 'Tron (TRC20)',
                default => strtoupper($network)
            };

            $shortAddress = substr($address, 0, 8) . '...' . substr($address, -6);

            $message = "
🔄 <b>Deposit Pending</b>

━━━━━━━━━━━━━━━━━━━━━

💰 <b>Amount:</b> \${$amountUsdt} USDT
🌐 <b>Network:</b> {$networkName}
📍 <b>Address:</b> <code>{$shortAddress}</code>
🆔 <b>Reference:</b> #{$depositId}

━━━━━━━━━━━━━━━━━━━━━

⏳ We're monitoring the blockchain for your transaction.

⚡ You'll receive a notification as soon as your deposit is confirmed.

💡 <i>This usually takes 1-5 minutes depending on network congestion.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify deposit pending for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification for confirmed deposit.
     *
     * @param int $userId User's Telegram ID
     * @param string $network Blockchain network (ERC20, BEP20, TRC20)
     * @param string $amountUsdt Amount in USDT
     * @param string $productType Product type (wallet_topup, lottery_tickets, ai_trader)
     * @param array<string, mixed> $meta Additional metadata
     */
    public static function notifyDepositConfirmed(
        int $userId,
        string $network,
        string $amountUsdt,
        string $productType,
        array $meta = []
    ): void {
        try {
            $networkName = match (strtolower($network)) {
                'erc20' => 'Ethereum (ERC20)',
                'bep20' => 'BSC (BEP20)',
                'trc20' => 'Tron (TRC20)',
                default => strtoupper($network)
            };

            $productDescription = match ($productType) {
                'wallet_topup' => '💼 Wallet Balance',
                'lottery_tickets' => '🎟️ Lottery Tickets',
                'ai_trader' => '🤖 AI Trader Account',
                default => $productType
            };

            $ticketInfo = '';
            if ($productType === 'lottery_tickets' && isset($meta['ticket_count'])) {
                $ticketInfo = "\n🎫 <b>Tickets Purchased:</b> {$meta['ticket_count']}";
            }

            $message = "
✅ <b>Deposit Confirmed!</b>

━━━━━━━━━━━━━━━━━━━━━

💰 <b>Amount:</b> \${$amountUsdt} USDT
🌐 <b>Network:</b> {$networkName}
📦 <b>Credited to:</b> {$productDescription}{$ticketInfo}

━━━━━━━━━━━━━━━━━━━━━

🎉 <b>Your funds have been credited successfully!</b>

💡 You can now use your balance in the app.
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Log error but don't break the main flow
            error_log("NotificationService: Failed to notify deposit for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification when deposit fails or expires.
     *
     * @param int $userId User's Telegram ID
     * @param string $network Blockchain network
     * @param string $amountUsdt Expected amount
     * @param string $reason Failure reason
     * @param int $depositId Deposit ID
     */
    public static function notifyDepositFailed(
        int $userId,
        string $network,
        string $amountUsdt,
        string $reason,
        int $depositId
    ): void {
        try {
            $networkName = match (strtolower($network)) {
                'erc20' => 'Ethereum (ERC20)',
                'bep20' => 'BSC (BEP20)',
                'trc20' => 'Tron (TRC20)',
                default => strtoupper($network)
            };

            $message = "
❌ <b>Deposit Issue</b>

━━━━━━━━━━━━━━━━━━━━━

💰 <b>Amount:</b> \${$amountUsdt} USDT
🌐 <b>Network:</b> {$networkName}
🆔 <b>Reference:</b> #{$depositId}

━━━━━━━━━━━━━━━━━━━━━

⚠️ <b>Issue:</b> {$reason}

💡 If you've already sent the funds, please contact support with your transaction hash.

📧 <i>Our team will help resolve this quickly.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify deposit failed for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification for lottery winner.
     *
     * @param int $userId User's Telegram ID
     * @param string $lotteryTitle Lottery title
     * @param int $rank Winner rank (1st, 2nd, etc.)
     * @param string $prizeAmountUsdt Prize amount in USDT
     */
    public static function notifyLotteryWinner(
        int $userId,
        string $lotteryTitle,
        int $rank,
        string $prizeAmountUsdt
    ): void {
        try {
            $rankSuffix = match ($rank) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th'
            };

            $message = "
🎉🎉🎉 <b>CONGRATULATIONS!</b> 🎉🎉🎉

You won the <b>{$lotteryTitle}</b>!

🏆 Rank: {$rank}{$rankSuffix} Place
💰 Prize: <b>\${$prizeAmountUsdt} USDT</b>

Your prize has been credited to your pending balance and awaits verification!

🔒 Please complete wallet verification to claim your prize.
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Log error but don't break the main flow
            error_log("NotificationService: Failed to notify lottery winner {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification for lottery participation reward.
     * All participants receive this notification to create positive engagement.
     *
     * @param int $userId User's Telegram ID
     * @param string $lotteryTitle Lottery title
     * @param string $rewardAmountUsdt Reward amount in USDT
     * @param int $ticketCount Number of tickets user purchased
     * @param bool $isGrandPrizeWinner Whether this user is also the grand prize winner
     */
    public static function notifyLotteryParticipationReward(
        int $userId,
        string $lotteryTitle,
        string $rewardAmountUsdt,
        int $ticketCount,
        bool $isGrandPrizeWinner = false
    ): void {
        try {
            // Skip notification if this user is the grand prize winner (they already got a winner notification)
            if ($isGrandPrizeWinner) {
                return;
            }

            $ticketText = $ticketCount === 1 ? 'ticket' : 'tickets';

            $message = "
🎊 <b>You're a Winner!</b> 🎊

Congratulations on participating in <b>{$lotteryTitle}</b>!

🎫 You purchased {$ticketCount} {$ticketText}
🎁 Participation Reward: <b>\${$rewardAmountUsdt} USDT</b>

Your reward has been added to your pending balance!

🔒 Complete wallet verification to claim your reward and join future lotteries with even better chances!
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Log error but don't break the main flow
            error_log("NotificationService: Failed to notify participation reward for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification for withdrawal completed.
     *
     * @param int $userId User's Telegram ID
     * @param string $network Blockchain network
     * @param string $amountUsdt Amount in USDT
     * @param string $address Destination address
     * @param string|null $txHash Transaction hash (if available)
     */
    public static function notifyWithdrawalCompleted(
        int $userId,
        string $network,
        string $amountUsdt,
        string $address,
        ?string $txHash = null
    ): void {
        try {
            $networkName = match (strtolower($network)) {
                'erc20' => 'Ethereum (ERC20)',
                'bep20' => 'BSC (BEP20)',
                'trc20' => 'Tron (TRC20)',
                default => strtoupper($network)
            };

            $shortAddress = substr($address, 0, 8) . '...' . substr($address, -6);
            $txInfo = '';
            if ($txHash) {
                $shortTx = substr($txHash, 0, 10) . '...' . substr($txHash, -8);
                $txInfo = "\n🔗 <b>TX Hash:</b> <code>{$shortTx}</code>";
            }

            $message = "
💸 <b>Withdrawal Completed!</b>

━━━━━━━━━━━━━━━━━━━━━

💰 <b>Amount:</b> \${$amountUsdt} USDT
🌐 <b>Network:</b> {$networkName}
📍 <b>To Address:</b> <code>{$shortAddress}</code>{$txInfo}

━━━━━━━━━━━━━━━━━━━━━

✅ <b>Your funds have been sent successfully!</b>

💡 <i>The transaction should arrive in your wallet within a few minutes.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify withdrawal for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification when withdrawal is being processed.
     *
     * @param int $userId User's Telegram ID
     * @param string $network Blockchain network
     * @param string $amountUsdt Amount in USDT
     * @param string $address Destination address
     */
    public static function notifyWithdrawalProcessing(
        int $userId,
        string $network,
        string $amountUsdt,
        string $address
    ): void {
        try {
            $networkName = match (strtolower($network)) {
                'erc20' => 'Ethereum (ERC20)',
                'bep20' => 'BSC (BEP20)',
                'trc20' => 'Tron (TRC20)',
                default => strtoupper($network)
            };

            $shortAddress = substr($address, 0, 8) . '...' . substr($address, -6);

            $message = "
🔄 <b>Withdrawal Processing</b>

━━━━━━━━━━━━━━━━━━━━━

💰 <b>Amount:</b> \${$amountUsdt} USDT
🌐 <b>Network:</b> {$networkName}
📍 <b>To Address:</b> <code>{$shortAddress}</code>

━━━━━━━━━━━━━━━━━━━━━

⏳ <b>Your withdrawal is being processed.</b>

💡 <i>You'll receive a confirmation once the transaction is complete.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify withdrawal processing for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification for AI Trader performance update.
     *
     * @param int $userId User's Telegram ID
     * @param string $pnlUsdt Profit/Loss in USDT
     * @param string $currentBalance Current balance in USDT
     */
    public static function notifyAiTraderPerformance(
        int $userId,
        string $pnlUsdt,
        string $currentBalance
    ): void {
        try {
            $pnl = (float) $pnlUsdt;
            $emoji = $pnl >= 0 ? '📈' : '📉';
            $sign = $pnl >= 0 ? '+' : '';

            $message = "
{$emoji} <b>AI Trader Update</b>

💰 P&L: {$sign}\${$pnlUsdt} USDT
💼 Balance: \${$currentBalance} USDT

Your AI Trader is working for you!
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify AI trader performance for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send custom notification to a user.
     *
     * @param int $userId User's Telegram ID
     * @param string $message Message text (HTML format)
     */
    public static function sendCustomNotification(int $userId, string $message): void
    {
        try {
            self::getBot()->sendMessage($userId, $message, [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to send custom notification to user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification for referral reward earned.
     *
     * @param int $referrerUserId User who earned the referral reward
     * @param int $fromUserId User whose action triggered the reward
     * @param int $level Referral level (1 for L1, 2 for L2)
     * @param string $rewardAmountUsdt Reward amount in USDT
     * @param string $sourceType Source type (wallet_deposit, ai_trader_deposit, lottery_purchase)
     */
    public static function notifyReferralReward(
        int $referrerUserId,
        int $fromUserId,
        int $level,
        string $rewardAmountUsdt,
        string $sourceType
    ): void {
        try {
            $sourceDescription = match ($sourceType) {
                'wallet_deposit' => 'wallet deposit',
                'ai_trader_deposit' => 'AI Trader deposit',
                'lottery_purchase' => 'lottery purchase',
                default => $sourceType
            };

            $levelText = $level === 1 ? 'direct' : 'indirect';

            $message = "
🎉 <b>Referral Reward Earned!</b>

You just earned <b>\${$rewardAmountUsdt} USDT</b> from your level {$level} ({$levelText}) referral's {$sourceDescription}.

Keep inviting friends to earn more rewards!
";

            self::getBot()->sendMessage($referrerUserId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            // Log error but don't break the main flow
            error_log("NotificationService: Failed to notify referral reward for user {$referrerUserId}: " . $e->getMessage());
        }
    }

    /**
     * Broadcast message to all users (limited batch).
     * For production, implement proper queue-based system.
     *
     * @param string $message Message text (HTML format)
     * @param int $limit Maximum number of users to send to
     * @return array{sent: int, failed: int} Result statistics
     */
    public static function broadcastMessage(string $message, int $limit = 100): array
    {
        $db = Database::getConnection();
        $bot = self::getBot();
        
        $stmt = $db->prepare('SELECT id FROM users LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $sent = 0;
        $failed = 0;

        foreach ($users as $userId) {
            try {
                $result = $bot->sendMessage($userId, $message, [
                    'parse_mode' => 'HTML',
                ]);
                
                if ($result && isset($result->ok) && $result->ok) {
                    $sent++;
                } else {
                    $failed++;
                }
                
                // Small delay to avoid rate limiting
                usleep(50000); // 50ms
            } catch (\Throwable $e) {
                $failed++;
                error_log("NotificationService: Broadcast failed for user {$userId}: " . $e->getMessage());
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * Send a direct Telegram message to a user by their Telegram ID.
     *
     * @param int $telegramId User's Telegram ID
     * @param string $message HTML-formatted message
     * @param array $options Additional options (reply_markup, etc.)
     * @return bool True if sent successfully
     */
    public static function sendTelegramMessage(int $telegramId, string $message, array $options = []): bool
    {
        try {
            $bot = self::getBot();
            
            $result = $bot->sendMessage($telegramId, $message, array_merge([
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ], $options));
            
            return $result && isset($result->ok) && $result->ok;
        } catch (\Throwable $e) {
            error_log("NotificationService: sendTelegramMessage failed for {$telegramId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a custom notification to a user (by internal user ID).
     *
     * @param int $userId Internal user ID
     * @param string $message HTML-formatted message
     * @return bool True if sent successfully
     */
    public static function sendCustomNotificationById(int $userId, string $message): bool
    {
        try {
            // Get user's telegram_id from database
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT telegram_id FROM users WHERE id = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !$user['telegram_id']) {
                error_log("NotificationService: User {$userId} not found or has no telegram_id");
                return false;
            }

            return self::sendTelegramMessage((int) $user['telegram_id'], $message);
        } catch (\Throwable $e) {
            error_log("NotificationService: sendCustomNotificationById failed for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    // ==========================================
    // LOTTERY NOTIFICATIONS
    // ==========================================

    /**
     * Send notification when lottery is ending soon.
     *
     * @param int $userId User's Telegram ID
     * @param string $lotteryTitle Lottery title
     * @param string $prizePoolUsdt Current prize pool in USDT
     * @param int $userTicketCount User's ticket count
     * @param string $timeRemaining Human-readable time remaining
     */
    public static function notifyLotteryEndingSoon(
        int $userId,
        string $lotteryTitle,
        string $prizePoolUsdt,
        int $userTicketCount,
        string $timeRemaining
    ): void {
        try {
            $ticketText = $userTicketCount === 1 ? 'ticket' : 'tickets';
            
            $message = "
⏰ <b>Lottery Ending Soon!</b>

━━━━━━━━━━━━━━━━━━━━━

🎟️ <b>{$lotteryTitle}</b>
💰 <b>Prize Pool:</b> \${$prizePoolUsdt} USDT
⏳ <b>Time Left:</b> {$timeRemaining}

━━━━━━━━━━━━━━━━━━━━━

🎫 You have <b>{$userTicketCount} {$ticketText}</b> in this lottery.

🍀 Good luck! The draw will happen soon.

💡 <i>Want better odds? You can still buy more tickets before the draw!</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify lottery ending soon for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification when a new lottery is available.
     *
     * @param int $userId User's Telegram ID
     * @param string $lotteryTitle Lottery title
     * @param string $ticketPriceUsdt Ticket price in USDT
     * @param string $initialPrizePoolUsdt Initial prize pool in USDT
     * @param string $endDate End date/time
     */
    public static function notifyNewLotteryAvailable(
        int $userId,
        string $lotteryTitle,
        string $ticketPriceUsdt,
        string $initialPrizePoolUsdt,
        string $endDate
    ): void {
        try {
            $message = "
🎉 <b>New Lottery Available!</b>

━━━━━━━━━━━━━━━━━━━━━

🎟️ <b>{$lotteryTitle}</b>
💰 <b>Starting Prize Pool:</b> \${$initialPrizePoolUsdt} USDT
🎫 <b>Ticket Price:</b> \${$ticketPriceUsdt} USDT
📅 <b>Draw Date:</b> {$endDate}

━━━━━━━━━━━━━━━━━━━━━

🚀 Be an early participant and increase your chances!

💡 <i>The earlier you join, the better your odds!</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify new lottery for user {$userId}: " . $e->getMessage());
        }
    }

    // ==========================================
    // WALLET VERIFICATION NOTIFICATIONS
    // ==========================================

    /**
     * Send notification when wallet verification is approved.
     *
     * @param int $userId User's Telegram ID
     * @param string $walletAddress Verified wallet address
     * @param string|null $unlockedAmountUsdt Amount unlocked (if applicable)
     */
    public static function notifyVerificationApproved(
        int $userId,
        string $walletAddress,
        ?string $unlockedAmountUsdt = null
    ): void {
        try {
            $shortAddress = substr($walletAddress, 0, 8) . '...' . substr($walletAddress, -6);
            
            $unlockedInfo = '';
            if ($unlockedAmountUsdt !== null) {
                $unlockedInfo = "\n💰 <b>Unlocked Balance:</b> \${$unlockedAmountUsdt} USDT";
            }

            $message = "
✅ <b>Wallet Verification Approved!</b>

━━━━━━━━━━━━━━━━━━━━━

📍 <b>Wallet:</b> <code>{$shortAddress}</code>
🔓 <b>Status:</b> Verified{$unlockedInfo}

━━━━━━━━━━━━━━━━━━━━━

🎉 <b>Congratulations!</b> Your wallet is now verified.

You can now:
• Withdraw funds to your wallet
• Claim pending rewards
• Access all platform features

💡 <i>Your verification is valid for 90 days.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify verification approved for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification when wallet verification is rejected.
     *
     * @param int $userId User's Telegram ID
     * @param string $reason Rejection reason
     * @param string|null $walletAddress Wallet address (if available)
     */
    public static function notifyVerificationRejected(
        int $userId,
        string $reason,
        ?string $walletAddress = null
    ): void {
        try {
            $walletInfo = '';
            if ($walletAddress !== null) {
                $shortAddress = substr($walletAddress, 0, 8) . '...' . substr($walletAddress, -6);
                $walletInfo = "\n📍 <b>Wallet:</b> <code>{$shortAddress}</code>";
            }

            $message = "
❌ <b>Wallet Verification Rejected</b>

━━━━━━━━━━━━━━━━━━━━━{$walletInfo}

⚠️ <b>Reason:</b> {$reason}

━━━━━━━━━━━━━━━━━━━━━

Please review the requirements and try again:

1. Ensure you're using the correct wallet
2. Complete all verification steps
3. Contact support if you need assistance

💡 <i>You can start a new verification anytime.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify verification rejected for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send notification when wallet verification is about to expire.
     *
     * @param int $userId User's Telegram ID
     * @param string $walletAddress Wallet address
     * @param int $daysRemaining Days until expiration
     */
    public static function notifyVerificationExpiring(
        int $userId,
        string $walletAddress,
        int $daysRemaining
    ): void {
        try {
            $shortAddress = substr($walletAddress, 0, 8) . '...' . substr($walletAddress, -6);
            $dayText = $daysRemaining === 1 ? 'day' : 'days';

            $message = "
⚠️ <b>Verification Expiring Soon</b>

━━━━━━━━━━━━━━━━━━━━━

📍 <b>Wallet:</b> <code>{$shortAddress}</code>
⏳ <b>Expires in:</b> {$daysRemaining} {$dayText}

━━━━━━━━━━━━━━━━━━━━━

To maintain uninterrupted access to withdrawals and rewards, please renew your verification before it expires.

💡 <i>Re-verification is quick and easy!</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify verification expiring for user {$userId}: " . $e->getMessage());
        }
    }

    // ==========================================
    // SECURITY NOTIFICATIONS
    // ==========================================

    /**
     * Send security alert for large withdrawal attempt.
     *
     * @param int $userId User's Telegram ID
     * @param string $amountUsdt Withdrawal amount in USDT
     * @param string $network Blockchain network
     * @param string $address Destination address
     */
    public static function notifySecurityAlertLargeWithdrawal(
        int $userId,
        string $amountUsdt,
        string $network,
        string $address
    ): void {
        try {
            $shortAddress = substr($address, 0, 8) . '...' . substr($address, -6);
            $networkName = match (strtolower($network)) {
                'erc20' => 'Ethereum (ERC20)',
                'bep20' => 'BSC (BEP20)',
                'trc20' => 'Tron (TRC20)',
                default => strtoupper($network)
            };

            $message = "
🚨 <b>Security Alert: Large Withdrawal</b>

━━━━━━━━━━━━━━━━━━━━━

💰 <b>Amount:</b> \${$amountUsdt} USDT
🌐 <b>Network:</b> {$networkName}
📍 <b>To:</b> <code>{$shortAddress}</code>

━━━━━━━━━━━━━━━━━━━━━

⚠️ A large withdrawal was initiated from your account.

If this was you, no action is needed.

<b>If you didn't request this:</b>
1. Contact support immediately
2. Do not share any verification codes
3. Change your account security settings

🔒 <i>Your security is our priority.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to send large withdrawal security alert for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Send security alert for suspicious activity.
     *
     * @param int $userId User's Telegram ID
     * @param string $activityType Type of suspicious activity
     * @param string $details Activity details
     */
    public static function notifySecurityAlertSuspiciousActivity(
        int $userId,
        string $activityType,
        string $details
    ): void {
        try {
            $activityName = match ($activityType) {
                'multiple_failed_attempts' => 'Multiple Failed Attempts',
                'unusual_location' => 'Unusual Login Location',
                'rapid_transactions' => 'Rapid Transaction Pattern',
                'account_change' => 'Account Settings Changed',
                default => $activityType
            };

            $message = "
🔴 <b>Security Alert</b>

━━━━━━━━━━━━━━━━━━━━━

⚠️ <b>Alert Type:</b> {$activityName}
📋 <b>Details:</b> {$details}
🕐 <b>Time:</b> " . date('Y-m-d H:i:s') . " UTC

━━━━━━━━━━━━━━━━━━━━━

If this was you, no action is needed.

<b>If you don't recognize this activity:</b>
1. Review your recent account activity
2. Contact support immediately
3. Enable additional security measures

🔒 <i>We're here to help protect your account.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to send suspicious activity alert for user {$userId}: " . $e->getMessage());
        }
    }

    // ==========================================
    // WITHDRAWAL NOTIFICATIONS
    // ==========================================

    /**
     * Send notification when withdrawal is rejected.
     *
     * @param int $userId User's Telegram ID
     * @param string $amountUsdt Withdrawal amount in USDT
     * @param string $network Blockchain network
     * @param string $reason Rejection reason
     * @param int|null $withdrawalId Withdrawal ID
     */
    public static function notifyWithdrawalRejected(
        int $userId,
        string $amountUsdt,
        string $network,
        string $reason,
        ?int $withdrawalId = null
    ): void {
        try {
            $networkName = match (strtolower($network)) {
                'erc20' => 'Ethereum (ERC20)',
                'bep20' => 'BSC (BEP20)',
                'trc20' => 'Tron (TRC20)',
                default => strtoupper($network)
            };

            $refInfo = $withdrawalId ? "\n🆔 <b>Reference:</b> #{$withdrawalId}" : '';

            $message = "
❌ <b>Withdrawal Rejected</b>

━━━━━━━━━━━━━━━━━━━━━

💰 <b>Amount:</b> \${$amountUsdt} USDT
🌐 <b>Network:</b> {$networkName}{$refInfo}

━━━━━━━━━━━━━━━━━━━━━

⚠️ <b>Reason:</b> {$reason}

Your funds have been returned to your wallet balance.

💡 <i>Please review the issue and try again, or contact support for assistance.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify withdrawal rejected for user {$userId}: " . $e->getMessage());
        }
    }

    // ==========================================
    // AIRDROP NOTIFICATIONS
    // ==========================================

    /**
     * Send daily airdrop reminder.
     *
     * @param int $userId User's Telegram ID
     * @param string $pendingGhdBalance Unclaimed GHD balance
     * @param int $streakDays Current streak days
     */
    public static function notifyAirdropReminder(
        int $userId,
        string $pendingGhdBalance,
        int $streakDays
    ): void {
        try {
            $streakBonus = '';
            if ($streakDays > 0) {
                $streakBonus = "\n🔥 <b>Current Streak:</b> {$streakDays} days";
            }

            $message = "
⛏️ <b>Daily Airdrop Reminder</b>

━━━━━━━━━━━━━━━━━━━━━

🪙 <b>Pending GHD:</b> {$pendingGhdBalance} GHD{$streakBonus}

━━━━━━━━━━━━━━━━━━━━━

Don't forget to claim your daily airdrop tokens!

Tap to earn GHD tokens that can be converted to USDT.

💡 <i>Maintain your streak for bonus rewards!</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to send airdrop reminder for user {$userId}: " . $e->getMessage());
        }
    }

    // ==========================================
    // MILESTONE NOTIFICATIONS
    // ==========================================

    /**
     * Send notification for account milestone achieved.
     *
     * @param int $userId User's Telegram ID
     * @param string $milestoneType Type of milestone
     * @param array<string, mixed> $data Milestone data
     */
    public static function notifyMilestoneAchieved(
        int $userId,
        string $milestoneType,
        array $data = []
    ): void {
        try {
            $message = match ($milestoneType) {
                'first_deposit' => self::buildFirstDepositMilestone($data),
                'referrals_10' => self::buildReferralMilestone(10, $data),
                'referrals_50' => self::buildReferralMilestone(50, $data),
                'referrals_100' => self::buildReferralMilestone(100, $data),
                'first_lottery_win' => self::buildFirstLotteryWinMilestone($data),
                'total_earnings_100' => self::buildEarningsMilestone('100', $data),
                'total_earnings_1000' => self::buildEarningsMilestone('1,000', $data),
                default => null
            };

            if ($message === null) {
                return;
            }

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to notify milestone for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Build first deposit milestone message.
     */
    private static function buildFirstDepositMilestone(array $data): string
    {
        $amount = $data['amount'] ?? '0';
        return "
🎊 <b>Milestone Unlocked!</b>

━━━━━━━━━━━━━━━━━━━━━

🏆 <b>First Deposit Complete</b>

💰 You deposited <b>\${$amount} USDT</b>

━━━━━━━━━━━━━━━━━━━━━

Welcome to the Ghidar community! 

You now have access to:
• 🎟️ Lottery tickets
• 🤖 AI Trader
• 💰 Referral rewards

Start your earning journey now!
";
    }

    /**
     * Build referral milestone message.
     */
    private static function buildReferralMilestone(int $count, array $data): string
    {
        $totalRewards = $data['total_rewards'] ?? '0';
        return "
🎊 <b>Referral Milestone!</b>

━━━━━━━━━━━━━━━━━━━━━

🏆 <b>{$count} Referrals Reached!</b>

👥 Total Referrals: {$count}
💰 Total Rewards: \${$totalRewards} USDT

━━━━━━━━━━━━━━━━━━━━━

Amazing work! You're building a great network.

Keep sharing your referral link to earn even more!
";
    }

    /**
     * Build first lottery win milestone message.
     */
    private static function buildFirstLotteryWinMilestone(array $data): string
    {
        $prize = $data['prize'] ?? '0';
        return "
🎊 <b>First Lottery Win!</b>

━━━━━━━━━━━━━━━━━━━━━

🏆 <b>Congratulations!</b>

🎉 You won your first lottery prize!
💰 Prize: \${$prize} USDT

━━━━━━━━━━━━━━━━━━━━━

Your luck is shining! Keep participating for more wins!
";
    }

    /**
     * Build earnings milestone message.
     */
    private static function buildEarningsMilestone(string $amount, array $data): string
    {
        return "
🎊 <b>Earnings Milestone!</b>

━━━━━━━━━━━━━━━━━━━━━

🏆 <b>\${$amount}+ USDT Earned!</b>

You've reached a significant earnings milestone on Ghidar!

━━━━━━━━━━━━━━━━━━━━━

Keep up the great work! Your dedication is paying off.

💡 <i>Explore more ways to earn with AI Trader and referrals!</i>
";
    }

    // ==========================================
    // INACTIVE USER NOTIFICATIONS
    // ==========================================

    /**
     * Send reminder to inactive user.
     *
     * @param int $userId User's Telegram ID
     * @param int $inactiveDays Number of days inactive
     * @param array<string, mixed> $accountSummary Summary of user's account
     */
    public static function notifyInactiveUserReminder(
        int $userId,
        int $inactiveDays,
        array $accountSummary = []
    ): void {
        try {
            $balance = $accountSummary['usdt_balance'] ?? '0';
            $ghdBalance = $accountSummary['ghd_balance'] ?? '0';
            
            $balanceInfo = '';
            if ((float)$balance > 0 || (float)$ghdBalance > 0) {
                $balanceInfo = "\n\n💼 <b>Your Account:</b>";
                if ((float)$balance > 0) {
                    $balanceInfo .= "\n💵 USDT Balance: \${$balance}";
                }
                if ((float)$ghdBalance > 0) {
                    $balanceInfo .= "\n🪙 GHD Balance: {$ghdBalance}";
                }
            }

            $message = "
👋 <b>We Miss You!</b>

━━━━━━━━━━━━━━━━━━━━━

It's been {$inactiveDays} days since your last visit.{$balanceInfo}

━━━━━━━━━━━━━━━━━━━━━

Here's what you might be missing:

🎟️ <b>Lottery</b> - New lotteries with big prizes
⛏️ <b>Airdrop</b> - Daily GHD tokens waiting
🤖 <b>AI Trader</b> - Growing profits automatically
👥 <b>Referrals</b> - Earn from your network

Come back and continue your earning journey!

💡 <i>Your balance is safe and waiting for you.</i>
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to send inactive reminder for user {$userId}: " . $e->getMessage());
        }
    }

    // ==========================================
    // WELCOME NOTIFICATIONS
    // ==========================================

    /**
     * Send enhanced welcome notification to new user.
     *
     * @param int $userId User's Telegram ID
     * @param string $firstName User's first name
     * @param bool $isPremium Whether user has Telegram Premium
     */
    public static function notifyWelcomeNewUser(
        int $userId,
        string $firstName,
        bool $isPremium = false
    ): void {
        try {
            $premiumBonus = '';
            if ($isPremium) {
                $premiumBonus = "\n\n⭐ <b>Premium Bonus:</b> As a Telegram Premium user, you get extra rewards!";
            }

            $message = "
🎉 <b>Welcome to Ghidar, {$firstName}!</b>

━━━━━━━━━━━━━━━━━━━━━

Your crypto journey starts here! Here's what you can do:{$premiumBonus}

━━━━━━━━━━━━━━━━━━━━━

<b>🚀 Quick Start Guide:</b>

1️⃣ <b>Airdrop</b> - Tap to earn free GHD tokens daily
2️⃣ <b>Lottery</b> - Buy tickets for a chance to win USDT
3️⃣ <b>AI Trader</b> - Let AI grow your investment
4️⃣ <b>Referrals</b> - Invite friends, earn USDT commissions

<b>💡 Pro Tips:</b>
• Check in daily for streak bonuses
• More tickets = better lottery odds
• Your referral earnings are automatic

Let's start earning! 🚀
";

            self::getBot()->sendMessage($userId, trim($message), [
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            error_log("NotificationService: Failed to send welcome notification for user {$userId}: " . $e->getMessage());
        }
    }
}

