<?php

declare(strict_types=1);

namespace Ghidar\Lottery;

/**
 * Configuration constants for Lottery system.
 * Defines ticket prices, limits, and status values for the Lottery module.
 */
final class LotteryConfig
{
    /**
     * Default ticket price in USDT (1 USDT per ticket)
     */
    public const DEFAULT_TICKET_PRICE_USDT = '1.00000000';

    /**
     * Maximum number of tickets a user can purchase in a single order
     */
    public const MAX_TICKETS_PER_ORDER = 1000;

    /**
     * Maximum number of tickets a user can own per lottery
     */
    public const MAX_TICKETS_PER_USER_PER_LOTTERY = 10000;

    /**
     * Lottery status: Upcoming (scheduled but not yet active)
     */
    public const STATUS_UPCOMING = 'upcoming';

    /**
     * Lottery status: Active (currently accepting ticket purchases)
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * Lottery status: Drawing (in process of selecting winners)
     */
    public const STATUS_DRAWING = 'drawing';

    /**
     * Lottery status: Finished (completed, winners selected)
     */
    public const STATUS_FINISHED = 'finished';

    /**
     * Default number of winners per lottery (currently 1)
     */
    public const DEFAULT_WINNERS_COUNT = 1;

    /**
     * Participation reward percentage of ticket price (0.01 = 1%)
     * All participants receive this reward as a participation bonus
     */
    public const PARTICIPATION_REWARD_PERCENTAGE = 0.01;

    /**
     * Minimum participation reward in USDT (if calculated reward is below this, use this minimum)
     */
    public const MIN_PARTICIPATION_REWARD_USDT = '0.10';
}

