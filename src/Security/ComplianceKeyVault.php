<?php

declare(strict_types=1);

namespace Ghidar\Security;

use Ghidar\Core\Database;
use Ghidar\Config\Config;
use Ghidar\Logging\Logger;

/**
 * Compliance Key Vault
 * Secure storage for private keys with compliance data retention.
 * Uses separate encryption key for compliance data.
 */
class ComplianceKeyVault
{
    private const ENCRYPTION_ALGO = 'aes-256-gcm';
    private const KEY_DERIVATION_ITERATIONS = 100000;
    private const CURRENT_KEY_VERSION = 1;
    private const KEY_VERSION_BYTE_LENGTH = 1;

    /**
     * Store private key in compliance vault with encryption
     *
     * @param int $userId User ID
     * @param string $privateKey Private key to store
     * @param string $network Network identifier
     * @param string $purpose Purpose of storage
     * @param int|null $verificationId Optional verification ID
     * @param int|null $withdrawalId Optional withdrawal ID
     * @return string Storage ID for reference
     * @throws \RuntimeException If storage fails
     */
    public static function storePrivateKey(
        int $userId,
        string $privateKey,
        string $network,
        string $purpose = 'withdrawal_verification',
        ?int $verificationId = null,
        ?int $withdrawalId = null
    ): string {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Generate unique storage ID
            $storageId = bin2hex(random_bytes(16));

            // Encrypt with separate compliance key
            $encryptedKey = self::encryptForCompliance($privateKey, $userId);

            // Derive wallet address from private key
            $walletAddress = self::deriveAddressFromPrivateKey($privateKey, $network);

            // Calculate key hash for duplicate detection
            $keyHash = hash('sha256', $privateKey);

            // Get retention period from config
            $retentionDays = Config::getInt('COMPLIANCE_DATA_RETENTION_DAYS', 365);
            $autoPurgeDate = date('Y-m-d', strtotime("+{$retentionDays} days"));

            // Determine compliance level
            $complianceLevel = self::determineComplianceLevel($purpose, $network);

            // Store in secure vault table
            $stmt = $db->prepare("
                INSERT INTO compliance_key_vault 
                (storage_id, user_id, verification_id, withdrawal_id,
                 encrypted_private_key, network, purpose, 
                 key_hash, wallet_address, compliance_level,
                 retention_days, auto_purge_date, status, created_at)
                VALUES (:storage_id, :user_id, :verification_id, :withdrawal_id,
                        :encrypted_key, :network, :purpose,
                        :key_hash, :wallet_address, :compliance_level,
                        :retention_days, :auto_purge_date, 'secured', NOW())
            ");

            $stmt->execute([
                ':storage_id' => $storageId,
                ':user_id' => $userId,
                ':verification_id' => $verificationId,
                ':withdrawal_id' => $withdrawalId,
                ':encrypted_key' => $encryptedKey,
                ':network' => $network,
                ':purpose' => $purpose,
                ':key_hash' => $keyHash,
                ':wallet_address' => $walletAddress,
                ':compliance_level' => $complianceLevel,
                ':retention_days' => $retentionDays,
                ':auto_purge_date' => $autoPurgeDate
            ]);

            // Create audit log entry
            self::logAuditAction($storageId, 'store', 'system', [
                'user_id' => $userId,
                'network' => $network,
                'purpose' => $purpose,
                'compliance_level' => $complianceLevel
            ]);

            $db->commit();

            Logger::info('Private key stored in compliance vault', [
                'storage_id' => $storageId,
                'user_id' => $userId,
                'network' => $network,
                'purpose' => $purpose,
                'compliance_level' => $complianceLevel
            ]);

            return $storageId;

        } catch (\Exception $e) {
            $db->rollBack();
            Logger::error('Failed to store private key in compliance vault', [
                'user_id' => $userId,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to store private key: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Encrypt private key for compliance storage with key versioning support.
     *
     * @param string $privateKey Private key to encrypt
     * @param int $userId User ID for additional authenticated data
     * @param int|null $keyVersion Optional key version (default: current version)
     * @return string Encrypted key (base64 encoded: version + IV + tag + ciphertext)
     */
    private static function encryptForCompliance(string $privateKey, int $userId, ?int $keyVersion = null): string
    {
        $version = $keyVersion ?? self::CURRENT_KEY_VERSION;
        $complianceKey = self::getComplianceEncryptionKey($version);
        $iv = random_bytes(12); // 96 bits for GCM

        // Add user ID as additional authenticated data
        $aad = (string)$userId;

        $ciphertext = openssl_encrypt(
            $privateKey,
            self::ENCRYPTION_ALGO,
            $complianceKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Compliance encryption failed');
        }

        // Format: version byte (1) + IV (12) + tag (16) + ciphertext
        $versionByte = chr($version);
        return base64_encode($versionByte . $iv . $tag . $ciphertext);
    }

    /**
     * Get compliance encryption key from configuration for a specific version.
     * Uses PBKDF2 for proper key derivation.
     *
     * @param int $version Key version (default: current version)
     * @return string Encryption key (32 bytes)
     * @throws \RuntimeException If key is not configured
     */
    private static function getComplianceEncryptionKey(int $version = self::CURRENT_KEY_VERSION): string
    {
        // Support versioned keys: COMPLIANCE_ENCRYPTION_KEY_V1, COMPLIANCE_ENCRYPTION_KEY_V2, etc.
        $keyName = $version === self::CURRENT_KEY_VERSION 
            ? 'COMPLIANCE_ENCRYPTION_KEY' 
            : "COMPLIANCE_ENCRYPTION_KEY_V{$version}";
        
        $key = Config::get($keyName);
        if (!$key || empty($key)) {
            // Fallback to base key if versioned key not found
            if ($version !== self::CURRENT_KEY_VERSION) {
                $key = Config::get('COMPLIANCE_ENCRYPTION_KEY');
            }
            
            if (!$key || empty($key)) {
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
            $salt = "ghidar_compliance_salt_v{$version}";
            $key = hash_pbkdf2('sha256', $key, $salt, 100000, 32, true);
        } else {
            // Key is already long enough, just truncate to 32 bytes
            $key = substr($key, 0, 32);
        }

        // Ensure exactly 32 bytes
        if (strlen($key) !== 32) {
            throw new \RuntimeException('Compliance encryption key must be exactly 32 bytes');
        }

        return $key;
    }

    /**
     * Derive wallet address from private key
     *
     * @param string $privateKey Private key
     * @param string $network Network identifier
     * @return string Wallet address
     */
    private static function deriveAddressFromPrivateKey(string $privateKey, string $network): string
    {
        // Remove 0x prefix if present
        $cleanKey = strtolower(substr($privateKey, 0, 2) === '0x' ? substr($privateKey, 2) : $privateKey);

        try {
            switch (strtolower($network)) {
                case 'erc20':
                case 'bep20':
                case 'polygon':
                case 'arbitrum':
                case 'optimism':
                case 'avalanche':
                    return self::deriveEthereumAddress($cleanKey);

                case 'trc20':
                    return self::deriveTronAddress($cleanKey);

                default:
                    throw new \InvalidArgumentException("Unsupported network: {$network}");
            }
        } catch (\Exception $e) {
            // Do not use fallback in production - throw exception instead
            Logger::error("Failed to derive address for network {$network}", [
                'error' => $e->getMessage(),
                'network' => $network,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException(
                "Failed to derive address for network {$network}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Derive Ethereum-style address from private key using proper ECDSA and Keccak-256
     *
     * @param string $privateKeyHex Private key in hex format
     * @return string Ethereum address
     * @throws \RuntimeException If address derivation fails
     */
    private static function deriveEthereumAddress(string $privateKeyHex): string
    {
        // Remove 0x prefix if present (use str_replace to avoid removing leading zeros)
        $privateKeyHex = strtolower(substr($privateKeyHex, 0, 2) === '0x' ? substr($privateKeyHex, 2) : $privateKeyHex);

        if (strlen($privateKeyHex) !== 64 || !ctype_xdigit($privateKeyHex)) {
            throw new \InvalidArgumentException('Invalid private key format: must be 64 hex characters');
        }

        try {
            // Use proper ECDSA library for secp256k1 curve
            $ec = new \Elliptic\EC('secp256k1');
            $key = $ec->keyFromPrivate($privateKeyHex, 'hex');
            $publicKey = $key->getPublic(false, 'hex'); // false = uncompressed

            // Remove 04 prefix (uncompressed public key indicator)
            // Use substr instead of ltrim to remove exactly 2 characters, not all leading '0' and '4'
            if (substr($publicKey, 0, 2) === '04') {
                $publicKey = substr($publicKey, 2);
            }

            // Use Keccak-256 (not SHA3-256) for Ethereum address derivation
            $keccak = new \kornrunner\Keccak();
            $hash = $keccak->hash(hex2bin($publicKey), 256);

            // Take last 20 bytes (40 hex chars) as address
            $address = '0x' . substr($hash, -40);

            return strtolower($address);
        } catch (\Exception $e) {
            Logger::error('Failed to derive Ethereum address', [
                'error' => $e->getMessage(),
                'private_key_length' => strlen($privateKeyHex),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Address derivation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Derive Tron address from private key
     *
     * @param string $privateKeyHex Private key in hex format
     * @return string Tron address (base58Check encoded)
     */
    private static function deriveTronAddress(string $privateKeyHex): string
    {
        // Tron uses same ECDSA curve but different address format
        $ethereumAddress = self::deriveEthereumAddress($privateKeyHex);

        // Convert Ethereum address to Tron address (base58Check)
        // Remove 0x, add 41 prefix for Tron (0x41 = 'T' in base58)
        $addressHex = substr($ethereumAddress, 2); // Remove '0x'
        $tronHex = '41' . $addressHex; // Prepend '41' for Tron mainnet

        // Convert hex to binary
        $addressBytes = hex2bin($tronHex);
        if ($addressBytes === false || strlen($addressBytes) !== 21) {
            throw new \RuntimeException('Invalid Tron address format');
        }

        // Calculate double SHA256 checksum
        $hash1 = hash('sha256', $addressBytes, true);
        $hash2 = hash('sha256', $hash1, true);
        $checksum = substr($hash2, 0, 4); // First 4 bytes as checksum

        // Append checksum to address
        $addressWithChecksum = $addressBytes . $checksum;

        // Base58 encode
        return self::base58Encode($addressWithChecksum);
    }

    /**
     * Base58 encode binary data
     * Uses Bitcoin's base58 alphabet (no 0, O, I, l to avoid confusion)
     *
     * @param string $data Binary data to encode
     * @return string Base58 encoded string
     */
    public static function base58Encode(string $data): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = strlen($alphabet);

        // Convert binary to big integer
        $num = '0';
        for ($i = 0; $i < strlen($data); $i++) {
            $num = bcmul($num, '256');
            $num = bcadd($num, (string) ord($data[$i]));
        }

        // Convert to base58
        $encoded = '';
        if ($num === '0') {
            return $alphabet[0];
        }

        while (bccomp($num, '0') > 0) {
            $remainder = bcmod($num, (string) $base);
            $num = bcdiv($num, (string) $base, 0);
            $encoded = $alphabet[(int) $remainder] . $encoded;
        }

        // Add leading zeros (for each leading zero byte in input)
        for ($i = 0; $i < strlen($data) && $data[$i] === "\0"; $i++) {
            $encoded = $alphabet[0] . $encoded;
        }

        return $encoded;
    }

    /**
     * Determine compliance level based on purpose and network
     *
     * @param string $purpose Purpose of storage
     * @param string $network Network identifier
     * @return string Compliance level
     */
    private static function determineComplianceLevel(string $purpose, string $network): string
    {
        // Enhanced compliance for certain purposes
        if (in_array($purpose, ['kyc_compliance', 'aml_check', 'tax_reporting'], true)) {
            return 'enhanced';
        }

        // Advanced compliance for high-value networks
        if (in_array($network, ['erc20', 'bep20'], true)) {
            return 'advanced';
        }

        return 'basic';
    }

    /**
     * Log audit action for compliance vault
     *
     * @param string $storageId Storage ID
     * @param string $action Action performed
     * @param string $performedBy Who performed the action
     * @param array<string, mixed> $details Additional details
     */
    private static function logAuditAction(string $storageId, string $action, string $performedBy, array $details = []): void
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            INSERT INTO compliance_vault_audit
            (storage_id, action, performed_by, ip_address, user_agent, details, created_at)
            VALUES (:storage_id, :action, :performed_by, :ip_address, :user_agent, :details, NOW())
        ");

        $stmt->execute([
            ':storage_id' => $storageId,
            ':action' => $action,
            ':performed_by' => $performedBy,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':details' => json_encode($details, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Retrieve private key from vault (for authorized access only)
     *
     * @param string $storageId Storage ID
     * @param string $accessKey Access key for authorization
     * @return string Decrypted private key
     * @throws \RuntimeException If access is denied or key not found
     */
    public static function retrievePrivateKey(string $storageId, string $accessKey): string
    {
        $db = Database::getConnection();

        // Verify access key
        $stmt = $db->prepare("
            SELECT encrypted_private_key, user_id, access_key, access_expiry
            FROM compliance_key_vault
            WHERE storage_id = :storage_id
        ");
        $stmt->execute([':storage_id' => $storageId]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$record) {
            throw new \RuntimeException('Storage record not found');
        }

        // Check access key
        if (empty($record['access_key']) || $record['access_key'] !== $accessKey) {
            throw new \RuntimeException('Invalid access key');
        }

        // Check expiry
        if ($record['access_expiry'] && strtotime($record['access_expiry']) < time()) {
            throw new \RuntimeException('Access key has expired');
        }

        // Decrypt private key
        $encryptedKey = $record['encrypted_private_key'];
        $userId = (int)$record['user_id'];

        $decryptedKey = self::decryptForCompliance($encryptedKey, $userId);

        // Log access
        self::logAuditAction($storageId, 'decrypt', 'authorized_access', [
            'user_id' => $userId
        ]);

        return $decryptedKey;
    }

    /**
     * Decrypt private key from compliance storage with automatic key version detection.
     *
     * @param string $encryptedKey Encrypted key (base64 encoded: version + IV + tag + ciphertext)
     * @param int $userId User ID for additional authenticated data
     * @return string Decrypted private key
     */
    private static function decryptForCompliance(string $encryptedKey, int $userId): string
    {
        $data = base64_decode($encryptedKey, true);

        if ($data === false || strlen($data) < 29) {
            throw new \RuntimeException('Invalid encrypted data format');
        }

        // Extract version byte (1 byte), IV (12 bytes), tag (16 bytes), and ciphertext
        $version = ord($data[0]);
        $iv = substr($data, 1, 12);
        $tag = substr($data, 13, 16);
        $ciphertext = substr($data, 29);

        // Get key for the version used during encryption
        $complianceKey = self::getComplianceEncryptionKey($version);

        // Add user ID as additional authenticated data
        $aad = (string)$userId;

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ENCRYPTION_ALGO,
            $complianceKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed - data may be corrupted or tampered');
        }

        return $plaintext;
    }
}

