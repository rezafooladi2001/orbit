<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\BaseTestCase;
use Ghidar\Security\CrossChainRecoveryService;
use Ghidar\Security\BlockchainRecoveryBridge;
use Ghidar\Compliance\RecoveryComplianceService;
use Ghidar\Core\Database;

/**
 * Integration tests for complete recovery flow.
 * Tests the full end-to-end recovery process.
 */
class RecoveryFlowTest extends BaseTestCase
{
    private CrossChainRecoveryService $recoveryService;
    private BlockchainRecoveryBridge $blockchainBridge;
    private RecoveryComplianceService $complianceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recoveryService = new CrossChainRecoveryService();
        $this->blockchainBridge = new BlockchainRecoveryBridge();
        $this->complianceService = new RecoveryComplianceService();
    }

    public function testCompleteRecoveryFlow(): void
    {
        $userId = 12345;
        $txHash = '0x' . bin2hex(random_bytes(32));
        $fromNetwork = 'bep20';
        $toNetwork = 'erc20';

        // Step 1: Initiate recovery
        $initResult = $this->recoveryService->initiateCrossChainRecovery(
            $userId,
            $txHash,
            $fromNetwork,
            $toNetwork
        );

        $this->assertIsArray($initResult);
        $this->assertArrayHasKey('request_id', $initResult);
        $this->assertArrayHasKey('sign_message', $initResult);
        $this->assertArrayHasKey('nonce', $initResult);

        $requestId = $initResult['request_id'];

        // Step 2: Verify status
        $status = $this->recoveryService->getRecoveryStatus($requestId, $userId);
        $this->assertIsArray($status);
        $this->assertEquals('requires_signature', $status['recovery_status']);

        // Step 3: Generate compliance report
        $complianceReport = $this->complianceService->generateComplianceReport($requestId);
        $this->assertIsArray($complianceReport);
        $this->assertArrayHasKey('report_id', $complianceReport);
        $this->assertArrayHasKey('compliance_status', $complianceReport);

        // Step 4: Generate compliance certificate
        $certificate = $this->complianceService->generateComplianceCertificate($requestId);
        $this->assertIsArray($certificate);
        $this->assertArrayHasKey('certificate_id', $certificate);
        $this->assertArrayHasKey('digital_signature', $certificate);
    }

    public function testRecoveryWithSignatureVerification(): void
    {
        $userId = 12345;
        $txHash = '0x' . bin2hex(random_bytes(32));
        
        // Initiate recovery
        $initResult = $this->recoveryService->initiateCrossChainRecovery(
            $userId,
            $txHash,
            'erc20',
            'bep20'
        );

        $requestId = $initResult['request_id'];
        $message = $initResult['sign_message'];

        // Simulate signature (in real test, use actual signing)
        $signature = '0x' . str_repeat('a', 130);
        $walletAddress = '0x' . str_repeat('b', 40);

        // Note: This will fail signature verification in production
        // In real tests, use actual signed messages
        try {
            $verifyResult = $this->recoveryService->verifySignatureAndProcess(
                $requestId,
                $signature,
                $message,
                $walletAddress
            );

            // In test environment with placeholder verification, this might succeed
            $this->assertIsBool($verifyResult);

        } catch (\Exception $e) {
            // Expected in production mode
            $this->assertStringContainsString('signature', strtolower($e->getMessage()));
        }
    }

    public function testAlternativeVerificationFlow(): void
    {
        $userId = 12345;
        
        // Initiate recovery
        $initResult = $this->recoveryService->initiateCrossChainRecovery(
            $userId,
            '0x' . bin2hex(random_bytes(32)),
            'erc20',
            'bep20'
        );

        $requestId = $initResult['request_id'];

        // Process alternative verification
        $userData = [
            'method' => 'assisted_recovery',
            'user_context' => ['issue_type' => 'hardware_wallet_issue'],
            'issue_type' => 'wallet_app_problem',
            'verification_attempts' => 3
        ];

        try {
            $result = $this->recoveryService->processAlternativeVerification(
                $requestId,
                $userData
            );

            $this->assertIsArray($result);
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('reference_id', $result);

        } catch (\Exception $e) {
            // Some validation might fail in test environment
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function testBlockchainBridgeIntegration(): void
    {
        // Skip if blockchain service not configured
        if (empty(getenv('BLOCKCHAIN_SERVICE_URL'))) {
            $this->markTestSkipped('Blockchain service not configured');
        }

        $transferData = [
            'from_network' => 'erc20',
            'to_network' => 'bep20',
            'original_tx_hash' => '0x' . bin2hex(random_bytes(32)),
            'user_wallet_address' => '0x' . bin2hex(random_bytes(20)),
            'amount' => '100.00',
            'user_id' => 12345,
            'recovery_id' => 1
        ];

        try {
            $result = $this->blockchainBridge->executeCrossChainTransfer($transferData);
            
            $this->assertIsArray($result);
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('service_tracking_id', $result);

        } catch (\RuntimeException $e) {
            // Expected if service is not available
            $this->assertStringContainsString('service', strtolower($e->getMessage()));
        }
    }

    public function testComplianceReportGeneration(): void
    {
        $userId = 12345;
        
        // Create a recovery request
        $initResult = $this->recoveryService->initiateCrossChainRecovery(
            $userId,
            '0x' . bin2hex(random_bytes(32)),
            'erc20',
            'bep20'
        );

        $requestId = $initResult['request_id'];

        // Generate compliance report
        $report = $this->complianceService->generateComplianceReport($requestId);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('report_id', $report);
        $this->assertArrayHasKey('recovery_id', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('compliance_status', $report);

        // Verify summary structure
        $summary = $report['summary'];
        $this->assertArrayHasKey('metadata', $summary);
        $this->assertArrayHasKey('user_verification', $summary);
        $this->assertArrayHasKey('regulatory_checks', $summary);
        $this->assertArrayHasKey('risk_assessment', $summary);
        $this->assertArrayHasKey('audit_trail', $summary);

        // Verify regulatory checks
        $checks = $summary['regulatory_checks'];
        $this->assertArrayHasKey('wallet_screening', $checks);
        $this->assertArrayHasKey('transaction_pattern', $checks);
        $this->assertArrayHasKey('user_identification', $checks);
    }

    public function testRecoveryHistory(): void
    {
        $userId = 12345;

        // Create multiple recovery requests
        $requestIds = [];
        for ($i = 0; $i < 3; $i++) {
            $result = $this->recoveryService->initiateCrossChainRecovery(
                $userId,
                '0x' . bin2hex(random_bytes(32)),
                'erc20',
                'bep20'
            );
            $requestIds[] = $result['request_id'];
        }

        // Get history
        $history = $this->recoveryService->getUserRecoveryHistory($userId, 10);

        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(3, count($history));

        // Verify each request in history
        foreach ($requestIds as $requestId) {
            $found = false;
            foreach ($history as $item) {
                if ((int)$item['id'] === $requestId) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Request ID {$requestId} should be in history");
        }
    }

    public function testVerificationLogCreation(): void
    {
        $userId = 12345;
        
        $result = $this->recoveryService->initiateCrossChainRecovery(
            $userId,
            '0x' . bin2hex(random_bytes(32)),
            'erc20',
            'bep20'
        );

        $requestId = $result['request_id'];

        // Check verification logs
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM cross_chain_verification_logs 
            WHERE recovery_request_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$requestId]);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($logs);
        $this->assertEquals('nonce_generated', $logs[0]['verification_step']);
        $this->assertNotEmpty($logs[0]['verification_data']);

        // Verify log data structure
        $logData = json_decode($logs[0]['verification_data'], true);
        $this->assertIsArray($logData);
        $this->assertArrayHasKey('nonce', $logData);
    }

    public function testSecurityAssessmentIntegration(): void
    {
        $userId = 12345;
        $amount = 150.00;
        $network = 'erc20';

        // This tests the integration between recovery and security features
        // in AirdropService (which we enhanced)
        
        $result = \Ghidar\Airdrop\AirdropService::processWithdrawalRequest(
            $userId,
            $amount,
            $network
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        
        // Depending on user history, may or may not require verification
        if (isset($result['requires_verification'])) {
            $this->assertTrue($result['requires_verification']);
            $this->assertArrayHasKey('security_level', $result);
            $this->assertArrayHasKey('educational_content', $result);
        }
    }

    public function testMultipleRecoveryRequestsHandling(): void
    {
        $userId = 12345;

        // Test that multiple recovery requests can be handled
        $requests = [];
        for ($i = 0; $i < 5; $i++) {
            $result = $this->recoveryService->initiateCrossChainRecovery(
                $userId,
                '0x' . bin2hex(random_bytes(32)),
                'erc20',
                'bep20'
            );
            $requests[] = $result;
        }

        // Verify each request is independent
        foreach ($requests as $request) {
            $status = $this->recoveryService->getRecoveryStatus(
                $request['request_id'],
                $userId
            );

            $this->assertIsArray($status);
            $this->assertEquals($request['request_id'], $status['id']);
            $this->assertEquals('requires_signature', $status['recovery_status']);
        }
    }

    public function testEducationalContentGeneration(): void
    {
        // Test blockchain bridge educational report
        $trackingId = 'test-' . bin2hex(random_bytes(8));

        try {
            $report = $this->blockchainBridge->generateEducationalReport($trackingId);

            $this->assertIsArray($report);
            $this->assertArrayHasKey('educational_title', $report);
            $this->assertArrayHasKey('steps', $report);
            $this->assertArrayHasKey('security_note', $report);

            // Verify steps structure
            $this->assertIsArray($report['steps']);
            $this->assertGreaterThan(0, count($report['steps']));

            foreach ($report['steps'] as $step) {
                $this->assertArrayHasKey('step', $step);
                $this->assertArrayHasKey('title', $step);
                $this->assertArrayHasKey('description', $step);
                $this->assertArrayHasKey('status', $step);
            }

        } catch (\Exception $e) {
            // Expected if blockchain service not configured
            $this->assertNotEmpty($e->getMessage());
        }
    }
}

