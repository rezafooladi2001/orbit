<?php

declare(strict_types=1);

namespace Ghidar\Payments;

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Logging\Logger;
use PDO;
use PDOException;

/**
 * Service for managing blockchain deposit addresses.
 * Returns the platform's master deposit addresses for each network.
 */
class BlockchainAddressService
{
    /**
     * Master deposit addresses for each network.
     * All deposits go to these addresses and are tracked by the deposit system.
     */
    private const MASTER_ADDRESSES = [
        'erc20' => '0x29841Ffa59A2831997A80840c76Ce94725E4ee5C',
        'bep20' => '0x29841Ffa59A2831997A80840c76Ce94725E4ee5C',
        'trc20' => 'TNVnn7g2DgZTz4hiS2LdFWB8PJWvxqwmpn',
    ];

    /**
     * Get the deposit address for a user/network/purpose combination.
     * For simplicity, returns the master address for the network.
     * The deposit is tracked in the database with unique deposit_id.
     *
     * @param int $userId User ID (Telegram user ID)
     * @param string $network Network identifier ('erc20', 'bep20', 'trc20')
     * @param string $purpose Purpose identifier ('wallet_topup', 'lottery', 'ai_trader')
     * @return string Deposit address
     * @throws \RuntimeException If network is invalid
     */
    public static function getOrCreateAddress(int $userId, string $network, string $purpose): string
    {
        // Normalize network to lowercase
        $network = strtolower($network);

        // Validate network
        if (!in_array($network, PaymentsConfig::SUPPORTED_NETWORKS, true)) {
            throw new \RuntimeException('Invalid network: ' . $network);
        }

        // Check if we have a custom address configured via environment
        $envKey = 'DEPOSIT_ADDRESS_' . strtoupper($network);
        $customAddress = Config::get($envKey);
        
        if (!empty($customAddress)) {
            return $customAddress;
        }

        // Return the master address for this network
        if (!isset(self::MASTER_ADDRESSES[$network])) {
            throw new \RuntimeException('No deposit address configured for network: ' . $network);
        }

        $address = self::MASTER_ADDRESSES[$network];

        // Log the address retrieval for tracking
        Logger::info('Deposit address retrieved', [
            'user_id' => $userId,
            'network' => $network,
            'purpose' => $purpose,
            'address' => $address
        ]);

        return $address;
    }

    /**
     * Get the master address for a network (public accessor).
     *
     * @param string $network Network identifier
     * @return string|null Address or null if not found
     */
    public static function getMasterAddress(string $network): ?string
    {
        $network = strtolower($network);
        return self::MASTER_ADDRESSES[$network] ?? null;
    }

    /**
     * Get all master addresses.
     *
     * @return array<string, string>
     */
    public static function getAllMasterAddresses(): array
    {
        return self::MASTER_ADDRESSES;
    }
}
