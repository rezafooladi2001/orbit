<?php

declare(strict_types=1);

namespace Tests\Airdrop;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\Airdrop\AirdropService;
use Ghidar\Airdrop\GhdConfig;
use PDO;

/**
 * Tests for AirdropService.
 * Covers GHD earning from taps and conversion to USDT.
 */
class AirdropServiceTest extends BaseTestCase
{
    public function testEarnFromTapsIncreasesGhdBalance(): void
    {
        // Arrange
        $user = TestFactory::createUser('1001');
        $userId = $user['id'];
        $initialGhdBalance = '0.00000000';
        $tapCount = 100;

        // Ensure initial balance is zero
        TestFactory::setWalletBalance($userId, '0.00000000', $initialGhdBalance);

        // Act
        $result = AirdropService::earnFromTaps($userId, $tapCount);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('wallet', $result);
        $this->assertArrayHasKey('ghd_earned', $result);

        $expectedGhdEarned = (string) ($tapCount * GhdConfig::GHD_PER_TAP);
        $this->assertEquals($expectedGhdEarned, $result['ghd_earned']);

        $updatedWallet = $result['wallet'];
        // Compare as numeric values since DECIMAL returns full precision strings (8 decimal places)
        $this->assertEquals(
            number_format((float) $expectedGhdEarned, 8, '.', ''),
            $updatedWallet['ghd_balance'],
            'GHD balance should match expected earned amount'
        );

        // Verify airdrop_actions record was created
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `airdrop_actions` 
             WHERE `user_id` = :user_id AND `type` = :type 
             ORDER BY `created_at` DESC LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => 'tap',
        ]);
        $action = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($action);
        // Compare with formatted values to handle DECIMAL precision
        $this->assertEquals(
            number_format((float) $expectedGhdEarned, 8, '.', ''),
            $action['amount_ghd']
        );
    }

    public function testConvertGhdToUsdtMovesFundsCorrectly(): void
    {
        // Arrange
        $user = TestFactory::createUser('1002');
        $userId = $user['id'];
        $initialGhdBalance = '5000.00000000'; // Enough to convert
        $initialUsdtBalance = '10.00000000';
        $ghdToConvert = '2000.00000000';

        TestFactory::setWalletBalance($userId, $initialUsdtBalance, $initialGhdBalance);

        // Act
        $result = AirdropService::convertGhdToUsdt($userId, (float) $ghdToConvert);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('wallet', $result);
        $this->assertArrayHasKey('converted_ghd', $result);
        $this->assertArrayHasKey('received_usdt', $result);

        $expectedUsdtReceived = bcdiv($ghdToConvert, (string) GhdConfig::GHD_PER_USDT, 8);
        $this->assertEquals($expectedUsdtReceived, $result['received_usdt']);

        $updatedWallet = $result['wallet'];
        $expectedGhdBalance = bcsub($initialGhdBalance, $ghdToConvert, 8);
        $expectedUsdtBalance = bcadd($initialUsdtBalance, $expectedUsdtReceived, 8);

        $this->assertEquals($expectedGhdBalance, $updatedWallet['ghd_balance']);
        $this->assertEquals($expectedUsdtBalance, $updatedWallet['usdt_balance']);

        // Verify airdrop_actions record was created
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM `airdrop_actions` 
             WHERE `user_id` = :user_id AND `type` = :type 
             ORDER BY `created_at` DESC LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => 'convert_to_usdt',
        ]);
        $action = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($action);
        $this->assertEquals($ghdToConvert, $action['amount_ghd']);
    }

    public function testConvertGhdToUsdtFailsWhenNotEnoughGhd(): void
    {
        // Arrange
        $user = TestFactory::createUser('1003');
        $userId = $user['id'];
        $initialGhdBalance = '500.00000000'; // Less than MIN_GHD_CONVERT (1000)
        $ghdToConvert = 2000.0; // More than available

        TestFactory::setWalletBalance($userId, '0.00000000', $initialGhdBalance);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient GHD balance');

        AirdropService::convertGhdToUsdt($userId, $ghdToConvert);

        // Verify wallet balances unchanged
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM `wallets` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($wallet);
        $this->assertEquals($initialGhdBalance, $wallet['ghd_balance']);
    }

    public function testConvertGhdToUsdtFailsWhenBelowMinimum(): void
    {
        // Arrange
        $user = TestFactory::createUser('1004');
        $userId = $user['id'];
        $initialGhdBalance = '500.00000000'; // Less than MIN_GHD_CONVERT
        $ghdToConvert = 500.0;

        TestFactory::setWalletBalance($userId, '0.00000000', $initialGhdBalance);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('GHD amount must be at least');

        AirdropService::convertGhdToUsdt($userId, $ghdToConvert);
    }

    public function testEarnFromTapsValidatesTapCount(): void
    {
        // Arrange
        $user = TestFactory::createUser('1005');
        $userId = $user['id'];

        // Act & Assert - zero taps
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tap count must be greater than 0');

        AirdropService::earnFromTaps($userId, 0);

        // Act & Assert - too many taps
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tap count exceeds maximum');

        AirdropService::earnFromTaps($userId, GhdConfig::MAX_TAPS_PER_REQUEST + 1);
    }
}

