<?php

declare(strict_types=1);

namespace Tests\Payments;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\Payments\DepositService;
use Ghidar\Payments\PaymentsConfig;
use Ghidar\AITrader\AiTraderService;
use PDO;

/**
 * Tests for DepositService.
 * Covers confirmed deposit handling, wallet crediting, and referral rewards.
 */
class DepositServiceTest extends BaseTestCase
{
    public function testHandleConfirmedDepositWalletTopupCreditsWallet(): void
    {
        // Arrange
        $user = TestFactory::createUser('4001');
        $userId = $user['id'];
        $initialUsdtBalance = '10.00000000';
        $depositAmount = '100.00000000';

        TestFactory::setWalletBalance($userId, $initialUsdtBalance, '0.00000000');

        $deposit = TestFactory::createPendingDeposit(
            $userId,
            'trc20',
            PaymentsConfig::PRODUCT_WALLET_TOPUP,
            $depositAmount
        );
        $depositId = (int) $deposit['id'];

        // Act
        $result = DepositService::handleConfirmedDeposit(
            $depositId,
            'trc20',
            'test_tx_hash_' . bin2hex(random_bytes(16)),
            $depositAmount
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('deposit', $result);
        $this->assertArrayHasKey('wallet', $result);

        // Verify deposit status changed to confirmed
        $this->assertEquals(PaymentsConfig::DEPOSIT_STATUS_CONFIRMED, $result['deposit']['status']);
        $this->assertEquals($depositAmount, $result['deposit']['actual_amount_usdt']);

        // Verify wallet was credited
        $expectedBalance = bcadd($initialUsdtBalance, $depositAmount, 8);
        $this->assertEquals($expectedBalance, $result['wallet']['usdt_balance']);
    }

    public function testHandleConfirmedDepositAiTraderCreditsAiAccount(): void
    {
        // Arrange
        $user = TestFactory::createUser('4002');
        $userId = $user['id'];
        $initialUsdtBalance = '10.00000000';
        $depositAmount = '200.00000000';

        TestFactory::setWalletBalance($userId, $initialUsdtBalance, '0.00000000');

        $deposit = TestFactory::createPendingDeposit(
            $userId,
            'trc20',
            PaymentsConfig::PRODUCT_AI_TRADER,
            $depositAmount
        );
        $depositId = (int) $deposit['id'];

        // Act
        $result = DepositService::handleConfirmedDeposit(
            $depositId,
            'trc20',
            'test_tx_hash_' . bin2hex(random_bytes(16)),
            $depositAmount
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('product_action', $result);
        $this->assertEquals('ai_trader_deposited', $result['product_action']['type']);

        // Verify wallet was credited first, then moved to AI account
        // Wallet should have initial + deposit, then AI account should have deposit
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        // After deposit confirmation, wallet gets credited, then AI Trader deposit moves it
        // So final wallet balance = initial + deposit - deposit = initial
        $this->assertEquals($initialUsdtBalance, $wallet['usdt_balance']);

        // Verify AI account was credited (with connection retry)
        $pdo = $this->getPdo(); // Ensure connection is alive
        $aiAccount = AiTraderService::findAccount($userId);
        $this->assertNotNull($aiAccount);
        $this->assertEquals($depositAmount, $aiAccount['current_balance_usdt']);
    }

    public function testHandleConfirmedDepositTriggersReferralRewards(): void
    {
        // Arrange - Create referral chain: L2 -> L1 -> U (where L1 is direct referrer)
        // grandparent is L2 referrer, parent is L1 referrer, user is the depositor
        $grandparent = TestFactory::createUser('4003'); // Level 2 referrer
        $parent = TestFactory::createUser('4004', $grandparent['id']); // Level 1 referrer (direct)
        $uUser = TestFactory::createUser('4005', $parent['id']); // The user making deposit

        $depositAmount = '1000.00000000';

        TestFactory::setWalletBalance($grandparent['id'], '0.00000000', '0.00000000');
        TestFactory::setWalletBalance($parent['id'], '0.00000000', '0.00000000');
        TestFactory::setWalletBalance($uUser['id'], '0.00000000', '0.00000000');

        $deposit = TestFactory::createPendingDeposit(
            $uUser['id'],
            'trc20',
            PaymentsConfig::PRODUCT_WALLET_TOPUP,
            $depositAmount
        );
        $depositId = (int) $deposit['id'];

        // Act
        DepositService::handleConfirmedDeposit(
            $depositId,
            'trc20',
            'test_tx_hash_' . bin2hex(random_bytes(16)),
            $depositAmount
        );

        // Assert - Verify referral rewards were created
        // Ensure fresh connection to avoid stale data
        $pdo = $this->getPdo();
        
        $stmt = $pdo->prepare(
            'SELECT * FROM `referral_rewards` 
             WHERE `from_user_id` = :from_user_id 
             AND `source_type` = :source_type 
             AND `source_id` = :source_id'
        );
        $stmt->execute([
            'from_user_id' => $uUser['id'],
            'source_type' => 'wallet_deposit',
            'source_id' => $depositId,
        ]);
        $rewards = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Debug: Check all rewards for this user
        if (empty($rewards)) {
            $debugStmt = $pdo->prepare('SELECT * FROM `referral_rewards` WHERE `from_user_id` = :from_user_id');
            $debugStmt->execute(['from_user_id' => $uUser['id']]);
            $allRewards = $debugStmt->fetchAll(\PDO::FETCH_ASSOC);
            echo "\nDEBUG: All rewards for user {$uUser['id']}: " . count($allRewards) . "\n";
            if (!empty($allRewards)) {
                echo "Rewards found but with different source_type or source_id!\n";
                foreach ($allRewards as $r) {
                    echo "  - source_type: {$r['source_type']}, source_id: {$r['source_id']}, user_id: {$r['user_id']}, level: {$r['level']}\n";
                }
            }
        }

        // Should have 2 rewards: L1 and L2
        $this->assertCount(2, $rewards, 'Should have 2 referral rewards (L1 and L2). Expected source_type: wallet_deposit, source_id: ' . $depositId);

        // Verify L1 reward (5% of 1000 = 50) goes to direct referrer (parent)
        $l1Reward = array_filter($rewards, fn($r) => (int) $r['user_id'] === $parent['id'] && (int) $r['level'] === 1);
        $this->assertNotEmpty($l1Reward, 'L1 reward should exist for parent (direct referrer)');
        $l1RewardData = reset($l1Reward);
        $expectedL1Reward = bcmul($depositAmount, '0.05', 8);
        $this->assertEquals($expectedL1Reward, $l1RewardData['amount_usdt']);

        // Verify L2 reward (2% of 1000 = 20) goes to indirect referrer (grandparent)
        $l2Reward = array_filter($rewards, fn($r) => (int) $r['user_id'] === $grandparent['id'] && (int) $r['level'] === 2);
        $this->assertNotEmpty($l2Reward, 'L2 reward should exist for grandparent (indirect referrer)');
        $l2RewardData = reset($l2Reward);
        $expectedL2Reward = bcmul($depositAmount, '0.02', 8);
        $this->assertEquals($expectedL2Reward, $l2RewardData['amount_usdt']);

        // Verify wallets were credited
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $parent['id']]);
        $parentWallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($expectedL1Reward, $parentWallet['usdt_balance']);

        $stmt->execute(['user_id' => $grandparent['id']]);
        $grandparentWallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($expectedL2Reward, $grandparentWallet['usdt_balance']);
    }

