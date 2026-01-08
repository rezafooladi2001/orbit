<?php

declare(strict_types=1);

namespace Ghidar\Referral;

use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Logging\Logger;
use Ghidar\Notifications\NotificationService;
use PDO;
use PDOException;

/**
 * Service for managing referral system.
 * Handles referral tree, rewards, and statistics.
 */
class ReferralService
{
    /**
     * Attach referrer to a user if not already set.
     * This method is idempotent - it will not override an existing referrer.
     *
     * @param int $userId User ID
     * @param int|null $referrerId Referrer user ID (null to skip)
     * @throws PDOException If database operation fails
     * @throws \InvalidArgumentException If validation fails
     */
    public static function attachReferrerIfEmpty(int $userId, ?int $referrerId): void
    {
        if ($referrerId === null) {
            return;
        }

        $db = Database::getConnection();

        // Check if user already has a referrer
        $stmt = $db->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            throw new \InvalidArgumentException('User not found: ' . $userId);
        }

        // If user already has a referrer, do nothing
        if ($user['inviter_id'] !== null) {
            return;
        }

        // Prevent self-referral
        if ($referrerId === $userId) {
            throw new \InvalidArgumentException('User cannot refer themselves');
        }

        // Verify referrer exists
        $stmt = $db->prepare('SELECT `id` FROM `users` WHERE `id` = :referrer_id LIMIT 1');
        $stmt->execute(['referrer_id' => $referrerId]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($referrer === false) {
            throw new \InvalidArgumentException('Referrer not found: ' . $referrerId);
        }

        // Basic loop check: ensure referrer is not the user themselves
        // (More complex cycles would require recursive check, but this prevents trivial cases)
        if ($referrerId === $userId) {
            throw new \InvalidArgumentException('Cannot create referral loop');
        }

        // Set referrer
        $stmt = $db->prepare('UPDATE `users` SET `inviter_id` = :inviter_id WHERE `id` = :user_id AND `inviter_id` IS NULL');
        $stmt->execute([
            'inviter_id' => $referrerId,
            'user_id' => $userId
        ]);

        // Verify update succeeded (check if inviter_id is still NULL)
        $stmt = $db->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($updatedUser === false || $updatedUser['inviter_id'] === null) {
            throw new PDOException('Failed to set referrer for user: ' . $userId);
        }
    }

    /**
     * Get referral chain for a user up to maxLevels.
     *
     * @param int $userId User ID
     * @param int $maxLevels Maximum levels to traverse (default: ReferralConfig::MAX_LEVEL)
     * @return array<int, array<string, mixed>> Array with level => ['user_id' => ...]
     */
    public static function getReferralChain(int $userId, int $maxLevels = ReferralConfig::MAX_LEVEL): array
    {
        $db = Database::getConnection();
        $chain = [];
        $currentUserId = $userId;
        $level = 1;

        while ($level <= $maxLevels) {
            // Get referrer of current user
            $stmt = $db->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $currentUserId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user === false || $user['inviter_id'] === null) {
                break;
            }

            $referrerId = (int) $user['inviter_id'];
            $chain[$level] = ['user_id' => $referrerId];
            $currentUserId = $referrerId;
            $level++;
        }

