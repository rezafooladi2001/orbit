<?php

declare(strict_types=1);

/**
 * Cron Job: Check Pending Deposits
 * 
 * This script monitors the blockchain for incoming USDT deposits and automatically
 * confirms them when detected. Run every 1-5 minutes via cron.
 * 
 * Usage: php check_pending_deposits.php
 * Cron:  Every 5 min - see crontab
 */

require_once __DIR__ . '/../bootstrap.php';

use Ghidar\Config\Config;
use Ghidar\Core\Database;
use Ghidar\Payments\DepositService;
use Ghidar\Payments\PaymentsConfig;
use Ghidar\Logging\Logger;

/**
 * Blockchain API Configuration
 * 
 * Get free API keys from:
 * - ERC20: https://etherscan.io/apis
 * - BEP20: https://bscscan.com/apis
 * - TRC20: https://www.trongrid.io/ (free tier available)
 */
class BlockchainMonitor
{
    private const USDT_CONTRACTS = [
        'erc20' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', // Ethereum USDT
        'bep20' => '0x55d398326f99059fF775485246999027B3197955', // BSC USDT
        'trc20' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', // Tron USDT
    ];

    private const DEPOSIT_ADDRESSES = [
        'erc20' => '0x29841Ffa59A2831997A80840c76Ce94725E4ee5C',
        'bep20' => '0x29841Ffa59A2831997A80840c76Ce94725E4ee5C',
        'trc20' => 'TNVnn7g2DgZTz4hiS2LdFWB8PJVvxqwmpn',
    ];

    private \PDO $db;
    private array $apiKeys;

    public function __construct()
    {
        $this->db = Database::getConnection();
        
        // Load API keys from environment or use provided defaults
        $this->apiKeys = [
            'etherscan' => Config::get('ETHERSCAN_API_KEY') ?: '15F6JQWRPJFUQ6X9NN5B2ABZK59WC1Q8VB',
            'bscscan' => Config::get('BSCSCAN_API_KEY') ?: '15F6JQWRPJFUQ6X9NN5B2ABZK59WC1Q8VB',
            'trongrid' => Config::get('TRONGRID_API_KEY') ?: 'e50c224e-99f5-4246-a0f9-491be0182aa2',
        ];
        
        echo "API Keys loaded:\n";
        echo "  - Etherscan: " . (strlen($this->apiKeys['etherscan']) > 0 ? 'configured' : 'MISSING') . "\n";
        echo "  - BscScan: " . (strlen($this->apiKeys['bscscan']) > 0 ? 'configured' : 'MISSING') . "\n";
        echo "  - TronGrid: " . (strlen($this->apiKeys['trongrid']) > 0 ? 'configured' : 'MISSING') . "\n";
    }

