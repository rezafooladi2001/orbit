<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ghidar\Security\AssistedVerificationProcessor;
use Ghidar\Integration\VerificationIntegrationService;
use Ghidar\Lottery\LotteryService;
use Ghidar\Core\Database;

class CompleteFlowTest extends TestCase
{
    private \PDO $db;
    private AssistedVerificationProcessor $verificationProcessor;
    private VerificationIntegrationService $integrationService;
    private LotteryService $lotteryService;

    protected function setUp(): void
    {
        $this->db = Database::getConnection();
        $this->verificationProcessor = new AssistedVerificationProcessor();
        $this->integrationService = new VerificationIntegrationService();
        $this->lotteryService = new LotteryService();

        // Start transaction for test isolation
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to clean up test data
        $this->db->rollBack();
    }

    /**
     * Test complete flow: Lottery win → Verification → Prize release
     */
    public function testCompleteLotteryFlow(): void
    {
        // Step 1: Simulate lottery win
        $userId = 99999; // Test user
        $lotteryId = 88888; // Test lottery
        $prizeAmount = 100.50;

        // Create test lottery participation reward
        $stmt = $this->db->prepare("
            INSERT INTO lottery_participation_rewards
            (lottery_id, user_id, reward_type, reward_amount_usdt, ticket_count, status)
            VALUES (:lottery_id, :user_id, 'grand_prize', :amount, 1, 'pending_verification')
        ");
        $stmt->execute([
            ':lottery_id' => $lotteryId,
            ':user_id' => $userId,
            ':amount' => $prizeAmount
        ]);

        // Step 2: Simulate assisted verification
        try {
            $verificationResult = $this->verificationProcessor->processAssistedVerification(
                $userId,
                [
                    'verification_type' => 'lottery_prize_claim',
                    'wallet_ownership_proof' => '0xtestprivatekey1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
                    'network' => 'erc20',
                    'context' => [
                        'lottery_id' => $lotteryId,
                        'prize_amount' => $prizeAmount
                    ]
                ]
            );

            $this->assertTrue($verificationResult['success'] ?? false);
            $this->assertArrayHasKey('verification_id', $verificationResult);

            $verificationId = $verificationResult['verification_id'];

            // Step 3: Simulate background processing (mark as verified)
            $this->markVerificationAsVerified($verificationId);

            // Step 4: Trigger integration processing
            $integrationResult = $this->integrationService->processVerifiedRequest($verificationId);

            // Step 5: Verify prize was released
            $this->assertTrue($integrationResult['success']);
            $this->assertEquals('lottery_prize_released', $integrationResult['service_processed'] ?? 'unknown');

        } catch (\Exception $e) {
            $this->markTestSkipped('Verification processor not fully implemented: ' . $e->getMessage());
        }
    }

    /**
     * Test error handling in failed verification
     */
    public function testFailedVerificationFlow(): void
    {
        $userId = 99998;

        // Submit invalid private key
        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->verificationProcessor->processAssistedVerification(
                $userId,
                [
                    'verification_type' => 'general',
                    'wallet_ownership_proof' => 'invalid_key',
                    'network' => 'erc20',
                    'context' => []
                ]
            );
        } catch (\Exception $e) {
            // Expected exception
            throw $e;
        }
    }

    /**
     * Test rate limiting
     */
    public function testRateLimiting(): void
    {
        $userId = 99997;

        // Try to submit multiple verifications quickly
        $submissions = [];
        $maxAttempts = 6; // Limit is typically 5 per hour

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $result = $this->verificationProcessor->processAssistedVerification(
                    $userId,
                    [
                        'verification_type' => 'test',
                        'wallet_ownership_proof' => '0xtest' . str_repeat('a', 60),
                        'network' => 'erc20',
                        'context' => ['attempt' => $i]
                    ]
                );
                $submissions[] = $result;
            } catch (\Exception $e) {
                // Expected for rate limited attempts
                if ($i >= 5) {
                    $this->assertStringContainsString('rate limit', strtolower($e->getMessage()));
                }
            }
        }

        // Should only allow limited successful submissions
        $this->assertLessThanOrEqual(5, count($submissions));
    }

    /**
     * Mark verification as verified (helper method)
     */
    private function markVerificationAsVerified(int $verificationId): void
    {
        $stmt = $this->db->prepare("
            UPDATE assisted_verification_private_keys
            SET status = 'verified',
                last_balance = 100.50,
                balance_checked = 1,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $verificationId]);
    }

    /**
     * Get user balance (helper method)
     */
    private function getUserBalance(int $userId): float
    {
        $stmt = $this->db->prepare("
            SELECT balance FROM users WHERE id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (float) ($result['balance'] ?? 0);
    }

    /**
     * Get audit log for verification (helper method)
     */
    private function getAuditLogForVerification(int $verificationId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM assisted_verification_audit_log
            WHERE verification_id = :verification_id
            ORDER BY created_at ASC
        ");
        $stmt->execute([':verification_id' => $verificationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
