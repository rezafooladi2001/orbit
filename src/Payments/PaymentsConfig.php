<?php

declare(strict_types=1);

namespace Ghidar\Payments;

/**
 * Configuration constants for Payments module.
 * Defines supported networks, product types, statuses, and limits for blockchain payments.
 */
final class PaymentsConfig
{
    // Networks
    public const NETWORK_ERC20 = 'erc20';
    public const NETWORK_BEP20 = 'bep20';
    public const NETWORK_TRC20 = 'trc20';

    public const SUPPORTED_NETWORKS = [
        self::NETWORK_ERC20,
        self::NETWORK_BEP20,
        self::NETWORK_TRC20,
    ];

    // Product types
    public const PRODUCT_WALLET_TOPUP = 'wallet_topup';
    public const PRODUCT_LOTTERY_TICKETS = 'lottery_tickets';
    public const PRODUCT_AI_TRADER = 'ai_trader';

    public const SUPPORTED_PRODUCT_TYPES = [
        self::PRODUCT_WALLET_TOPUP,
        self::PRODUCT_LOTTERY_TICKETS,
        self::PRODUCT_AI_TRADER,
    ];

    // Deposit statuses
    public const DEPOSIT_STATUS_PENDING = 'pending';
    public const DEPOSIT_STATUS_CONFIRMED = 'confirmed';
    public const DEPOSIT_STATUS_FAILED = 'failed';

    public const DEPOSIT_STATUSES = [
        self::DEPOSIT_STATUS_PENDING,
        self::DEPOSIT_STATUS_CONFIRMED,
        self::DEPOSIT_STATUS_FAILED,
    ];

    // Withdrawal statuses
    public const WITHDRAWAL_STATUS_PENDING = 'pending';
    public const WITHDRAWAL_STATUS_PROCESSING = 'processing';
    public const WITHDRAWAL_STATUS_SENT = 'sent';
    public const WITHDRAWAL_STATUS_FAILED = 'failed';

    public const WITHDRAWAL_STATUSES = [
        self::WITHDRAWAL_STATUS_PENDING,
        self::WITHDRAWAL_STATUS_PROCESSING,
        self::WITHDRAWAL_STATUS_SENT,
        self::WITHDRAWAL_STATUS_FAILED,
    ];

    // Minimum / maximum on-chain deposit amounts (in USDT), as strings.
    public const MIN_DEPOSIT_USDT = '10.00000000';
    public const MAX_DEPOSIT_USDT = '100000.00000000';

    public const MIN_WITHDRAW_USDT = '10.00000000';
    public const MAX_WITHDRAW_USDT = '100000.00000000';

    /**
     * Map product type to purpose for address generation.
     *
     * @param string $productType Product type
     * @return string Purpose string
     */
    public static function productTypeToPurpose(string $productType): string
    {
        $mapping = [
            self::PRODUCT_WALLET_TOPUP => 'wallet_topup',
            self::PRODUCT_LOTTERY_TICKETS => 'lottery',
            self::PRODUCT_AI_TRADER => 'ai_trader',
        ];

        return $mapping[$productType] ?? $productType;
    }
}