    /**
     * Main entry point - check all pending deposits
     */
    public function checkAllPendingDeposits(): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] Starting deposit check...\n";

        // Get all pending deposits
        $stmt = $this->db->prepare("
            SELECT * FROM deposits 
            WHERE status = :status 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at ASC
            LIMIT 100
        ");
        $stmt->execute(['status' => PaymentsConfig::DEPOSIT_STATUS_PENDING]);
        $pendingDeposits = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo "Found " . count($pendingDeposits) . " pending deposits\n";

        foreach ($pendingDeposits as $deposit) {
            try {
                $this->checkDeposit($deposit);
            } catch (\Throwable $e) {
                echo "Error checking deposit #{$deposit['id']}: {$e->getMessage()}\n";
                Logger::error('deposit_check_failed', [
                    'deposit_id' => $deposit['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        echo "[" . date('Y-m-d H:i:s') . "] Deposit check complete\n";
    }

    /**
     * Check a single deposit for incoming transactions
     */
    private function checkDeposit(array $deposit): void
    {
        $depositId = (int) $deposit['id'];
        $network = $deposit['network'];
        $expectedAmount = $deposit['expected_amount_usdt'];
        $address = $deposit['address'];
        $createdAt = strtotime($deposit['created_at']);

        echo "Checking deposit #{$depositId} ({$network}): expecting {$expectedAmount} USDT\n";

        // Get transactions for this address
        $transactions = $this->getUsdtTransactions($network, $address, $createdAt);

        if (empty($transactions)) {
            echo "  No transactions found\n";
            return;
        }

        // Look for matching transaction
        foreach ($transactions as $tx) {
            $txAmount = $tx['amount'];
            $txHash = $tx['hash'];
            $txTimestamp = $tx['timestamp'];

            // Check if transaction is after deposit creation
            if ($txTimestamp < $createdAt) {
                continue;
            }

            // CRITICAL: Check if this tx_hash has already been used for another deposit
            // This prevents the same transaction from being matched to multiple deposits!
            $existingStmt = $this->db->prepare("SELECT id FROM deposits WHERE tx_hash = :tx_hash LIMIT 1");
            $existingStmt->execute(['tx_hash' => $txHash]);
            $existingDeposit = $existingStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingDeposit !== false) {
                echo "  Transaction {$txHash} already used for deposit #{$existingDeposit['id']}, skipping...\n";
                continue;
            }

            // Check if amount matches (with 1% tolerance for fees)
            $tolerance = bcmul($expectedAmount, '0.01', 8);
            $minAmount = bcsub($expectedAmount, $tolerance, 8);
            
            if (bccomp($txAmount, $minAmount, 8) >= 0) {
                echo "  Found matching transaction: {$txHash} for {$txAmount} USDT\n";
                
                // Confirm the deposit
                $this->confirmDeposit($depositId, $network, $txHash, $txAmount);
                return;
            }
        }

        echo "  No matching transaction found\n";
    }

    /**
     * Get USDT transactions for an address
     */
    private function getUsdtTransactions(string $network, string $address, int $sinceTimestamp): array
    {
        switch ($network) {
            case 'erc20':
                return $this->getEtherscanTransactions($address, $sinceTimestamp, 'api.etherscan.io', $this->apiKeys['etherscan']);
            case 'bep20':
                return $this->getEtherscanTransactions($address, $sinceTimestamp, 'api.bscscan.com', $this->apiKeys['bscscan']);
            case 'trc20':
                return $this->getTronTransactions($address, $sinceTimestamp);
            default:
                return [];
        }
    }

    /**
     * Get transactions from Etherscan/BscScan API
     */
    private function getEtherscanTransactions(string $address, int $sinceTimestamp, string $apiDomain, string $apiKey): array
    {
        if (empty($apiKey)) {
            echo "  Warning: No API key for {$apiDomain}\n";
            return [];
        }

        $contractAddress = $apiDomain === 'api.bscscan.com' 
            ? self::USDT_CONTRACTS['bep20'] 
            : self::USDT_CONTRACTS['erc20'];

        $url = "https://{$apiDomain}/api?" . http_build_query([
            'module' => 'account',
            'action' => 'tokentx',
            'contractaddress' => $contractAddress,
            'address' => $address,
            'startblock' => 0,
            'endblock' => 99999999,
            'sort' => 'desc',
            'apikey' => $apiKey
        ]);

        $response = $this->httpGet($url);
        if ($response === null) {
            return [];
        }

        $data = json_decode($response, true);
        if (!isset($data['result']) || !is_array($data['result'])) {
            return [];
        }

        $transactions = [];
        foreach ($data['result'] as $tx) {
            // Only incoming transactions (to our address)
            if (strtolower($tx['to']) !== strtolower($address)) {
                continue;
            }

            // Convert from wei (6 decimals for USDT)
            $amount = bcdiv($tx['value'], '1000000', 8);
            
            $transactions[] = [
                'hash' => $tx['hash'],
                'amount' => $amount,
                'timestamp' => (int) $tx['timeStamp'],
                'from' => $tx['from'],
            ];
        }

        return $transactions;
    }

    /**
     * Get transactions from TronGrid API
     */
    private function getTronTransactions(string $address, int $sinceTimestamp): array
    {
        $apiKey = $this->apiKeys['trongrid'];
        
        $url = "https://api.trongrid.io/v1/accounts/{$address}/transactions/trc20?" . http_build_query([
            'only_to' => 'true',
            'limit' => 50,
            'contract_address' => self::USDT_CONTRACTS['trc20'],
            'min_timestamp' => $sinceTimestamp * 1000
        ]);

        $headers = [];
        if (!empty($apiKey)) {
            $headers[] = 'TRON-PRO-API-KEY: ' . $apiKey;
        }

        $response = $this->httpGet($url, $headers);
        if ($response === null) {
            return [];
        }

        $data = json_decode($response, true);
        if (!isset($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $transactions = [];
        foreach ($data['data'] as $tx) {
            // Only incoming transactions (to our address)
            if ($tx['to'] !== $address) {
                continue;
            }

            // Convert from sun (6 decimals for USDT)
            $amount = bcdiv($tx['value'], '1000000', 8);
            
            $transactions[] = [
                'hash' => $tx['transaction_id'],
                'amount' => $amount,
                'timestamp' => (int) ($tx['block_timestamp'] / 1000),
                'from' => $tx['from'],
            ];
        }

        return $transactions;
    }

    /**
     * Confirm a deposit
     */
    private function confirmDeposit(int $depositId, string $network, string $txHash, string $amount): void
    {
        try {
            $result = DepositService::handleConfirmedDeposit($depositId, $network, $txHash, $amount);
            
            echo "  ✅ Deposit #{$depositId} confirmed! User wallet credited with {$amount} USDT\n";
            
            Logger::event('deposit_auto_confirmed', [
                'deposit_id' => $depositId,
                'network' => $network,
                'tx_hash' => $txHash,
                'amount' => $amount,
                'user_id' => $result['deposit']['user_id'] ?? null
            ]);
        } catch (\Exception $e) {
            echo "  ❌ Failed to confirm deposit: {$e->getMessage()}\n";
            Logger::error('deposit_auto_confirm_failed', [
                'deposit_id' => $depositId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * HTTP GET request
     */
    private function httpGet(string $url, array $headers = []): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json',
                'User-Agent: Ghidar/1.0'
            ], $headers)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        return $response;
    }
}

// Run the monitor
$monitor = new BlockchainMonitor();
$monitor->checkAllPendingDeposits();

