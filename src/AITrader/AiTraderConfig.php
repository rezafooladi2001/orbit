<?php

declare(strict_types=1);

namespace Ghidar\AITrader;

/**
 * Configuration constants for AI Trader module.
 * Defines limits, constraints, and defaults for AI Trader operations.
 */
final class AiTraderConfig
{
    /**
     * Minimum internal deposit amount in USDT
     */
    public const MIN_DEPOSIT_USDT = '100.00000000';

    /**
     * Minimum internal withdrawal amount in USDT
     */
    public const MIN_WITHDRAW_USDT = '1.00000000';

    /**
     * Maximum deposit amount per single operation in USDT
     */
    public const MAX_DEPOSIT_USDT = '100000.00000000';

    /**
     * Maximum withdrawal amount per single operation in USDT
     */
    public const MAX_WITHDRAW_USDT = '100000.00000000';

    /**
     * Default limit for history queries
     */
    public const HISTORY_LIMIT_DEFAULT = 50;

    /**
     * Maximum limit for history queries
     */
    public const HISTORY_LIMIT_MAX = 200;
}

