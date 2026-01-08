<?php

declare(strict_types=1);

namespace Ghidar\Airdrop;

/**
 * Configuration constants for GHD (Ghidar) token and Airdrop system.
 * Defines conversion rates, limits, and constraints for the Airdrop module.
 */
final class GhdConfig
{
    /**
     * Conversion rate: 1000 GHD = 1 USDT
     */
    public const GHD_PER_USDT = 1000;

    /**
     * Maximum number of taps allowed per request to prevent abuse
     */
    public const MAX_TAPS_PER_REQUEST = 1000;

    /**
     * GHD earned per tap
     */
    public const GHD_PER_TAP = 1;

    /**
     * Minimum GHD amount required to convert to USDT
     * This prevents micro-transactions and ensures meaningful conversions
     */
    public const MIN_GHD_CONVERT = 1000;
}

