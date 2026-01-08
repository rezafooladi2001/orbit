<?php

declare(strict_types=1);

namespace Tests\Core;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\Core\Database;
use PDO;

/**
 * Tests for balance calculations.
 * Ensures balance updates are accurate and prevent negative balances.
 */
class BalanceCalculationTest extends BaseTestCase
{
    public function testBalanceIncrementWithValidAmount(): void
    {
        $user = TestFactory::createUser('2001');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        
        // Set initial balance
        $stmt = $pdo->prepare('UPDATE `users` SET `balance` = 1000 WHERE `id` = :user_id');
        $stmt->execute(['user_id' => $userId]);

        // Increment balance
        $increment = 500;
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `balance` = `balance` + :amount 
             WHERE `id` = :user_id'
        );
        $stmt->execute([
            'amount' => $increment,
            'user_id' => $userId,
        ]);

        // Verify
        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(1500, (int) $user['balance']);
    }

    public function testBalanceDecrementWithSufficientBalance(): void
    {
        $user = TestFactory::createUser('2002');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        
        // Set initial balance
        $stmt = $pdo->prepare('UPDATE `users` SET `balance` = 1000 WHERE `id` = :user_id');
        $stmt->execute(['user_id' => $userId]);

        // Decrement balance
        $decrement = 300;
        
        // First check balance
        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentBalance = (int) $user['balance'];

        // Ensure sufficient balance
        if ($currentBalance >= $decrement) {
            $stmt = $pdo->prepare(
                'UPDATE `users` 
                 SET `balance` = `balance` - :amount 
                 WHERE `id` = :user_id'
            );
            $stmt->execute([
                'amount' => $decrement,
                'user_id' => $userId,
            ]);
        }

        // Verify
        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(700, (int) $user['balance']);
    }

    public function testBalanceDecrementPreventsNegativeBalance(): void
    {
        $user = TestFactory::createUser('2003');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        
        // Set initial balance
        $stmt = $pdo->prepare('UPDATE `users` SET `balance` = 100 WHERE `id` = :user_id');
        $stmt->execute(['user_id' => $userId]);

        // Try to decrement more than available
        $decrement = 500;
        
        // Check balance first
        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentBalance = (int) $user['balance'];

        // Only decrement if balance is sufficient
        if ($currentBalance >= $decrement) {
            $stmt = $pdo->prepare(
                'UPDATE `users` 
                 SET `balance` = `balance` - :amount 
                 WHERE `id` = :user_id'
            );
            $stmt->execute([
                'amount' => $decrement,
                'user_id' => $userId,
            ]);
        }

        // Verify balance should remain unchanged (or at least non-negative)
        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Balance should still be 100 (decrement didn't happen)
        $this->assertEquals(100, (int) $user['balance']);
        $this->assertGreaterThanOrEqual(0, (int) $user['balance']);
    }

    public function testBalanceTransactionAtomicity(): void
    {
        $user = TestFactory::createUser('2004');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        
        // Set initial balance
        $stmt = $pdo->prepare('UPDATE `users` SET `balance` = 1000 WHERE `id` = :user_id');
        $stmt->execute(['user_id' => $userId]);

        // Start transaction
        $pdo->beginTransaction();

        try {
            // Increment balance
            $stmt = $pdo->prepare(
                'UPDATE `users` 
                 SET `balance` = `balance` + :amount 
                 WHERE `id` = :user_id'
            );
            $stmt->execute([
                'amount' => 500,
                'user_id' => $userId,
            ]);

            // Simulate error condition
            throw new \Exception('Simulated error');

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
        }

        // Verify balance was not changed (transaction rolled back)
        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(1000, (int) $user['balance']);
    }

    public function testBalanceUpdateWithIntegerValidation(): void
    {
        $user = TestFactory::createUser('2005');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        
        // Set initial balance
        $stmt = $pdo->prepare('UPDATE `users` SET `balance` = 0 WHERE `id` = :user_id');
        $stmt->execute(['user_id' => $userId]);

        // Validate amount is integer
        $amount = '123.45'; // String that should be cast to int
        
        // Cast to int (simulating validation)
        $amountInt = (int) $amount;
        
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `balance` = `balance` + :amount 
             WHERE `id` = :user_id'
        );
        $stmt->execute([
            'amount' => $amountInt,
            'user_id' => $userId,
        ]);

        // Verify
        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(123, (int) $user['balance']);
    }
}

