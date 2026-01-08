<?php

declare(strict_types=1);

namespace Ghidar\Validation;

/**
 * Input validator for admin operations.
 * Provides validation methods for all admin API inputs.
 */
class AdminValidator
{
    /**
     * Validate user ID (must be positive integer).
     *
     * @param mixed $userId User ID to validate
     * @return int Validated user ID
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateUserId($userId): int
    {
        $id = filter_var($userId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        if ($id === false) {
            throw new \InvalidArgumentException('Invalid user ID: must be a positive integer');
        }

        return $id;
    }

    /**
     * Validate balance (must be non-negative decimal).
     *
     * @param mixed $balance Balance to validate
     * @return string Validated balance formatted to 8 decimal places
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateBalance($balance): string
    {
        if (!is_numeric($balance)) {
            throw new \InvalidArgumentException('Balance must be numeric');
        }

        $balance = (float)$balance;
        if ($balance < 0) {
            throw new \InvalidArgumentException('Balance cannot be negative');
        }

        if ($balance > 999999999.99999999) {
            throw new \InvalidArgumentException('Balance exceeds maximum allowed');
        }

        return number_format($balance, 8, '.', '');
    }

    /**
     * Validate score (must be non-negative integer).
     *
     * @param mixed $score Score to validate
     * @return int Validated score
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateScore($score): int
    {
        $val = filter_var($score, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX]
        ]);

        if ($val === false) {
            throw new \InvalidArgumentException('Score must be a non-negative integer');
        }

        return $val;
    }

    /**
     * Validate network name (whitelist approach).
     *
     * @param mixed $network Network name to validate
     * @return string Validated network name (lowercase)
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateNetwork($network): string
    {
        $allowed = ['erc20', 'bep20', 'trc20', 'polygon', 'arbitrum', 'optimism', 'avalanche'];
        $network = strtolower(trim((string)$network));

        if (!in_array($network, $allowed, true)) {
            throw new \InvalidArgumentException(
                'Invalid network. Allowed: ' . implode(', ', $allowed)
            );
        }

        return $network;
    }

    /**
     * Validate wallet address format based on network.
     *
     * @param mixed $address Wallet address to validate
     * @param string $network Network identifier
     * @return string Validated wallet address
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateWalletAddress($address, string $network): string
    {
        $address = trim((string)$address);

        switch (strtolower($network)) {
            case 'erc20':
            case 'bep20':
            case 'polygon':
            case 'arbitrum':
            case 'optimism':
            case 'avalanche':
                if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
                    throw new \InvalidArgumentException('Invalid EVM address format');
                }
                break;

            case 'trc20':
                if (!preg_match('/^T[A-Za-z1-9]{33}$/', $address)) {
                    throw new \InvalidArgumentException('Invalid Tron address format');
                }
                break;

            default:
                throw new \InvalidArgumentException('Unknown network for address validation');
        }

        return $address;
    }

    /**
     * Validate level (must be non-negative integer within reasonable range).
     *
     * @param mixed $level Level to validate
     * @param int $maxLevel Maximum allowed level (default 100)
     * @return int Validated level
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateLevel($level, int $maxLevel = 100): int
    {
        $val = filter_var($level, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => $maxLevel]
        ]);

        if ($val === false) {
            throw new \InvalidArgumentException(
                "Level must be a non-negative integer between 0 and {$maxLevel}"
            );
        }

        return $val;
    }

    /**
     * Validate mission ID (must be positive integer).
     *
     * @param mixed $missionId Mission ID to validate
     * @return int Validated mission ID
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateMissionId($missionId): int
    {
        $id = filter_var($missionId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        if ($id === false) {
            throw new \InvalidArgumentException('Invalid mission ID: must be a positive integer');
        }

        return $id;
    }

    /**
     * Validate task ID (must be positive integer).
     *
     * @param mixed $taskId Task ID to validate
     * @return int Validated task ID
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateTaskId($taskId): int
    {
        $id = filter_var($taskId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        if ($id === false) {
            throw new \InvalidArgumentException('Invalid task ID: must be a positive integer');
        }

        return $id;
    }

    /**
     * Validate text message (sanitize and check length).
     *
     * @param mixed $text Text to validate
     * @param int $maxLength Maximum allowed length (default 4096)
     * @return string Validated and sanitized text
     * @throws \InvalidArgumentException If validation fails
     */
    public static function validateText($text, int $maxLength = 4096): string
    {
        $text = trim((string)$text);

        if (empty($text)) {
            throw new \InvalidArgumentException('Text cannot be empty');
        }

        if (strlen($text) > $maxLength) {
            throw new \InvalidArgumentException("Text exceeds maximum length of {$maxLength} characters");
        }

        // Basic sanitization (HTML entities)
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