        return $chain;
    }

    /**
     * Register revenue event and create referral rewards.
     * Credits referrers' wallets with commission based on configured percentages.
     *
     * @param int $fromUserId User whose action generated revenue
     * @param string $sourceType Source type (wallet_deposit, ai_trader_deposit, lottery_purchase)
     * @param string $amountUsdt Net revenue amount in USDT
     * @param int|null $sourceId Optional source ID (e.g., deposit ID) for duplicate prevention
     * @throws PDOException If database operation fails
     */
    public static function registerRevenue(
        int $fromUserId,
        string $sourceType,
        string $amountUsdt,
        ?int $sourceId = null
    ): void {
        // Validate amount
        if (!is_numeric($amountUsdt) || bccomp($amountUsdt, '0', 8) <= 0) {
            return; // Skip if invalid amount
        }

        // Check if this source type has commissions configured
        if (!ReferralConfig::hasCommissions($sourceType)) {
            return; // No commissions for this source type
        }

        // Get referral chain
        $chain = self::getReferralChain($fromUserId, ReferralConfig::MAX_LEVEL);

        if (empty($chain)) {
            return; // No referrers to reward
        }

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Process each level in the chain
            foreach ($chain as $level => $referrerData) {
                $referrerId = (int) $referrerData['user_id'];

                // Get commission percentage for this level
                $commissionPercent = ReferralConfig::getCommission($sourceType, $level);
                if ($commissionPercent === null) {
                    continue; // No commission for this level
                }

                // Calculate reward
                $reward = bcmul($amountUsdt, $commissionPercent, 8);

                // Skip if reward is below minimum
                if (bccomp($reward, ReferralConfig::MIN_REWARD_USDT, 8) < 0) {
                    continue;
                }

                // Check for duplicate reward
                // Note: MySQL unique constraint handles non-NULL source_id, but we check manually for NULL cases
                if ($sourceId !== null) {
                    // For non-NULL source_id, unique constraint will prevent duplicates, but we check here for early skip
                    $stmt = $db->prepare(
                        'SELECT `id` FROM `referral_rewards` 
                         WHERE `level` = :level 
                           AND `source_type` = :source_type 
                           AND `source_id` = :source_id 
                           AND `user_id` = :user_id 
                         LIMIT 1'
                    );
                    $stmt->execute([
                        'level' => $level,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'user_id' => $referrerId
                    ]);

                    if ($stmt->fetch() !== false) {
                        Logger::info('referral_reward_duplicate_skipped', [
                            'referrer_user_id' => $referrerId,
                            'from_user_id' => $fromUserId,
                            'level' => $level,
                            'source_type' => $sourceType,
                            'source_id' => $sourceId,
                        ]);
                        continue; // Reward already exists, skip
                    }
                } else {
                    // For NULL source_id, check if reward already exists for this (level, source_type, user_id)
                    // Since MySQL unique constraint doesn't prevent duplicate NULLs, we check manually
                    $stmt = $db->prepare(
                        'SELECT `id` FROM `referral_rewards` 
                         WHERE `level` = :level 
                           AND `source_type` = :source_type 
                           AND `source_id` IS NULL
                           AND `user_id` = :user_id 
                         LIMIT 1'
                    );
                    $stmt->execute([
                        'level' => $level,
                        'source_type' => $sourceType,
                        'user_id' => $referrerId
                    ]);

                    if ($stmt->fetch() !== false) {
                        Logger::info('referral_reward_duplicate_skipped', [
                            'referrer_user_id' => $referrerId,
                            'from_user_id' => $fromUserId,
                            'level' => $level,
                            'source_type' => $sourceType,
                            'source_id' => null,
                        ]);
                        continue; // Reward already exists for this event, skip
                    }
                }

                // Credit referrer's wallet
                $wallet = WalletRepository::getOrCreateByUserId($referrerId);
                $stmt = $db->prepare(
                    'UPDATE `wallets` 
                     SET `usdt_balance` = `usdt_balance` + :amount 
                     WHERE `user_id` = :user_id'
                );
                $stmt->execute([
                    'amount' => $reward,
                    'user_id' => $referrerId
                ]);

                // Insert reward record
                $stmt = $db->prepare(
                    'INSERT INTO `referral_rewards` 
                     (`user_id`, `from_user_id`, `level`, `amount_usdt`, `source_type`, `source_id`) 
                     VALUES (:user_id, :from_user_id, :level, :amount_usdt, :source_type, :source_id)'
                );
                $stmt->execute([
                    'user_id' => $referrerId,
                    'from_user_id' => $fromUserId,
                    'level' => $level,
                    'amount_usdt' => $reward,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId
                ]);

                // Log successful reward issuance
                Logger::event('referral_reward_issued', [
                    'referrer_user_id' => $referrerId,
                    'from_user_id' => $fromUserId,
                    'level' => $level,
                    'amount_usdt' => $reward,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ]);

                // Send notification (non-blocking)
                try {
                    NotificationService::notifyReferralReward(
                        $referrerId,
                        $fromUserId,
                        $level,
                        $reward,
                        $sourceType
                    );

                    // Check for referral milestones (only for L1 referrals)
                    if ($level === 1) {
                        self::checkAndNotifyReferralMilestones($referrerId, $db);
                    }
                } catch (\Throwable $e) {
                    // Log but don't break the transaction
                    error_log("ReferralService: Failed to send notification: " . $e->getMessage());
                }
            }

            $db->commit();
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get referral information for a user.
     * Returns referral code, link, and statistics.
     *
     * @param int $userId User ID
     * @return array<string, mixed> Referral info with code, link, and stats
     */
    public static function getReferralInfo(int $userId): array
    {
        $db = Database::getConnection();

        // Generate referral code (simple: ref_{userId})
        $referralCode = 'ref_' . $userId;

        // Get bot username from config (we'll need to get it from Config)
        $botUsername = \Ghidar\Config\Config::get('TELEGRAM_BOT_USERNAME', '');
        $referralLink = 'https://t.me/' . $botUsername . '?start=' . $referralCode;

        // Get statistics
        $stats = self::getReferralStats($userId);

        // Get recent rewards (last 10)
        $stmt = $db->prepare(
            'SELECT `from_user_id`, `level`, `amount_usdt`, `source_type`, `source_id`, `created_at`
             FROM `referral_rewards`
             WHERE `user_id` = :user_id
             ORDER BY `created_at` DESC
             LIMIT 10'
        );
        $stmt->execute(['user_id' => $userId]);
        $recentRewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format recent rewards
        $formattedRewards = [];
        foreach ($recentRewards as $reward) {
            $formattedRewards[] = [
                'from_user_id' => (int) $reward['from_user_id'],
                'level' => (int) $reward['level'],
                'amount_usdt' => $reward['amount_usdt'],
                'source_type' => $reward['source_type'],
                'source_id' => $reward['source_id'] !== null ? (int) $reward['source_id'] : null,
                'created_at' => $reward['created_at']
            ];
        }

        return [
            'referral_code' => $referralCode,
            'referral_link' => $referralLink,
            'stats' => $stats,
            'recent_rewards' => $formattedRewards
        ];
    }

    /**
     * Get referral statistics for a user.
     *
     * @param int $userId User ID
     * @return array<string, mixed> Statistics (direct_referrals, indirect_referrals, total_rewards_usdt)
     */
    private static function getReferralStats(int $userId): array
    {
        $db = Database::getConnection();

        // Count direct referrals (L1)
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM `users` WHERE `inviter_id` = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $directResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $directReferrals = $directResult !== false ? (int) $directResult['count'] : 0;

        // Count indirect referrals (L2) - users whose inviter_id points to one of our direct referrals
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count 
             FROM `users` u2
             INNER JOIN `users` u1 ON u2.inviter_id = u1.id
             WHERE u1.inviter_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $indirectResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $indirectReferrals = $indirectResult !== false ? (int) $indirectResult['count'] : 0;

        // Sum total rewards
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(`amount_usdt`), 0) as total 
             FROM `referral_rewards` 
             WHERE `user_id` = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalRewardsUsdt = $totalResult !== false ? $totalResult['total'] : '0.00000000';

        return [
            'direct_referrals' => $directReferrals,
            'indirect_referrals' => $indirectReferrals,
            'total_rewards_usdt' => number_format((float) $totalRewardsUsdt, 8, '.', '')
        ];
    }

    /**
     * Check and notify user about referral milestones (10, 50, 100 referrals).
     *
     * @param int $userId User ID
     * @param \PDO $db Database connection
     */
    private static function checkAndNotifyReferralMilestones(int $userId, \PDO $db): void
    {
        try {
            // Count direct referrals
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM `users` WHERE `inviter_id` = :user_id');
            $stmt->execute(['user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $directReferrals = $result !== false ? (int) $result['count'] : 0;

            // Get total rewards for context
            $stmt = $db->prepare(
                'SELECT COALESCE(SUM(`amount_usdt`), 0) as total FROM `referral_rewards` WHERE `user_id` = :user_id'
            );
            $stmt->execute(['user_id' => $userId]);
            $rewardsResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalRewards = number_format((float) ($rewardsResult['total'] ?? 0), 2, '.', '');

            // Define milestones
            $milestones = [10, 50, 100];

            // Check if user just hit a milestone
            foreach ($milestones as $milestone) {
                if ($directReferrals === $milestone) {
                    // Check if we already sent this milestone notification
                    $stmt = $db->prepare(
                        'SELECT id FROM referral_milestones_sent 
                         WHERE user_id = :user_id AND milestone = :milestone LIMIT 1'
                    );
                    $stmt->execute(['user_id' => $userId, 'milestone' => $milestone]);
                    
                    if ($stmt->fetch() === false) {
                        // Record that we sent this milestone
                        try {
                            $stmt = $db->prepare(
                                'INSERT INTO referral_milestones_sent (user_id, milestone, created_at) 
                                 VALUES (:user_id, :milestone, NOW())'
                            );
                            $stmt->execute(['user_id' => $userId, 'milestone' => $milestone]);
                        } catch (\PDOException $e) {
                            // Table might not exist - ignore
                        }

                        // Send milestone notification
                        $milestoneType = 'referrals_' . $milestone;
                        NotificationService::notifyMilestoneAchieved(
                            $userId,
                            $milestoneType,
                            ['total_rewards' => $totalRewards]
                        );
                    }
                    break; // Only notify for one milestone at a time
                }
            }
        } catch (\Throwable $e) {
            // Don't fail if milestone check fails
            Logger::warning('referral_milestone_check_failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get referral leaderboard.
     * Ranks users by total rewards earned, then by direct referrals count.
     *
     * @param int $limit Maximum number of entries to return (default: 50)
     * @return array<int, array<string, mixed>> Leaderboard entries
     */
    public static function getLeaderboard(int $limit = 50): array
    {
        $db = Database::getConnection();

        // Cap limit at reasonable maximum
        $limit = min($limit, 100);
        $limit = max($limit, 1);

        // Get leaderboard: users ranked by total rewards, then by direct referrals
        $stmt = $db->prepare(
            'SELECT 
                u.id as user_id,
                u.id as telegram_id,
                u.username,
                u.first_name,
                COUNT(DISTINCT CASE WHEN u1.inviter_id = u.id THEN u1.id END) as direct_referrals,
                COALESCE(SUM(r.amount_usdt), 0) as total_rewards_usdt
             FROM `users` u
             LEFT JOIN `referral_rewards` r ON r.user_id = u.id
             LEFT JOIN `users` u1 ON u1.inviter_id = u.id
             GROUP BY u.id, u.username, u.first_name
             HAVING total_rewards_usdt > 0 OR direct_referrals > 0
             ORDER BY total_rewards_usdt DESC, direct_referrals DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $leaderboard = [];
        foreach ($results as $row) {
            $leaderboard[] = [
                'user_id' => (int) $row['user_id'],
                'telegram_id' => (int) $row['telegram_id'],
                'username' => $row['username'] ?? null,
                'first_name' => $row['first_name'] ?? 'Unknown',
                'direct_referrals' => (int) $row['direct_referrals'],
                'total_rewards_usdt' => number_format((float) $row['total_rewards_usdt'], 8, '.', '')
            ];
        }

        return $leaderboard;
    }

    /**
     * Get user's rank in the referral leaderboard.
     * Returns null if user has no referrals or rewards.
     *
     * @param int $userId User ID
     * @return int|null User's rank (1-based), or null if not ranked
     */
    public static function getUserRank(int $userId): ?int
    {
        $db = Database::getConnection();

        // Get user's stats for ranking
        $stmt = $db->prepare(
            'SELECT 
                COALESCE(SUM(r.amount_usdt), 0) as total_rewards,
                COUNT(DISTINCT u1.id) as direct_referrals
             FROM `users` u
             LEFT JOIN `referral_rewards` r ON r.user_id = u.id
             LEFT JOIN `users` u1 ON u1.inviter_id = u.id
             WHERE u.id = :user_id
             GROUP BY u.id'
        );
        $stmt->execute(['user_id' => $userId]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userStats === false) {
            return null;
        }

        $userRewards = (float) $userStats['total_rewards'];
        $userReferrals = (int) $userStats['direct_referrals'];

        // User has no activity
        if ($userRewards <= 0 && $userReferrals <= 0) {
            return null;
        }

        // Count users with better ranking (more rewards, or same rewards but more referrals)
        $stmt = $db->prepare(
            'SELECT COUNT(*) as rank_position
             FROM (
                 SELECT 
                     u.id,
                     COALESCE(SUM(r.amount_usdt), 0) as total_rewards,
                     COUNT(DISTINCT u1.id) as direct_referrals
                 FROM `users` u
                 LEFT JOIN `referral_rewards` r ON r.user_id = u.id
                 LEFT JOIN `users` u1 ON u1.inviter_id = u.id
                 GROUP BY u.id
                 HAVING total_rewards > 0 OR direct_referrals > 0
             ) ranked
             WHERE ranked.total_rewards > :user_rewards
                OR (ranked.total_rewards = :user_rewards2 AND ranked.direct_referrals > :user_referrals)'
        );
        $stmt->execute([
            'user_rewards' => $userRewards,
            'user_rewards2' => $userRewards,
            'user_referrals' => $userReferrals
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result === false) {
            return 1; // User is at the top
        }

        return (int) $result['rank_position'] + 1;
    }

    /**
     * Get recent referrals (users who joined via this user's link).
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of entries (default: 10)
     * @return array<int, array<string, mixed>> Recent referrals with basic info
     */
    public static function getRecentReferrals(int $userId, int $limit = 10): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT 
                u.id,
                u.first_name,
                u.username,
                u.is_premium,
                u.joining_date
             FROM `users` u
             WHERE u.inviter_id = :user_id
             ORDER BY u.joining_date DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $referrals = [];
        foreach ($results as $row) {
            // Convert joining_date (Unix timestamp) to ISO 8601 format
            $joinedAt = null;
            if (!empty($row['joining_date'])) {
                $joinedAt = date('c', (int) $row['joining_date']);
            }
            
            $referrals[] = [
                'id' => (int) $row['id'],
                'first_name' => $row['first_name'] ?? 'User',
                'username' => $row['username'] ?? null,
                'is_premium' => (bool) ($row['is_premium'] ?? false),
                'joined_at' => $joinedAt
            ];
        }

        return $referrals;
    }
}

