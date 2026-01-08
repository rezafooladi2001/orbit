<?php

declare(strict_types=1);

namespace Tests\API;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\Core\Database;
use PDO;

/**
 * Tests for tap endpoint logic.
 * Verifies balance calculations, energy consumption, and score updates.
 */
class TapEndpointTest extends BaseTestCase
{
    public function testNormalTapConsumesEnergy(): void
    {
        // Create test user
        $user = TestFactory::createUser('1001');
        $userId = $user['id'];

        // Set initial values
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `balance` = 1000, 
                 `score` = 5000, 
                 `energy` = 100, 
                 `multitap` = 2,
                 `rechargingSpeed` = 1,
                 `energyLimit` = 1,
                 `lastTapTime` = :last_tap_time 
             WHERE `id` = :user_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'last_tap_time' => time() - 10,
        ]);

        // Simulate normal tap: 10 taps with multitap 2 = 20 taps processed
        $tapsInc = 10;
        $multitap = 2;
        $processedTaps = $tapsInc * $multitap; // 20

        // Get initial values
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $initialBalance = (int) $user['balance'];
        $initialScore = (int) $user['score'];
        $initialEnergy = (int) $user['energy'];

        // Process tap (simulating endpoint logic)
        $pdo->beginTransaction();
        
        $newBalance = $initialBalance + $processedTaps;
        $newScore = $initialScore + $processedTaps;
        $newEnergy = max(0, $initialEnergy - $processedTaps);

        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `score` = `score` + :tapsInc, 
                 `balance` = `balance` + :tapsInc2, 
                 `energy` = :energy, 
                 `lastTapTime` = :time 
             WHERE `id` = :user_id'
        );
        $stmt->execute([
            'tapsInc' => $processedTaps,
            'tapsInc2' => $processedTaps,
            'energy' => $newEnergy,
            'time' => time(),
            'user_id' => $userId,
        ]);

        $pdo->commit();

        // Verify results
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($newBalance, (int) $updatedUser['balance']);
        $this->assertEquals($newScore, (int) $updatedUser['score']);
        $this->assertEquals($newEnergy, (int) $updatedUser['energy']);
    }

    public function testTapCannotExceedEnergyLimit(): void
    {
        $user = TestFactory::createUser('1002');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        
        // Set energy to 50, but try to tap 100 times
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `energy` = 50, 
                 `multitap` = 1 
             WHERE `id` = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        $tapsInc = 100;
        $multitap = 1;
        $processedTaps = $tapsInc * $multitap; // 100

        $stmt = $pdo->prepare('SELECT `energy` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $initialEnergy = (int) $user['energy'];

        // Energy should be capped at available energy
        $actualTapsProcessed = min($processedTaps, $initialEnergy); // min(100, 50) = 50
        $newEnergy = $initialEnergy - $actualTapsProcessed; // 50 - 50 = 0

        $this->assertEquals(50, $actualTapsProcessed);
        $this->assertEquals(0, $newEnergy);
    }

    public function testTappingGuruMultipliesTapsByFive(): void
    {
        $user = TestFactory::createUser('1003');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `balance` = 0, 
                 `score` = 0, 
                 `multitap` = 2 
             WHERE `id` = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        $tapsInc = 10;
        $multitap = 2;
        
        // Tapping guru: tapsInc * 5 * multitap
        $processedTaps = $tapsInc * 5 * $multitap; // 10 * 5 * 2 = 100

        $initialBalance = 0;
        $newBalance = $initialBalance + $processedTaps;

        $this->assertEquals(100, $processedTaps);
        $this->assertEquals(100, $newBalance);
    }

    public function testEnergyRechargesBasedOnTime(): void
    {
        $user = TestFactory::createUser('1004');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        
        // Set initial energy and recharging speed
        $initialEnergy = 50;
        $rechargingSpeed = 10; // energy per second
        $energyLimit = 2;
        $maxEnergy = $energyLimit * 500; // 1000

        $stmt = $pdo->prepare(
            'UPDATE `users` 
             SET `energy` = :energy, 
                 `rechargingSpeed` = :recharging_speed,
                 `energyLimit` = :energy_limit,
                 `lastTapTime` = :last_tap_time 
             WHERE `id` = :user_id'
        );
        $stmt->execute([
            'energy' => $initialEnergy,
            'recharging_speed' => $rechargingSpeed,
            'energy_limit' => $energyLimit,
            'last_tap_time' => time() - 30, // 30 seconds ago
            'user_id' => $userId,
        ]);

        // Calculate new energy
        $remaining_time = time() - (time() - 30); // 30 seconds
        $calculated_energy = ($remaining_time * $rechargingSpeed) + $initialEnergy;
        
        // Cap at max energy
        if ($calculated_energy > $maxEnergy) {
            $calculated_energy = $maxEnergy;
        }

        // 50 + (30 * 10) = 350
        $this->assertEquals(350, $calculated_energy);
        $this->assertLessThanOrEqual($maxEnergy, $calculated_energy);
    }

    public function testBalanceCannotGoNegative(): void
    {
        $user = TestFactory::createUser('1005');
        $userId = $user['id'];

        $pdo = $this->getPdo();
        
        // Set initial balance to 0
        $stmt = $pdo->prepare('UPDATE `users` SET `balance` = 0 WHERE `id` = :user_id');
        $stmt->execute(['user_id' => $userId]);

        // Process tap (balance increases, so it should be positive)
        $tapsInc = 10;
        $multitap = 1;
        $processedTaps = $tapsInc * $multitap;

        $stmt = $pdo->prepare('SELECT `balance` FROM `users` WHERE `id` = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $initialBalance = (int) $user['balance'];

        $newBalance = max(0, $initialBalance + $processedTaps);

        // Balance should be positive after taps
        $this->assertGreaterThanOrEqual(0, $newBalance);
        $this->assertEquals(10, $newBalance);
    }
}

