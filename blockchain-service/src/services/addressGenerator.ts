/**
 * Address generator service for deterministic deposit address generation.
 * Generates unique addresses per (userId, network, purpose) combination.
 */

import { ethers } from 'ethers';
import TronWeb from 'tronweb';
import { Config } from '../config';

/**
 * Generate a deterministic deposit address for a user/network/purpose combination.
 * Uses mnemonic-based derivation for EVM chains and TronWeb for Tron.
 *
 * @param userId User ID
 * @param network Network identifier ('erc20', 'bep20', 'trc20')
 * @param purpose Purpose identifier ('wallet_topup', 'lottery', 'ai_trader')
 * @param config Configuration object
 * @returns Deposit address
 */
export function generateDepositAddress(
  userId: number,
  network: string,
  purpose: string,
  config: Config
): string {
  // Generate deterministic index from userId and purpose
  const seed = `${userId}|${purpose}`;
  const hash = ethers.keccak256(ethers.toUtf8Bytes(seed));
  const index = BigInt(hash) % BigInt(2147483647); // Use modulo to get reasonable index

  switch (network) {
    case 'erc20':
      return generateEVMAddress(config.depositMnemonics.eth, index, 60); // Ethereum coin type

    case 'bep20':
      return generateEVMAddress(config.depositMnemonics.bsc, index, 60); // BSC uses Ethereum coin type

    case 'trc20':
      return generateTronAddress(config.depositMnemonics.tron, index);

    default:
      throw new Error(`Unsupported network: ${network}`);
  }
}

/**
 * Generate EVM-compatible address (Ethereum/BSC) from mnemonic and index.
 */
function generateEVMAddress(
  mnemonic: string,
  index: bigint,
  coinType: number
): string {
  if (!mnemonic) {
    throw new Error('Mnemonic not configured for this network');
  }

  // Derive wallet using BIP44 path: m/44'/coinType'/0'/0/index
  const path = `m/44'/${coinType}'/0'/0/${index}`;
  const wallet = ethers.HDNodeWallet.fromPhrase(mnemonic, path);
  return wallet.address;
}

/**
 * Generate Tron address from mnemonic and index.
 */
function generateTronAddress(mnemonic: string, index: bigint): string {
  if (!mnemonic) {
    throw new Error('Mnemonic not configured for this network');
  }

  // For Tron, we'll use a similar approach but with TronWeb
  // Note: TronWeb's mnemonic support may vary, so we use a deterministic approach
  // Generate seed from mnemonic + index
  const seed = `${mnemonic}|${index}`;
  const hash = ethers.keccak256(ethers.toUtf8Bytes(seed));

  // Tron addresses are base58-encoded and start with 'T'
  // For now, we'll use a simplified approach - in production, use proper Tron key derivation
  // This is a placeholder - actual Tron address generation should use TronWeb's proper methods
  try {
    // Use TronWeb to generate address from private key derived from hash
    const privateKey = hash.slice(2, 66); // Remove '0x' prefix
    const tronWeb = new TronWeb({
      fullHost: 'https://api.trongrid.io', // Placeholder, actual host from config
    });
    const address = tronWeb.address.fromPrivateKey(privateKey);
    return address;
  } catch (error) {
    // Fallback: generate a deterministic address format
    // In production, this should be properly implemented with TronWeb
    throw new Error(
      `Tron address generation not fully implemented. Error: ${error}`
    );
  }
}

