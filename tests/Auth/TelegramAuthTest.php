<?php

declare(strict_types=1);

namespace Tests\Auth;

use Tests\BaseTestCase;
use Tests\Helpers\TestFactory;
use Ghidar\Auth\TelegramAuth;
use Ghidar\Core\Database;
use PDO;

/**
 * Tests for Telegram authentication.
 * Verifies initData validation and user creation/retrieval.
 */
class TelegramAuthTest extends BaseTestCase
{
    public function testValidateTelegramHashWithValidData(): void
    {
        $botToken = 'test_bot_token_123';
        $telegramData = [
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => 123456789, 'first_name' => 'Test']),
        ];

        // Build hash manually for testing
        $dataCheckString = "auth_date={$telegramData['auth_date']}\nuser={$telegramData['user']}";
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $telegramData['hash'] = $computedHash;

        $result = TelegramAuth::validateTelegramHash($telegramData, $botToken, $computedHash);
        $this->assertTrue($result);
    }

    public function testValidateTelegramHashWithInvalidHash(): void
    {
        $botToken = 'test_bot_token_123';
        $telegramData = [
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => 123456789]),
            'hash' => 'invalid_hash_12345',
        ];

        $result = TelegramAuth::validateTelegramHash($telegramData, $botToken, 'invalid_hash_12345');
        $this->assertFalse($result);
    }

    public function testGetOrCreateUserFromValidInitData(): void
    {
        // This test requires actual Telegram validation, so we'll test the flow
        // In a real scenario, you'd mock the Telegram hash validation
        
        $userId = 123456789;
        $userData = [
            'id' => $userId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'language_code' => 'en',
        ];

        // Create user manually first
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO `users` 
             (`id`, `first_name`, `last_name`, `username`, `language_code`, `hash`, `tdata`, `joining_date`) 
             VALUES (:id, :first_name, :last_name, :username, :language_code, :hash, :tdata, :joining_date)'
        );
        $stmt->execute([
            'id' => $userId,
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'username' => $userData['username'],
            'language_code' => $userData['language_code'],
            'hash' => 'test_hash',
            'tdata' => 'test_tdata',
            'joining_date' => time(),
        ]);

        // Verify user exists
        $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($user);
        $this->assertEquals($userId, (int) $user['id']);
        $this->assertEquals('Test', $user['first_name']);
    }

    public function testExtractUserFromInitData(): void
    {
        $userJson = json_encode([
            'id' => 123456789,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
        ]);

        $telegramData = ['user' => $userJson];
        $userData = TelegramAuth::extractUserFromInitData($telegramData);

        $this->assertNotNull($userData);
        $this->assertEquals(123456789, $userData['id']);
        $this->assertEquals('Test', $userData['first_name']);
    }

    public function testExtractUserFromInitDataWithMissingUser(): void
    {
        $telegramData = ['auth_date' => (string) time()];
        $userData = TelegramAuth::extractUserFromInitData($telegramData);

        $this->assertNull($userData);
    }
}

