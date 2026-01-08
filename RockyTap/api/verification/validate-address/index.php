<?php

declare(strict_types=1);

/**
 * Validate Wallet Address API endpoint
 * POST /api/verification/validate-address
 * Validates wallet address format for a given network
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Security\RateLimiter;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
    exit;
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Rate limiting: max 100 validations per hour
    if (!RateLimiter::checkAndIncrement($userId, 'verification_validate_address', 100, 3600)) {
        Response::jsonError('RATE_LIMIT_EXCEEDED', 'Too many address validation requests', 429);
        exit;
    }

    // Read and parse JSON input
    $input = file_get_contents('php://input');
    if ($input === false) {
        Response::jsonError('INVALID_INPUT', 'Request body is required', 400);
        exit;
    }

    $data = json_decode($input, true);
    if ($data === null) {
        Response::jsonError('INVALID_JSON', 'Invalid JSON in request body', 400);
        exit;
    }

    // Validate required fields
    $walletAddress = $data['wallet_address'] ?? null;
    $walletNetwork = $data['wallet_network'] ?? null;

    if (!$walletAddress || !$walletNetwork) {
        Response::jsonError('MISSING_FIELDS', 'wallet_address and wallet_network are required', 400);
        exit;
    }

    // Validate network
    $validNetworks = ['erc20', 'bep20', 'trc20'];
    if (!in_array(strtolower($walletNetwork), $validNetworks, true)) {
        Response::jsonError('INVALID_NETWORK', 'Invalid wallet network. Must be one of: erc20, bep20, trc20', 400);
        exit;
    }

    $walletNetwork = strtolower($walletNetwork);

    // Validate address format
    $isValid = self::validateWalletAddress($walletAddress, $walletNetwork);
    $checksumValid = false;
    $normalizedAddress = $walletAddress;

    if ($isValid) {
        // For Ethereum/BSC addresses, check checksum
        if ($walletNetwork === 'erc20' || $walletNetwork === 'bep20') {
            $checksumValid = self::validateEthereumChecksum($walletAddress);
            $normalizedAddress = strtolower($walletAddress);
        } else {
            // Tron addresses are case-sensitive
            $normalizedAddress = $walletAddress;
            $checksumValid = true; // Tron addresses don't use checksum
        }
    }

    Response::jsonSuccess([
        'wallet_address' => $walletAddress,
        'wallet_network' => $walletNetwork,
        'is_valid' => $isValid,
        'checksum_valid' => $checksumValid,
        'normalized_address' => $normalizedAddress,
        'format_details' => self::getFormatDetails($walletNetwork, $isValid)
    ]);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while validating address', 500);
}

/**
 * Validate wallet address format.
 */
function validateWalletAddress(string $address, string $network): bool
{
    if ($network === 'erc20' || $network === 'bep20') {
        // Ethereum/BSC: 0x followed by 40 hex characters
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    } elseif ($network === 'trc20') {
        // Tron: T followed by 33 base58 characters
        return preg_match('/^T[A-Za-z1-9]{33}$/', $address) === 1;
    }
    return false;
}

/**
 * Validate Ethereum address checksum (EIP-55).
 */
function validateEthereumChecksum(string $address): bool
{
    // Remove 0x prefix
    $address = strtolower(substr($address, 2));
    
    // Calculate checksum
    $hash = hash('sha3', '0x' . $address);
    $checksumAddress = '0x';
    
    for ($i = 0; $i < 40; $i++) {
        $checksumAddress .= (hexdec($hash[$i]) >= 8) 
            ? strtoupper($address[$i]) 
            : $address[$i];
    }
    
    // Compare with original (case-sensitive)
    return hash_equals($checksumAddress, '0x' . $address);
}

/**
 * Get format details for the address.
 */
function getFormatDetails(string $network, bool $isValid): array
{
    if (!$isValid) {
        return [
            'expected_format' => $network === 'trc20' 
                ? 'T followed by 33 base58 characters (e.g., Txxxxxxxxxxxxxxxxxxxxxxxxxxxxx)' 
                : '0x followed by 40 hexadecimal characters (e.g., 0x1234567890abcdef...)',
            'length' => $network === 'trc20' ? 34 : 42,
            'example' => $network === 'trc20' 
                ? 'Txxxxxxxxxxxxxxxxxxxxxxxxxxxxx' 
                : '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb'
        ];
    }

    return [
        'format' => 'valid',
        'length' => $network === 'trc20' ? 34 : 42,
        'case_sensitive' => $network === 'trc20' ? true : false,
        'checksum_supported' => ($network === 'erc20' || $network === 'bep20')
    ];
}

