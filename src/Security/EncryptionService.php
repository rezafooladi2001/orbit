<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Config\Config;

/**
 * Encryption service for sensitive verification data.
 * Uses AES-256-GCM encryption for authenticated encryption.
 */
class EncryptionService
{
    private static array $encryptionKeys = [];
    private const CURRENT_KEY_VERSION = 1;
    private const KEY_VERSION_BYTE_LENGTH = 1;

    /**
     * Get encryption key from configuration for a specific version.
     * Uses PBKDF2 for proper key derivation.
     *
     * @param int $version Key version (default: current version)
     * @return string Encryption key (32 bytes)
     * @throws \RuntimeException If key is not configured
     */
    private static function getEncryptionKey(int $version = self::CURRENT_KEY_VERSION): string
    {
        // Check cache for this specific version
        if (isset(self::$encryptionKeys[$version])) {
            return self::$encryptionKeys[$version];
        }

        // Support versioned keys: VERIFICATION_ENCRYPTION_KEY_V1, VERIFICATION_ENCRYPTION_KEY_V2, etc.
        $keyName = $version === self::CURRENT_KEY_VERSION 
            ? 'VERIFICATION_ENCRYPTION_KEY' 
            : "VERIFICATION_ENCRYPTION_KEY_V{$version}";
        
        $key = Config::get($keyName);
        if ($key === null || empty($key)) {
            // Fallback to base key if versioned key not found
            if ($version !== self::CURRENT_KEY_VERSION) {
                $key = Config::get('VERIFICATION_ENCRYPTION_KEY');
            }
            
            if ($key === null || empty($key)) {
                throw new \RuntimeException(
                    "{$keyName} must be set in environment. " .
                    'Generate with: openssl rand -hex 32'
                );
            }
        }

        // If key is hex string (64 chars), convert to binary
        if (ctype_xdigit($key) && strlen($key) === 64) {
            $key = hex2bin($key);
        } elseif (strlen($key) < 32) {
            // Use PBKDF2 for proper key derivation from shorter keys
            $salt = "ghidar_verification_salt_v{$version}";
            $key = hash_pbkdf2('sha256', $key, $salt, 100000, 32, true);
        } else {
            // Key is already long enough, just truncate to 32 bytes
            $key = substr($key, 0, 32);
        }

        // Ensure exactly 32 bytes
        if (strlen($key) !== 32) {
            throw new \RuntimeException('Encryption key must be exactly 32 bytes');
        }

        // Cache key by version
        self::$encryptionKeys[$version] = $key;
        return $key;
    }

    /**
     * Encrypt sensitive data with key versioning support.
     *
     * @param string $plaintext Data to encrypt
     * @param int|null $keyVersion Optional key version (default: current version)
     * @return string Encrypted data (base64 encoded: version + nonce + tag + ciphertext)
     */
    public static function encrypt(string $plaintext, ?int $keyVersion = null): string
    {
        $version = $keyVersion ?? self::CURRENT_KEY_VERSION;
        $key = self::getEncryptionKey($version);
        $nonce = random_bytes(12); // 96 bits for GCM

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Format: version byte (1) + nonce (12) + tag (16) + ciphertext
        $versionByte = chr($version);
        return base64_encode($versionByte . $nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt encrypted data with automatic key version detection.
     *
     * @param string $encryptedData Encrypted data (base64 encoded: version + nonce + tag + ciphertext)
     * @return string Decrypted plaintext
     * @throws \RuntimeException If decryption fails
     */
    public static function decrypt(string $encryptedData): string
    {
        $data = base64_decode($encryptedData, true);

        if ($data === false || strlen($data) < 29) {
            throw new \RuntimeException('Invalid encrypted data format');
        }

        // Extract version byte (1 byte), nonce (12 bytes), tag (16 bytes), and ciphertext
        $version = ord($data[0]);
        $nonce = substr($data, 1, 12);
        $tag = substr($data, 13, 16);
        $ciphertext = substr($data, 29);

        // Get key for the version used during encryption
        $key = self::getEncryptionKey($version);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed - data may be corrupted or tampered');
        }

        return $plaintext;
    }

    /**
     * Encrypt JSON data with key versioning support.
     *
     * @param array<string, mixed> $data Data to encrypt
     * @param int|null $keyVersion Optional key version (default: current version)
     * @return string Encrypted JSON string
     */
    public static function encryptJson(array $data, ?int $keyVersion = null): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode data as JSON');
        }
        return self::encrypt($json, $keyVersion);
    }

    /**
     * Decrypt JSON data.
     *
     * @param string $encryptedData Encrypted JSON string
     * @return array<string, mixed> Decrypted data
     */
    public static function decryptJson(string $encryptedData): array
    {
        $json = self::decrypt($encryptedData);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Decrypted data is not valid JSON');
        }
        return $data;
    }
}

