<?php

declare(strict_types=1);

/**
 * Get Signing Instructions API endpoint
 * GET /api/verification/signing-instructions
 * Gets wallet-specific signing instructions
 */

require_once __DIR__ . '/../../../bootstrap.php';

use Ghidar\Core\Response;
use Ghidar\Core\UserContext;
use Ghidar\Core\Database;

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::jsonError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
    exit;
}

try {
    // Authenticate user
    $context = UserContext::requireCurrentUserWithWallet();
    $user = $context['user'];
    $userId = (int) $user['id'];

    // Get wallet network from query string
    $walletNetwork = $_GET['wallet_network'] ?? null;
    $verificationId = isset($_GET['verification_id']) ? (int) $_GET['verification_id'] : null;

    if (!$walletNetwork) {
        Response::jsonError('MISSING_NETWORK', 'wallet_network parameter is required', 400);
        exit;
    }

    // Validate network
    $validNetworks = ['erc20', 'bep20', 'trc20'];
    if (!in_array(strtolower($walletNetwork), $validNetworks, true)) {
        Response::jsonError('INVALID_NETWORK', 'Invalid wallet network. Must be one of: erc20, bep20, trc20', 400);
        exit;
    }

    $walletNetwork = strtolower($walletNetwork);

    // Get verification details if verification ID provided
    $messageToSign = null;
    $messageNonce = null;
    if ($verificationId) {
        $db = Database::ensureConnection();
        $stmt = $db->prepare(
            'SELECT `message_to_sign`, `message_nonce`, `wallet_address`, `wallet_network`
             FROM `wallet_verifications`
             WHERE `id` = :verification_id AND `user_id` = :user_id LIMIT 1'
        );
        $stmt->execute([
            'verification_id' => $verificationId,
            'user_id' => $userId
        ]);
        $verification = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($verification) {
            $messageToSign = $verification['message_to_sign'];
            $messageNonce = $verification['message_nonce'];
            $walletNetwork = $verification['wallet_network']; // Use network from verification
        }
    }

    // Get network-specific instructions
    $instructions = self::getNetworkInstructions($walletNetwork);

    $response = [
        'network' => $walletNetwork,
        'instructions' => $instructions,
        'message_to_sign' => $messageToSign,
        'message_nonce' => $messageNonce
    ];

    Response::jsonSuccess($response);

} catch (\Exception $e) {
    Response::jsonError('INTERNAL_ERROR', 'An error occurred while fetching signing instructions', 500);
}

/**
 * Get network-specific signing instructions.
 */
function getNetworkInstructions(string $network): array
{
    $instructions = [
        'erc20' => [
            'title' => 'Ethereum (ERC20) Wallet Signing Instructions',
            'steps' => [
                '1. Open your Ethereum wallet (MetaMask, Trust Wallet, etc.)',
                '2. Navigate to the signing interface',
                '3. Copy the verification message provided',
                '4. Use your wallet\'s "Sign Message" feature',
                '5. Paste the message and sign it',
                '6. Copy the signature (starts with 0x)',
                '7. Submit the signature to complete verification'
            ],
            'common_wallets' => ['MetaMask', 'Trust Wallet', 'Coinbase Wallet', 'Ledger', 'Trezor'],
            'signature_format' => '0x followed by 130 hexadecimal characters',
            'troubleshooting' => [
                'If signing fails, ensure you are using the correct wallet address',
                'Make sure you copy the entire message including all lines',
                'The signature should be exactly 132 characters (0x + 130 hex chars)'
            ]
        ],
        'bep20' => [
            'title' => 'Binance Smart Chain (BEP20) Wallet Signing Instructions',
            'steps' => [
                '1. Open your BSC wallet (MetaMask, Trust Wallet, etc.)',
                '2. Ensure you are connected to Binance Smart Chain network',
                '3. Navigate to the signing interface',
                '4. Copy the verification message provided',
                '5. Use your wallet\'s "Sign Message" feature',
                '6. Paste the message and sign it',
                '7. Copy the signature (starts with 0x)',
                '8. Submit the signature to complete verification'
            ],
            'common_wallets' => ['MetaMask', 'Trust Wallet', 'Binance Chain Wallet'],
            'signature_format' => '0x followed by 130 hexadecimal characters',
            'troubleshooting' => [
                'Ensure you are on the BSC network, not Ethereum mainnet',
                'If signing fails, check that your wallet supports BSC message signing',
                'The signature format is the same as Ethereum (0x + 130 hex chars)'
            ]
        ],
        'trc20' => [
            'title' => 'Tron (TRC20) Wallet Signing Instructions',
            'steps' => [
                '1. Open your Tron wallet (TronLink, Trust Wallet, etc.)',
                '2. Navigate to the signing interface',
                '3. Copy the verification message provided',
                '4. Use your wallet\'s message signing feature',
                '5. Sign the message with your private key',
                '6. Copy the signature (hex or base64 format)',
                '7. Submit the signature to complete verification'
            ],
            'common_wallets' => ['TronLink', 'Trust Wallet', 'TronWallet'],
            'signature_format' => 'Hexadecimal or base64 encoded signature (minimum 64 characters)',
            'troubleshooting' => [
                'Tron wallets may use different signature formats',
                'Ensure your wallet supports message signing (not all Tron wallets do)',
                'If your wallet doesn\'t support signing, use the assisted verification method'
            ]
        ]
    ];

    return $instructions[strtolower($network)] ?? [
        'title' => 'Wallet Signing Instructions',
        'steps' => ['Please contact support for signing instructions for this network'],
        'common_wallets' => [],
        'signature_format' => 'Unknown',
        'troubleshooting' => []
    ];
}

