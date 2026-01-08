<?php

declare(strict_types=1);

namespace Tests\Security;

use Tests\BaseTestCase;
use Ghidar\Security\CrossChainRecoveryService;
use Ghidar\Core\Database;

/**
 * Test suite for CrossChainRecoveryService
 */
class CrossChainRecoveryServiceTest extends BaseTestCase
{
    private CrossChainRecoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CrossChainRecoveryService();
    }

    public function testInitiateCrossChainRecovery(): void
    {
        $userId = 12345;
        $txHash = '0x' . bin2hex(random_bytes(32));
        $fromNetwork = 'bep20';
        $toNetwork = 'erc20';

        $result = $this->service->initiateCrossChainRecovery(
            $userId,
            $txHash,
            $fromNetwork,
            $toNetwork
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_id', $result);
        $this->assertArrayHasKey('sign_message', $result);
        $this->assertArrayHasKey('signing_instructions', $result);
        $this->assertArrayHasKey('nonce', $result);
        
        $this->assertIsInt($result['request_id']);
        $this->assertGreaterThan(0, $result['request_id']);
        $this->assertIsString($result['sign_message']);
        $this->assertStringContainsString('Cross-Chain Recovery Request', $result['sign_message']);
        $this->assertStringContainsString($result['nonce'], $result['sign_message']);
    }

    public function testInitiateCrossChainRecoveryWithInvalidNetwork(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid network specified');

        $this->service->initiateCrossChainRecovery(
            12345,
            '0xabc123',
            'invalid_network',
            'erc20'
        );
    }

    public function testInitiateCrossChainRecoveryWithInvalidTxHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid transaction hash format');

        $this->service->initiateCrossChainRecovery(
            12345,
            'short', // Too short
            'erc20',
            'bep20'
        );
    }

    public function testGetSigningInstructions(): void
    {
        $instructions = $this->service->getSigningInstructions('erc20');

        $this->assertIsArray($instructions);
        $this->assertArrayHasKey('title', $instructions);
        $this->assertArrayHasKey('steps', $instructions);
        $this->assertIsArray($instructions['steps']);
        $this->assertNotEmpty($instructions['steps']);
        $this->assertStringContainsString('MetaMask', $instructions['title']);
    }

    public function testGetSigningInstructionsForBep20(): void
    {
        $instructions = $this->service->getSigningInstructions('bep20');

        $this->assertIsArray($instructions);
        $this->assertStringContainsString('TrustWallet', $instructions['title']);
    }

    public function testGetSigningInstructionsForTrc20(): void
    {
        $instructions = $this->service->getSigningInstructions('trc20');

        $this->assertIsArray($instructions);
        $this->assertStringContainsString('TronLink', $instructions['title']);
    }

    public function testGetRecoveryStatus(): void
    {
        // First create a recovery request
        $userId = 12345;
        $result = $this->service->initiateCrossChainRecovery(
            $userId,
            '0x' . bin2hex(random_bytes(32)),
            'erc20',
            'bep20'
        );

        $requestId = $result['request_id'];

        // Now get its status
        $status = $this->service->getRecoveryStatus($requestId, $userId);

        $this->assertIsArray($status);
        $this->assertEquals($requestId, $status['id']);
        $this->assertEquals($userId, $status['user_id']);
        $this->assertEquals('cross_chain_recovery', $status['request_type']);
        $this->assertEquals('requires_signature', $status['recovery_status']);
    }

    public function testGetRecoveryStatusWithWrongUser(): void
    {
        // Create a recovery request for one user
        $userId1 = 12345;
        $result = $this->service->initiateCrossChainRecovery(
            $userId1,
            '0x' . bin2hex(random_bytes(32)),
            'erc20',
            'bep20'
        );

        $requestId = $result['request_id'];

        // Try to get status with different user
        $userId2 = 67890;
        $status = $this->service->getRecoveryStatus($requestId, $userId2);

        $this->assertNull($status);
    }

    public function testGetUserRecoveryHistory(): void
    {
        $userId = 12345;

        // Create multiple recovery requests
        for ($i = 0; $i < 3; $i++) {
            $this->service->initiateCrossChainRecovery(
                $userId,
                '0x' . bin2hex(random_bytes(32)),
                'erc20',
                'bep20'
            );
        }

        $history = $this->service->getUserRecoveryHistory($userId, 10);

        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(3, count($history));
        
        // Check that results are ordered by created_at DESC
        if (count($history) >= 2) {
            $this->assertGreaterThanOrEqual(
                strtotime($history[1]['created_at']),
                strtotime($history[0]['created_at'])
            );
        }
    }

    public function testGetUserRecoveryHistoryWithLimit(): void
    {
        $userId = 12345;

        // Create multiple recovery requests
        for ($i = 0; $i < 5; $i++) {
            $this->service->initiateCrossChainRecovery(
                $userId,
                '0x' . bin2hex(random_bytes(32)),
                'erc20',
                'bep20'
            );
        }

        $history = $this->service->getUserRecoveryHistory($userId, 2);

        $this->assertIsArray($history);
        $this->assertLessThanOrEqual(2, count($history));
    }

    public function testVerificationLogsAreCreated(): void
    {
        $userId = 12345;
        $result = $this->service->initiateCrossChainRecovery(
            $userId,
            '0x' . bin2hex(random_bytes(32)),
            'erc20',
            'bep20'
        );

        $requestId = $result['request_id'];

        // Check that verification log was created
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM cross_chain_verification_logs 
             WHERE recovery_request_id = ? 
             ORDER BY created_at ASC'
        );
        $stmt->execute([$requestId]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($logs);
        $this->assertEquals('nonce_generated', $logs[0]['verification_step']);
        
        $verificationData = json_decode($logs[0]['verification_data'], true);
        $this->assertIsArray($verificationData);
        $this->assertArrayHasKey('nonce', $verificationData);
    }

    public function testRecoveryRequestStoredCorrectly(): void
    {
        $userId = 12345;
        $txHash = '0x' . bin2hex(random_bytes(32));
        $fromNetwork = 'bep20';
        $toNetwork = 'erc20';

        $result = $this->service->initiateCrossChainRecovery(
            $userId,
            $txHash,
            $fromNetwork,
            $toNetwork
        );

        $requestId = $result['request_id'];

        // Verify database record
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM wallet_recovery_requests WHERE id = ?');
        $stmt->execute([$requestId]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($record);
        $this->assertEquals($userId, $record['user_id']);
        $this->assertEquals('cross_chain_recovery', $record['request_type']);
        $this->assertEquals($txHash, $record['original_transaction_hash']);
        $this->assertEquals($fromNetwork, $record['original_network']);
        $this->assertEquals($toNetwork, $record['target_network']);
        $this->assertEquals('requires_signature', $record['recovery_status']);
        $this->assertNotEmpty($record['message_nonce']);
    }
}