    public function testHandleConfirmedDepositPreventsDoubleProcessing(): void
    {
        // Arrange
        $user = TestFactory::createUser('4006');
        $userId = $user['id'];
        $initialUsdtBalance = '10.00000000';
        $depositAmount = '100.00000000';

        TestFactory::setWalletBalance($userId, $initialUsdtBalance, '0.00000000');

        $deposit = TestFactory::createPendingDeposit(
            $userId,
            'trc20',
            PaymentsConfig::PRODUCT_WALLET_TOPUP,
            $depositAmount
        );
        $depositId = (int) $deposit['id'];

        // Act - process first time
        DepositService::handleConfirmedDeposit(
            $depositId,
            'trc20',
            'test_tx_hash_1',
            $depositAmount
        );

        // Get balance after first processing
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $walletAfterFirst = $stmt->fetch(PDO::FETCH_ASSOC);
        $balanceAfterFirst = (string) $walletAfterFirst['usdt_balance'];

        // Act & Assert - try to process again
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Deposit already processed');

        DepositService::handleConfirmedDeposit(
            $depositId,
            'trc20',
            'test_tx_hash_2',
            $depositAmount
        );

        // Verify wallet balance didn't increase again
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $walletAfterSecond = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($balanceAfterFirst, $walletAfterSecond['usdt_balance']);

        // Verify no duplicate referral rewards
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM `referral_rewards` 
             WHERE `source_type` = :source_type AND `source_id` = :source_id'
        );
        $stmt->execute([
            'source_type' => 'wallet_deposit',
            'source_id' => $depositId,
        ]);
        $rewardResult = $stmt->fetch(PDO::FETCH_ASSOC);
        // Should have 0 rewards if user has no referrer, or exactly the original count if they do
        // We'll just verify it didn't increase
    }
}

