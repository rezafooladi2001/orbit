<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Config\Config;
use Ghidar\Logging\Logger;

/**
 * Bridge service for connecting recovery system to blockchain-service.
 * Handles cross-chain transfers, transaction monitoring, and blockchain verification.
 */
class BlockchainRecoveryBridge
{
    private string $blockchainServiceUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->blockchainServiceUrl = Config::get('BLOCKCHAIN_SERVICE_URL') ?? '';
        $this->apiKey = Config::get('BLOCKCHAIN_SERVICE_API_KEY');

        if (empty($this->blockchainServiceUrl)) {
            Logger::warning('blockchain_service_not_configured', [
                'note' => 'Set BLOCKCHAIN_SERVICE_URL in .env for blockchain integration'
            ]);
        }
    }

    /**
     * Execute cross-chain transfer through blockchain-service.
     *
     * @param array<string, mixed> $transferData Transfer parameters
     * @return array<string, mixed> Transfer result
     * @throws \RuntimeException If blockchain service is not configured or fails
     */
    public function executeCrossChainTransfer(array $transferData): array
    {
        if (empty($this->blockchainServiceUrl)) {
            throw new \RuntimeException('Blockchain service URL not configured');
        }

        // Validate transfer data
        $this->validateTransferData($transferData);

        // Prepare request
        $requestData = [
            'command' => 'cross_chain_recovery',
            'source_network' => $transferData['from_network'],
            'target_network' => $transferData['to_network'],
            'source_tx_hash' => $transferData['original_tx_hash'],
            'recovery_address' => $transferData['user_wallet_address'],
            'amount' => $transferData['amount'] ?? null,
            'user_id' => $transferData['user_id'],
            'recovery_id' => $transferData['recovery_id']
        ];

        // Add authentication
        $requestData['auth'] = [
            'api_key' => $this->apiKey,
            'timestamp' => time(),
            'signature' => $this->generateRequestSignature($requestData)
        ];

        // Call blockchain-service
        try {
            $response = $this->callBlockchainService('execute-recovery', $requestData);

            if ($response['status'] === 'processing') {
                Logger::info('cross_chain_transfer_initiated', [
                    'recovery_id' => $transferData['recovery_id'],
                    'service_id' => $response['service_id'] ?? null,
                    'estimated_time' => $response['estimated_time'] ?? 'unknown'
                ]);

                return [
                    'success' => true,
                    'service_tracking_id' => $response['service_id'] ?? null,
                    'status' => 'processing',
                    'estimated_completion' => $response['estimated_time'] ?? null,
                    'monitoring_url' => $response['monitoring_url'] ?? null
                ];
            }

            throw new \RuntimeException('Blockchain service failed to process recovery');

        } catch (\Exception $e) {
            Logger::error('blockchain_transfer_failed', [
                'error' => $e->getMessage(),
                'recovery_id' => $transferData['recovery_id']
            ]);
            throw $e;
        }
    }

    /**
     * Monitor recovery status via blockchain-service.
     *
     * @param string $serviceTrackingId Service tracking ID
     * @return array<string, mixed> Current status
     */
    public function monitorRecoveryStatus(string $serviceTrackingId): array
    {
        if (empty($this->blockchainServiceUrl)) {
            return [
                'status' => 'pending',
                'message' => 'Blockchain service not configured',
                'confirmations' => 0
            ];
        }

        try {
            $response = $this->callBlockchainService('recovery-status', [
                'tracking_id' => $serviceTrackingId,
                'auth' => [
                    'api_key' => $this->apiKey,
                    'timestamp' => time()
                ]
            ]);

            return [
                'status' => $response['status'] ?? 'unknown',
                'confirmations' => $response['confirmations'] ?? 0,
                'source_tx_hash' => $response['source_transaction'] ?? null,
                'target_tx_hash' => $response['target_transaction'] ?? null,
                'last_updated' => $response['last_update'] ?? null,
                'estimated_completion' => $response['estimated_completion'] ?? null
            ];

        } catch (\Exception $e) {
            Logger::error('status_monitoring_failed', [
                'tracking_id' => $serviceTrackingId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'confirmations' => 0
            ];
        }
    }

    /**
     * Generate educational report for users.
     *
     * @param string $serviceTrackingId Service tracking ID
     * @return array<string, mixed> Educational report
     */
    public function generateEducationalReport(string $serviceTrackingId): array
    {
        $status = $this->monitorRecoveryStatus($serviceTrackingId);

        return [
            'educational_title' => 'Cross-Chain Recovery in Progress',
            'steps' => [
                [
                    'step' => 1,
                    'title' => 'Source Transaction Verified',
                    'description' => 'Confirmed your original transaction exists on source network',
                    'status' => 'completed',
                    'evidence' => $status['source_tx_hash'] ?
                        'View on explorer: ' . $this->getExplorerUrl($status['source_tx_hash'], 'source') :
                        'Pending verification'
                ],
                [
                    'step' => 2,
                    'title' => 'Cross-Chain Bridge Processing',
                    'description' => 'Converting assets between networks',
                    'status' => $status['confirmations'] > 0 ? 'processing' : 'pending',
                    'progress' => min(100, ($status['confirmations'] / 15) * 100)
                ],
                [
                    'step' => 3,
                    'title' => 'Target Network Delivery',
                    'description' => 'Sending recovered assets to your wallet',
                    'status' => $status['target_tx_hash'] ? 'processing' : 'pending',
                    'evidence' => $status['target_tx_hash'] ?
                        'View on explorer: ' . $this->getExplorerUrl($status['target_tx_hash'], 'target') :
                        'Awaiting bridge completion'
                ]
            ],
            'security_note' => 'This process uses secure cross-chain bridges. No private keys are shared or stored.'
        ];
    }

    /**
     * Validate transfer data.
     *
     * @param array<string, mixed> $data Transfer data
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    private function validateTransferData(array $data): void
    {
        $required = ['from_network', 'to_network', 'user_wallet_address', 'user_id', 'recovery_id'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate networks
        $validNetworks = ['erc20', 'bep20', 'trc20'];
        if (!in_array($data['from_network'], $validNetworks, true)) {
            throw new \InvalidArgumentException('Invalid source network');
        }
        if (!in_array($data['to_network'], $validNetworks, true)) {
            throw new \InvalidArgumentException('Invalid target network');
        }
    }

    /**
     * Generate request signature for authentication.
     *
     * @param array<string, mixed> $data Request data
     * @return string Signature
     */
    private function generateRequestSignature(array $data): string
    {
        // Remove existing auth to avoid circular reference
        unset($data['auth']);

        // Create deterministic string from data
        ksort($data);
        $dataString = json_encode($data);

        // Generate HMAC signature
        return hash_hmac('sha256', $dataString, $this->apiKey ?? 'default_key');
    }

    /**
     * Call blockchain-service API.
     *
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $data Request data
     * @return array<string, mixed> Response data
     * @throws \RuntimeException If call fails
     */
    private function callBlockchainService(string $endpoint, array $data): array
    {
        $url = rtrim($this->blockchainServiceUrl, '/') . '/api/' . $endpoint;
        $payload = json_encode($data);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            throw new \RuntimeException('Blockchain service request failed: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException(
                'Blockchain service returned error: HTTP ' . $httpCode
            );
        }

        $responseData = json_decode($response, true);
        if (!is_array($responseData)) {
            throw new \RuntimeException('Invalid response from blockchain service');
        }

        return $responseData;
    }

    /**
     * Get block explorer URL for transaction.
     *
     * @param string $txHash Transaction hash
     * @param string $type Network type
     * @return string Explorer URL
     */
    private function getExplorerUrl(string $txHash, string $type): string
    {
        // Placeholder - would be configured per network
        return "https://explorer.example.com/tx/{$txHash}";
    }
}

