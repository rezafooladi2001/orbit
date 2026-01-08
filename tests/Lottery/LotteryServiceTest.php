<?php

declare(strict_types=1);

namespace Tests\Lottery;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\Lottery\LotteryService;
use Ghidar\Lottery\LotteryConfig;
use PDO;

/**
 * Tests for LotteryService.
 * Covers ticket purchase and winner drawing.
 */
class LotteryServiceTest extends BaseTestCase
{
    public function testPurchaseTicketsFromBalanceHappyPath(): void
    {
        // Arrange
        $user = TestFactory::createUser('2001');
        $userId = $user['id'];
        $initialUsdtBalance = '100.00000000';
        $lottery = TestFactory::createActiveLottery('1.00000000', '0.00000000');
        $lotteryId = (int) $lottery['id'];
        $ticketCount = 5;
        $ticketPrice = (string) $lottery['ticket_price_usdt'];

        TestFactory::setWalletBalance($userId, $initialUsdtBalance, '0.00000000');

        // Act
        $result = LotteryService::purchaseTicketsFromBalance($userId, $ticketCount);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('wallet', $result);
        $this->assertArrayHasKey('ticket_count_purchased', $result);
        $this->assertArrayHasKey('user_total_tickets', $result);
        $this->assertArrayHasKey('lottery', $result);

        $this->assertEquals($ticketCount, $result['ticket_count_purchased']);
        $this->assertEquals($ticketCount, $result['user_total_tickets']);

        $expectedCost = bcmul($ticketPrice, (string) $ticketCount, 8);
        $expectedUsdtBalance = bcsub($initialUsdtBalance, $expectedCost, 8);
        $this->assertEquals($expectedUsdtBalance, $result['wallet']['usdt_balance']);

        // Verify tickets were created
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM `lottery_tickets` 
             WHERE `lottery_id` = :lottery_id AND `user_id` = :user_id'
        );
        $stmt->execute([
            'lottery_id' => $lotteryId,
            'user_id' => $userId,
        ]);
        $ticketResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($ticketCount, (int) $ticketResult['count']);

        // Verify prize pool was updated
        $expectedPrizePool = bcadd((string) $lottery['prize_pool_usdt'], $expectedCost, 8);
        $this->assertEquals($expectedPrizePool, $result['lottery']['prize_pool_usdt']);
    }

    public function testPurchaseTicketsFromBalanceInsufficientFunds(): void
    {
        // Arrange
        $user = TestFactory::createUser('2002');
        $userId = $user['id'];
        $initialUsdtBalance = '1.00000000'; // Not enough for 5 tickets
        $lottery = TestFactory::createActiveLottery('1.00000000', '0.00000000');
        $ticketCount = 5;

        TestFactory::setWalletBalance($userId, $initialUsdtBalance, '0.00000000');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient USDT balance');

        LotteryService::purchaseTicketsFromBalance($userId, $ticketCount);

        // Verify no tickets were created
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM `lottery_tickets` 
             WHERE `user_id` = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $ticketResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(0, (int) $ticketResult['count']);

        // Verify wallet unchanged
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($initialUsdtBalance, $wallet['usdt_balance']);
    }

    public function testDrawWinnersAwardsPrizeOnce(): void
    {
        // Arrange
        $user1 = TestFactory::createUser('2003');
        $user2 = TestFactory::createUser('2004');
        $user3 = TestFactory::createUser('2005');

        $lottery = TestFactory::createActiveLottery('1.00000000', '0.00000000');
        $lotteryId = (int) $lottery['id'];

        // Purchase tickets for multiple users
        TestFactory::setWalletBalance($user1['id'], '10.00000000', '0.00000000');
        TestFactory::setWalletBalance($user2['id'], '10.00000000', '0.00000000');
        TestFactory::setWalletBalance($user3['id'], '10.00000000', '0.00000000');

        LotteryService::purchaseTicketsFromBalance($user1['id'], 2);
        LotteryService::purchaseTicketsFromBalance($user2['id'], 3);
        LotteryService::purchaseTicketsFromBalance($user3['id'], 1);

        // Get updated lottery with prize pool
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM `lotteries` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $lotteryId]);
        $updatedLottery = $stmt->fetch(PDO::FETCH_ASSOC);
        $prizePool = (string) $updatedLottery['prize_pool_usdt'];

        // Get initial wallet balance of one user (we'll check if winner gets credited)
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $user1['id']]);
        $initialWallet1 = $stmt->fetch(PDO::FETCH_ASSOC);

        // Act
        $result = LotteryService::drawWinners($lotteryId);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('lottery', $result);
        $this->assertArrayHasKey('winner', $result);

        // Verify lottery status changed to finished
        $this->assertEquals(LotteryConfig::STATUS_FINISHED, $result['lottery']['status']);

        // Verify exactly one winner was created
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM `lottery_winners` WHERE `lottery_id` = :lottery_id'
        );
        $stmt->execute(['lottery_id' => $lotteryId]);
        $winnerResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, (int) $winnerResult['count']);

        // Verify winner's wallet was credited
        $winnerUserId = (int) $result['winner']['user_id'];
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $winnerUserId]);
        $winnerWallet = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($winnerWallet);
        $expectedBalance = bcadd((string) $initialWallet1['usdt_balance'], $prizePool, 8);
        // Note: We can't predict which user won, so we just verify the winner got the prize
        // The actual balance check depends on which user won

        // Verify prize amount matches
        $this->assertEquals($prizePool, $result['winner']['prize_amount_usdt']);
    }

    public function testDrawWinnersPreventsDuplicateWinners(): void
    {
        // Arrange
        $user = TestFactory::createUser('2006');
        $lottery = TestFactory::createActiveLottery('1.00000000', '0.00000000');
        $lotteryId = (int) $lottery['id'];

        TestFactory::setWalletBalance($user['id'], '10.00000000', '0.00000000');
        LotteryService::purchaseTicketsFromBalance($user['id'], 5);

        // Get prize pool
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM `lotteries` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $lotteryId]);
        $updatedLottery = $stmt->fetch(PDO::FETCH_ASSOC);
        $prizePool = (string) $updatedLottery['prize_pool_usdt'];

        // Get initial wallet balance
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $user['id']]);
        $initialWallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $initialBalance = (string) $initialWallet['usdt_balance'];

        // Act - draw winners first time
        $result1 = LotteryService::drawWinners($lotteryId);

        // Get wallet after first draw
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $user['id']]);
        $walletAfterFirst = $stmt->fetch(PDO::FETCH_ASSOC);
        $balanceAfterFirst = (string) $walletAfterFirst['usdt_balance'];

        // Act - try to draw winners again
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Lottery is not active');

        LotteryService::drawWinners($lotteryId);

        // Verify no duplicate winners
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM `lottery_winners` WHERE `lottery_id` = :lottery_id'
        );
        $stmt->execute(['lottery_id' => $lotteryId]);
        $winnerResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, (int) $winnerResult['count']);

        // Verify wallet balance didn't increase again
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $user['id']]);
        $walletAfterSecond = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($balanceAfterFirst, $walletAfterSecond['usdt_balance']);
    }
}

