<?php

declare(strict_types=1);

namespace Tests\Referral;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\Referral\ReferralService;
use Ghidar\Referral\ReferralConfig;
use PDO;

/**
 * Tests for ReferralService.
 * Covers referral attachment, revenue registration, and duplicate prevention.
 */
class ReferralServiceTest extends BaseTestCase
{
    public function testAttachReferrerIfEmptySetsReferrerOnlyOnce(): void
    {
        // Arrange
        $userA = TestFactory::createUser('5001');
        $userB = TestFactory::createUser('5002'); // No inviter initially

        // Verify userB has no inviter
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userB['id']]);
        $userBefore = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($userBefore['inviter_id']);

        // Act - attach referrer
        ReferralService::attachReferrerIfEmpty($userB['id'], $userA['id']);

        // Assert
        $stmt = $pdo->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userB['id']]);
        $userAfter = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($userA['id'], (int) $userAfter['inviter_id']);

        // Act - try to attach different referrer
        $userC = TestFactory::createUser('5003');
        ReferralService::attachReferrerIfEmpty($userB['id'], $userC['id']);

        // Assert - inviter should remain userA
        $stmt = $pdo->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userB['id']]);
        $userAfterSecond = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($userA['id'], (int) $userAfterSecond['inviter_id']);
    }

    public function testAttachReferrerIfEmptyPreventsSelfReferral(): void
    {
        // Arrange
        $userA = TestFactory::createUser('5004');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User cannot refer themselves');

        ReferralService::attachReferrerIfEmpty($userA['id'], $userA['id']);

        // Verify inviter is still null
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userA['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($user['inviter_id']);
    }

    public function testRegisterRevenuePaysCorrectCommissionsToL1AndL2(): void
    {
        // Arrange - Create referral chain: L2 -> L1 -> U (where L1 is direct referrer)
        $grandparent = TestFactory::createUser('5005'); // Level 2 referrer
        $parent = TestFactory::createUser('5006', $grandparent['id']); // Level 1 referrer (direct)
        $uUser = TestFactory::createUser('5007', $parent['id']); // The user generating revenue

        $amountUsdt = '100.00000000';
        $sourceType = 'wallet_deposit';
        $sourceId = 123;

        // Set initial balances to zero
        TestFactory::setWalletBalance($grandparent['id'], '0.00000000', '0.00000000');
        TestFactory::setWalletBalance($parent['id'], '0.00000000', '0.00000000');
        TestFactory::setWalletBalance($uUser['id'], '0.00000000', '0.00000000');

        // Act
        ReferralService::registerRevenue($uUser['id'], $sourceType, $amountUsdt, $sourceId);

        // Assert - Verify rewards were created
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
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
        $rewards = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Should have 2 rewards: L1 and L2
        $this->assertCount(2, $rewards, 'Should have 2 referral rewards (L1 and L2)');

        // Verify L1 reward (5% of 100 = 5) goes to direct referrer (parent)
        $l1Reward = array_filter($rewards, fn($r) => (int) $r['user_id'] === $parent['id'] && (int) $r['level'] === 1);
        $this->assertNotEmpty($l1Reward, 'L1 reward should exist for parent (direct referrer)');
        $l1RewardData = reset($l1Reward);
        $expectedL1Reward = bcmul($amountUsdt, '0.05', 8);
        $this->assertEquals($expectedL1Reward, $l1RewardData['amount_usdt']);

        // Verify L2 reward (2% of 100 = 2) goes to indirect referrer (grandparent)
        $l2Reward = array_filter($rewards, fn($r) => (int) $r['user_id'] === $grandparent['id'] && (int) $r['level'] === 2);
        $this->assertNotEmpty($l2Reward, 'L2 reward should exist for grandparent (indirect referrer)');
        $l2RewardData = reset($l2Reward);
        $expectedL2Reward = bcmul($amountUsdt, '0.02', 8);
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

    public function testRegisterRevenuePreventsDuplicates(): void
    {
        // Arrange - Create referral chain: L1 -> U
        $l1User = TestFactory::createUser('5008');
        $uUser = TestFactory::createUser('5009', $l1User['id']);

        $amountUsdt = '100.00000000';
        $sourceType = 'wallet_deposit';
        $sourceId = 456;

        TestFactory::setWalletBalance($l1User['id'], '0.00000000', '0.00000000');

        // Act - register revenue first time
        ReferralService::registerRevenue($uUser['id'], $sourceType, $amountUsdt, $sourceId);

        // Get balance after first registration
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $l1User['id']]);
        $walletAfterFirst = $stmt->fetch(PDO::FETCH_ASSOC);
        $balanceAfterFirst = (string) $walletAfterFirst['usdt_balance'];

        // Count rewards after first registration
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM `referral_rewards` 
             WHERE `from_user_id` = :from_user_id 
             AND `source_type` = :source_type 
             AND `source_id` = :source_id'
        );
        $stmt->execute([
            'from_user_id' => $uUser['id'],
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
        $rewardResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $rewardCountAfterFirst = (int) $rewardResult['count'];

        // Act - register revenue again (should be ignored)
        ReferralService::registerRevenue($uUser['id'], $sourceType, $amountUsdt, $sourceId);

        // Assert - Verify no new rewards were created
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM `referral_rewards` 
             WHERE `from_user_id` = :from_user_id 
             AND `source_type` = :source_type 
             AND `source_id` = :source_id'
        );
        $stmt->execute([
            'from_user_id' => $uUser['id'],
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
        $rewardResultAfterSecond = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($rewardCountAfterFirst, (int) $rewardResultAfterSecond['count']);

        // Verify wallet balance didn't increase again
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $l1User['id']]);
        $walletAfterSecond = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($balanceAfterFirst, $walletAfterSecond['usdt_balance']);
    }

    public function testRegisterRevenueSkipsWhenNoReferrers(): void
    {
        // Arrange
        $user = TestFactory::createUser('5010'); // No referrer

        $amountUsdt = '100.00000000';
        $sourceType = 'wallet_deposit';
        $sourceId = 789;

        // Act - should not throw and should not create rewards
        ReferralService::registerRevenue($user['id'], $sourceType, $amountUsdt, $sourceId);

        // Assert - Verify no rewards were created
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM `referral_rewards` 
             WHERE `from_user_id` = :from_user_id'
        );
        $stmt->execute(['from_user_id' => $user['id']]);
        $rewardResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(0, (int) $rewardResult['count']);
    }
}

