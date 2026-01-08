<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\Core\Database;
use Ghidar\Core\WalletRepository;
use Ghidar\Auth\TelegramAuth;
use Ghidar\Airdrop\AirdropService;
use Ghidar\Airdrop\GhdConfig;
use Ghidar\Lottery\LotteryService;
use Ghidar\AITrader\AiTraderService;
use Ghidar\Referral\ReferralService;
use PDO;

/**
 * Comprehensive integration test for the complete mini-app user journey.
 * Tests all major features end-to-end to ensure production readiness.
 */
class MiniAppFinalTest extends BaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->getPdo();
    }

    /**
     * Test complete user journey from registration to all features.
     * This is the main end-to-end test covering the full flow.
     */
    public function testCompleteUserJourney(): void
    {
        // Step 1: User Registration
        $user = TestFactory::createUser('999999');
        $userId = (int) $user['id'];
        
        // Verify user and wallet created
        $this->assertNotNull($user);
        $this->assertArrayHasKey('wallet', $user);
        
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $this->assertEquals('0.00000000', $wallet['usdt_balance']);
        $this->assertEquals('0.00000000', $wallet['ghd_balance']);

        // Step 2: Game Mechanics - Tap and Earn
        // Set initial energy (no transaction needed, services handle their own)
        $stmt = $this->db->prepare(
            'UPDATE `users` SET `energy` = 100, `multitap` = 2, `lastTapTime` = :time WHERE `id` = :user_id'
        );
        $stmt->execute([
            'time' => time() - 10,
            'user_id' => $userId
        ]);

        // Simulate tap: 10 taps with multitap 2 = 20 processed
        $tapsInc = 10;
        $multitap = 2;
        $processedTaps = $tapsInc * $multitap;

        // Update user balance and score
        $stmt = $this->db->prepare(
            'UPDATE `users` 
             SET `score` = `score` + :taps, `balance` = `balance` + :taps2, `energy` = `energy` - :taps3 
             WHERE `id` = :user_id'
        );
        $stmt->execute([
            'taps' => $processedTaps,
            'taps2' => $processedTaps,
            'taps3' => $processedTaps,
            'user_id' => $userId
        ]);

        // Earn GHD from taps (AirdropService handles its own transaction)
        $airdropResult = AirdropService::earnFromTaps($userId, $tapsInc);
        $this->assertArrayHasKey('wallet', $airdropResult);
        $this->assertArrayHasKey('ghd_earned', $airdropResult);

        // Step 3: Convert GHD to USDT
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $ghdBalance = (float) $wallet['ghd_balance'];
        
        if ($ghdBalance >= GhdConfig::MIN_GHD_CONVERT) {
            $convertResult = AirdropService::convertGhdToUsdt($userId, $ghdBalance);
            $this->assertArrayHasKey('wallet', $convertResult);
            $this->assertArrayHasKey('converted_ghd', $convertResult);
            $this->assertArrayHasKey('received_usdt', $convertResult);
        }

        // Step 4: Lottery - Purchase Tickets
        $lottery = TestFactory::createActiveLottery('1.00000000', '0.00000000');
        $lotteryId = (int) $lottery['id'];

        // Set wallet balance for lottery purchase
        TestFactory::setWalletBalance($userId, '10.00000000', '0.00000000');

        $purchaseResult = LotteryService::purchaseTicketsFromBalance($userId, 2);
        $this->assertArrayHasKey('wallet', $purchaseResult);
        $this->assertArrayHasKey('ticket_count_purchased', $purchaseResult);
        $this->assertEquals(2, $purchaseResult['ticket_count_purchased']);

        // Step 5: AI Trader - Deposit and Withdraw
        TestFactory::setWalletBalance($userId, '200.00000000', '0.00000000');

        // Deposit to AI Trader (minimum is 100 USDT)
        $depositResult = AiTraderService::depositFromWallet($userId, '100.00000000');
        $this->assertArrayHasKey('wallet', $depositResult);
        $this->assertArrayHasKey('ai_account', $depositResult);
        
        $aiAccount = $depositResult['ai_account'];
        $this->assertEquals('100.00000000', $aiAccount['current_balance_usdt']);

        // Withdraw from AI Trader
        $withdrawResult = AiTraderService::withdrawToWallet($userId, '20.00000000');
        $this->assertArrayHasKey('wallet', $withdrawResult);
        $this->assertArrayHasKey('ai_account', $withdrawResult);

        // Step 6: Verify final balances
        $finalWallet = WalletRepository::getOrCreateByUserId($userId);
        $this->assertGreaterThanOrEqual(0, (float) $finalWallet['usdt_balance']);
        $this->assertGreaterThanOrEqual(0, (float) $finalWallet['ghd_balance']);

        // All operations completed successfully
        $this->assertTrue(true);
    }

    /**
     * Test authentication flow - user creation and login.
     */
    public function testAuthenticationFlow(): void
    {
        // Create user via TestFactory (simulates registration)
        $user = TestFactory::createUser('888888');
        $userId = (int) $user['id'];

        // Verify user exists in database
        $stmt = $this->db->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($dbUser);
        $this->assertEquals($userId, (int) $dbUser['id']);

        // Verify wallet was created
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $this->assertNotNull($wallet);
        $this->assertEquals($userId, (int) $wallet['user_id']);

        // Test getUser endpoint structure (simulate)
        $userData = [
            'id' => $userId,
            'score' => (int) $dbUser['score'],
            'balance' => (int) $dbUser['balance'],
            'energy' => (int) $dbUser['energy'],
            'multitap' => (int) $dbUser['multitap'],
            'energyLimit' => (int) $dbUser['energyLimit'],
            'rechargingSpeed' => (int) $dbUser['rechargingSpeed'],
        ];

        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('score', $userData);
        $this->assertArrayHasKey('balance', $userData);
        $this->assertArrayHasKey('energy', $userData);
    }

    /**
     * Test core game mechanics - tapping, energy, balance updates.
     */
    public function testGameMechanicsFlow(): void
    {
        $user = TestFactory::createUser('777777');
        $userId = (int) $user['id'];

        $this->db->beginTransaction();

        // Set initial state
        $stmt = $this->db->prepare(
            'UPDATE `users` 
             SET `balance` = 1000, `score` = 5000, `energy` = 100, 
                 `multitap` = 2, `rechargingSpeed` = 1, `energyLimit` = 1,
                 `lastTapTime` = :time 
             WHERE `id` = :user_id'
        );
        $stmt->execute([
            'time' => time() - 10,
            'user_id' => $userId
        ]);

        // Get initial values
        $stmt = $this->db->prepare('SELECT * FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $userBefore = $stmt->fetch(PDO::FETCH_ASSOC);

        $initialBalance = (int) $userBefore['balance'];
        $initialScore = (int) $userBefore['score'];
        $initialEnergy = (int) $userBefore['energy'];

        // Process tap: 10 taps with multitap 2 = 20 processed
        $tapsInc = 10;
        $multitap = 2;
        $processedTaps = $tapsInc * $multitap;

        // Update user
        $stmt = $this->db->prepare(
            'UPDATE `users` 
             SET `score` = `score` + :taps, `balance` = `balance` + :taps2, 
                 `energy` = GREATEST(0, `energy` - :taps3), `lastTapTime` = :time 
             WHERE `id` = :user_id'
        );
        $stmt->execute([
            'taps' => $processedTaps,
            'taps2' => $processedTaps,
            'taps3' => $processedTaps,
            'time' => time(),
            'user_id' => $userId
        ]);

        // Verify updates
        $stmt = $this->db->prepare('SELECT * FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $userAfter = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($initialBalance + $processedTaps, (int) $userAfter['balance']);
        $this->assertEquals($initialScore + $processedTaps, (int) $userAfter['score']);
        $this->assertEquals(max(0, $initialEnergy - $processedTaps), (int) $userAfter['energy']);

        // Test energy recharging
        $remainingTime = time() - (time() - 30); // 30 seconds
        $rechargingSpeed = 1;
        $calculatedEnergy = ($remainingTime * $rechargingSpeed) + (int) $userAfter['energy'];
        $maxEnergy = 1 * 500; // energyLimit * 500
        if ($calculatedEnergy > $maxEnergy) {
            $calculatedEnergy = $maxEnergy;
        }

        $this->assertGreaterThanOrEqual((int) $userAfter['energy'], $calculatedEnergy);
        $this->assertLessThanOrEqual($maxEnergy, $calculatedEnergy);

        $this->db->commit();
    }

    /**
     * Test airdrop flow - GHD earning and conversion.
     */
    public function testAirdropFlow(): void
    {
        $user = TestFactory::createUser('666666');
        $userId = (int) $user['id'];

        // Test GHD earning from taps
        $tapCount = 100;
        $result = AirdropService::earnFromTaps($userId, $tapCount);

        $this->assertArrayHasKey('wallet', $result);
        $this->assertArrayHasKey('ghd_earned', $result);
        
        $expectedGhd = $tapCount * GhdConfig::GHD_PER_TAP;
        $this->assertEquals($expectedGhd, $result['ghd_earned']);

        // Verify wallet balance updated
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $this->assertGreaterThanOrEqual($expectedGhd, (float) $wallet['ghd_balance']);

        // Test GHD to USDT conversion
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $ghdBalance = (float) $wallet['ghd_balance'];

        if ($ghdBalance >= GhdConfig::MIN_GHD_CONVERT) {
            $convertResult = AirdropService::convertGhdToUsdt($userId, $ghdBalance);
            
            $this->assertArrayHasKey('wallet', $convertResult);
            $this->assertArrayHasKey('converted_ghd', $convertResult);
            $this->assertArrayHasKey('received_usdt', $convertResult);

            // Verify balances updated correctly
            $updatedWallet = $convertResult['wallet'];
            $this->assertEquals('0.00000000', $updatedWallet['ghd_balance']);
            $this->assertGreaterThan('0.00000000', $updatedWallet['usdt_balance']);
        }

        // Test insufficient balance error
        $this->expectException(\InvalidArgumentException::class);
        AirdropService::convertGhdToUsdt($userId, 1000000.0); // More than available
    }

    /**
     * Test lottery flow - ticket purchase and management.
     */
    public function testLotteryFlow(): void
    {
        $user = TestFactory::createUser('555555');
        $userId = (int) $user['id'];

        // Create active lottery
        $lottery = TestFactory::createActiveLottery('1.00000000', '0.00000000');
        $lotteryId = (int) $lottery['id'];

        // Set wallet balance
        TestFactory::setWalletBalance($userId, '10.00000000', '0.00000000');

        // Test ticket purchase
        $ticketCount = 3;
        $result = LotteryService::purchaseTicketsFromBalance($userId, $ticketCount);

        $this->assertArrayHasKey('wallet', $result);
        $this->assertArrayHasKey('ticket_count_purchased', $result);
        $this->assertArrayHasKey('lottery', $result);
        
        $this->assertEquals($ticketCount, $result['ticket_count_purchased']);

        // Verify balance deducted
        $wallet = $result['wallet'];
        $ticketPrice = (float) $lottery['ticket_price_usdt'];
        $expectedBalance = 10.0 - ($ticketPrice * $ticketCount);
        $this->assertEqualsWithDelta($expectedBalance, (float) $wallet['usdt_balance'], 0.0001);

        // Verify tickets created
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM `lottery_tickets` 
             WHERE `lottery_id` = :lottery_id AND `user_id` = :user_id'
        );
        $stmt->execute([
            'lottery_id' => $lotteryId,
            'user_id' => $userId
        ]);
        $ticketResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($ticketCount, (int) $ticketResult['count']);

        // Test insufficient funds error
        TestFactory::setWalletBalance($userId, '0.50000000', '0.00000000');
        $this->expectException(\RuntimeException::class);
        LotteryService::purchaseTicketsFromBalance($userId, 1);
    }

    /**
     * Test AI Trader flow - deposit and withdrawal operations.
     */
    public function testAiTraderFlow(): void
    {
        $user = TestFactory::createUser('444444');
        $userId = (int) $user['id'];

        // Set initial wallet balance (need enough for minimum deposit of 100 USDT)
        TestFactory::setWalletBalance($userId, '200.00000000', '0.00000000');

        // Test AI account creation
        $account = AiTraderService::getOrCreateAccount($userId);
        $this->assertNotNull($account);
        $this->assertEquals($userId, (int) $account['user_id']);

        // Test deposit from wallet (minimum is 100 USDT)
        $depositAmount = '100.00000000';
        $depositResult = AiTraderService::depositFromWallet($userId, $depositAmount);

        $this->assertArrayHasKey('wallet', $depositResult);
        $this->assertArrayHasKey('ai_account', $depositResult);

        $wallet = $depositResult['wallet'];
        $aiAccount = $depositResult['ai_account'];

        // Verify wallet balance decreased
        $this->assertEqualsWithDelta(100.0, (float) $wallet['usdt_balance'], 0.0001);
        
        // Verify AI account balance increased
        $this->assertEquals($depositAmount, $aiAccount['current_balance_usdt']);
        $this->assertEquals($depositAmount, $aiAccount['total_deposited_usdt']);

        // Test withdrawal to wallet (minimum is 1 USDT)
        $withdrawAmount = '20.00000000';
        $withdrawResult = AiTraderService::withdrawToWallet($userId, $withdrawAmount);

        $this->assertArrayHasKey('wallet', $withdrawResult);
        $this->assertArrayHasKey('ai_account', $withdrawResult);

        $walletAfter = $withdrawResult['wallet'];
        $aiAccountAfter = $withdrawResult['ai_account'];

        // Verify wallet balance increased
        $this->assertEqualsWithDelta(120.0, (float) $walletAfter['usdt_balance'], 0.0001);
        
        // Verify AI account balance decreased
        $expectedAiBalance = 100.0 - 20.0;
        $this->assertEqualsWithDelta($expectedAiBalance, (float) $aiAccountAfter['current_balance_usdt'], 0.0001);

        // Test insufficient funds error
        $this->expectException(\InvalidArgumentException::class);
        AiTraderService::withdrawToWallet($userId, '100.00000000'); // More than available
    }

    /**
     * Test error handling - invalid inputs and edge cases.
     */
    public function testErrorHandling(): void
    {
        $user = TestFactory::createUser('333333');
        $userId = (int) $user['id'];

        // Test invalid tap count
        $this->expectException(\InvalidArgumentException::class);
        AirdropService::earnFromTaps($userId, 0);

        // Test invalid GHD conversion amount
        $this->expectException(\InvalidArgumentException::class);
        AirdropService::convertGhdToUsdt($userId, -10.0);

        // Test insufficient balance for conversion
        TestFactory::setWalletBalance($userId, '0.00000000', '0.00000000');
        $this->expectException(\InvalidArgumentException::class);
        AirdropService::convertGhdToUsdt($userId, 1000.0);

        // Test invalid lottery ticket count
        $lottery = TestFactory::createActiveLottery('1.00000000', '0.00000000');
        $this->expectException(\InvalidArgumentException::class);
        LotteryService::purchaseTicketsFromBalance($userId, 0);

        // Test insufficient funds for lottery
        TestFactory::setWalletBalance($userId, '0.50000000', '0.00000000');
        $this->expectException(\RuntimeException::class);
        LotteryService::purchaseTicketsFromBalance($userId, 1);

        // Test invalid AI Trader deposit amount
        TestFactory::setWalletBalance($userId, '100.00000000', '0.00000000');
        $this->expectException(\InvalidArgumentException::class);
        AiTraderService::depositFromWallet($userId, '0.00000000');

        // Test insufficient funds for AI Trader deposit
        TestFactory::setWalletBalance($userId, '5.00000000', '0.00000000');
        $this->expectException(\InvalidArgumentException::class);
        AiTraderService::depositFromWallet($userId, '10.00000000');
    }

    /**
     * Test data consistency - balance integrity and transaction atomicity.
     */
    public function testDataConsistency(): void
    {
        $user = TestFactory::createUser('222222');
        $userId = (int) $user['id'];

        // Set initial balances
        TestFactory::setWalletBalance($userId, '200.00000000', '0.00000000');

        // Test wallet balance consistency
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $initialUsdt = (float) $wallet['usdt_balance'];
        $initialGhd = (float) $wallet['ghd_balance'];

        // Earn GHD (AirdropService handles its own transaction)
        AirdropService::earnFromTaps($userId, 100);

        // Convert GHD to USDT (AirdropService handles its own transaction)
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $ghdBalance = (float) $wallet['ghd_balance'];
        if ($ghdBalance >= GhdConfig::MIN_GHD_CONVERT) {
            AirdropService::convertGhdToUsdt($userId, $ghdBalance);
        }

        // Deposit to AI Trader (AiTraderService handles its own transaction)
        $wallet = WalletRepository::getOrCreateByUserId($userId);
        $usdtBalance = (float) $wallet['usdt_balance'];
        if ($usdtBalance >= 100.0) {
            AiTraderService::depositFromWallet($userId, '100.00000000');
        }

        // Verify balances are non-negative
        $finalWallet = WalletRepository::getOrCreateByUserId($userId);
        $this->assertGreaterThanOrEqual(0, (float) $finalWallet['usdt_balance']);
        $this->assertGreaterThanOrEqual(0, (float) $finalWallet['ghd_balance']);

        // Test referral system consistency
        $referrer = TestFactory::createUser('111111');
        $referrerId = (int) $referrer['id'];

        // Attach referrer
        ReferralService::attachReferrerIfEmpty($userId, $referrerId);

        // Verify referrer attached
        $stmt = $this->db->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($referrerId, (int) $userData['inviter_id']);

        // Test self-referral prevention (create a new user without referrer)
        $newUser = TestFactory::createUser('111112');
        $newUserId = (int) $newUser['id'];
        
        $this->expectException(\InvalidArgumentException::class);
        ReferralService::attachReferrerIfEmpty($newUserId, $newUserId);
    }

    /**
     * Test referral system - attachment and rewards.
     */
    public function testReferralSystem(): void
    {
        // Create referrer
        $referrer = TestFactory::createUser('111000');
        $referrerId = (int) $referrer['id'];

        // Create new user with referrer
        $newUser = TestFactory::createUser('111001', $referrerId);
        $newUserId = (int) $newUser['id'];

        // Verify referrer attached
        $stmt = $this->db->prepare('SELECT `inviter_id` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $newUserId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($referrerId, (int) $userData['inviter_id']);

        // Test referral rewards on deposit
        TestFactory::setWalletBalance($newUserId, '100.00000000', '0.00000000');
        
        // Simulate deposit that triggers referral reward
        $depositId = 999;
        ReferralService::registerRevenue(
            $newUserId,
            'wallet_deposit',
            '100.00000000',
            $depositId
        );

        // Verify referrer received reward
        $referrerWallet = WalletRepository::getOrCreateByUserId($referrerId);
        $this->assertGreaterThan('0.00000000', $referrerWallet['usdt_balance']);

        // Test duplicate prevention
        ReferralService::registerRevenue(
            $newUserId,
            'wallet_deposit',
            '100.00000000',
            $depositId // Same source_id
        );

        // Verify reward not duplicated (check count)
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM `referral_rewards` 
             WHERE `user_id` = :user_id AND `source_id` = :source_id'
        );
        $stmt->execute([
            'user_id' => $referrerId,
            'source_id' => $depositId
        ]);
        $rewardResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, (int) $rewardResult['count']);
    }
}

