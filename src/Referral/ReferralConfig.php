<?php

declare(strict_types=1);

namespace Ghidar\Referral;

/**
 * Configuration for referral system.
 * Defines commission percentages, levels, and minimum rewards.
 */
class ReferralConfig
{
    /**
     * Maximum referral level supported.
     */
    public const MAX_LEVEL = 2;

    /**
     * Commission percentages per product type and level.
     * Level 1 = direct referral (L1), Level 2 = indirect referral (L2).
     * Values are strings for bcmath usage.
     */
    public const COMMISSIONS = [
        // When a referred user makes a wallet deposit
        'wallet_deposit' => [
            1 => '0.05', // 5% to L1
            2 => '0.02', // 2% to L2
        ],
        // When a referred user deposits into AI Trader
        'ai_trader_deposit' => [
            1 => '0.07', // 7% to L1
            2 => '0.03', // 3% to L2
        ],
        // When a referred user purchases lottery tickets
        'lottery_purchase' => [
            1 => '0.03', // 3% to L1
            2 => '0.01', // 1% to L2
        ],
    ];

    /**
     * Minimum reward amount in USDT to avoid tiny dust rewards.
     */
    public const MIN_REWARD_USDT = '0.01';

    /**
     * Get commission percentage for a given source type and level.
     *
     * @param string $sourceType Source type (wallet_deposit, ai_trader_deposit, lottery_purchase)
     * @param int $level Referral level (1 or 2)
     * @return string|null Commission percentage as string, or null if not configured
     */
    public static function getCommission(string $sourceType, int $level): ?string
    {
        if (!isset(self::COMMISSIONS[$sourceType])) {
            return null;
        }

        if (!isset(self::COMMISSIONS[$sourceType][$level])) {
            return null;
        }

        return self::COMMISSIONS[$sourceType][$level];
    }

    /**
     * Check if a source type has referral commissions configured.
     *
     * @param string $sourceType Source type
     * @return bool True if commissions are configured
     */
    public static function hasCommissions(string $sourceType): bool
    {
        return isset(self::COMMISSIONS[$sourceType]);
    }
}

