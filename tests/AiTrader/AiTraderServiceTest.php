<?php

declare(strict_types=1);

namespace Tests\AiTrader;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\AITrader\AiTraderService;
use Ghidar\AITrader\AiTraderConfig;
use PDO;

/**
 * Tests for AiTraderService.
 * Covers deposits, withdrawals, and balance management.
 */
class AiTraderServiceTest extends BaseTestCase
{
    public function testDepositFromWalletMovesFundsCorrectly(): void
    {
        // Arrange
        $user = TestFactory::createUser('3001');
        $userId = $user['id'];
        $initialUsdtBalance = '500.00000000';
        $depositAmount = '200.00000000';

        TestFactory::setWalletBalance($userId, $initialUsdtBalance, '0.00000000');

        // Act
        $result = AiTraderService::depositFromWallet($userId, $depositAmount);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('wallet', $result);
        $this->assertArrayHasKey('ai_account', $result);

        $expectedWalletBalance = bcsub($initialUsdtBalance, $depositAmount, 8);
        $this->assertEquals($expectedWalletBalance, $result['wallet']['usdt_balance']);

        $this->assertEquals($depositAmount, $result['ai_account']['current_balance_usdt']);
        $this->assertEquals($depositAmount, $result['ai_account']['total_deposited_usdt']);

        // Verify action was logged
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `ai_trader_actions` 
             WHERE `user_id` = :user_id AND `type` = :type 
             ORDER BY `created_at` DESC LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => 'deposit_from_wallet',
        ]);
        $action = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($action);
        $this->assertEquals($depositAmount, $action['amount_usdt']);
    }

    public function testWithdrawToWalletMovesFundsBack(): void
    {
        // Arrange
        $user = TestFactory::createUser('3002');
        $userId = $user['id'];
        $initialWalletBalance = '100.00000000';
        $aiAccountBalance = '150.00000000';
        $withdrawAmount = '50.00000000';

        TestFactory::setWalletBalance($userId, $initialWalletBalance, '0.00000000');
        TestFactory::createAiAccount($userId, $aiAccountBalance);

        // Act
        $result = AiTraderService::withdrawToWallet($userId, $withdrawAmount);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('wallet', $result);
        $this->assertArrayHasKey('ai_account', $result);

        $expectedWalletBalance = bcadd($initialWalletBalance, $withdrawAmount, 8);
        $this->assertEquals($expectedWalletBalance, $result['wallet']['usdt_balance']);

        $expectedAiBalance = bcsub($aiAccountBalance, $withdrawAmount, 8);
        $this->assertEquals($expectedAiBalance, $result['ai_account']['current_balance_usdt']);

        // Verify action was logged
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `ai_trader_actions` 
             WHERE `user_id` = :user_id AND `type` = :type 
             ORDER BY `created_at` DESC LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => 'withdraw_to_wallet',
        ]);
        $action = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($action);
        $this->assertEquals($withdrawAmount, $action['amount_usdt']);
    }

    public function testDepositFromWalletInsufficientFunds(): void
    {
        // Arrange
        $user = TestFactory::createUser('3003');
        $userId = $user['id'];
        $initialUsdtBalance = '50.00000000'; // Less than deposit amount
        $depositAmount = '200.00000000';

        TestFactory::setWalletBalance($userId, $initialUsdtBalance, '0.00000000');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient USDT balance');

        AiTraderService::depositFromWallet($userId, $depositAmount);

        // Verify balances unchanged
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($initialUsdtBalance, $wallet['usdt_balance']);

        // Verify AI account not created or unchanged
        $stmt = $pdo->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account !== false) {
            $this->assertEquals('0.00000000', $account['current_balance_usdt']);
        }
    }

    public function testWithdrawToWalletInsufficientFunds(): void
    {
        // Arrange
        $user = TestFactory::createUser('3004');
        $userId = $user['id'];
        $aiAccountBalance = '50.00000000'; // Less than withdraw amount
        $withdrawAmount = '200.00000000';

        TestFactory::setWalletBalance($userId, '100.00000000', '0.00000000');
        TestFactory::createAiAccount($userId, $aiAccountBalance);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient USDT balance in AI Trader account');

        AiTraderService::withdrawToWallet($userId, $withdrawAmount);

        // Verify balances unchanged
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM `ai_accounts` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($aiAccountBalance, $account['current_balance_usdt']);
    }

    public function testDepositFromWalletValidatesMinimumAmount(): void
    {
        // Arrange
        $user = TestFactory::createUser('3005');
        $userId = $user['id'];
        $depositAmount = '50.00000000'; // Less than MIN_DEPOSIT_USDT

        TestFactory::setWalletBalance($userId, '1000.00000000', '0.00000000');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be at least');

        AiTraderService::depositFromWallet($userId, $depositAmount);
    }

    public function testDepositFromWalletValidatesMaximumAmount(): void
    {
        // Arrange
        $user = TestFactory::createUser('3006');
        $userId = $user['id'];
        $maxDeposit = AiTraderConfig::MAX_DEPOSIT_USDT;
        $depositAmount = bcadd($maxDeposit, '1.00000000', 8);

        TestFactory::setWalletBalance($userId, '1000000.00000000', '0.00000000');

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount exceeds maximum allowed');

        AiTraderService::depositFromWallet($userId, $depositAmount);
    }
}

