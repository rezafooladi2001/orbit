/**
 * Deposit watcher service.
 * Periodically scans blockchain for deposits to pending deposit addresses.
 * When deposits are detected and confirmed, calls PHP backend callback.
 */

import { ethers } from 'ethers';
import { getDb } from '../lib/db';
import { callDepositCallback } from '../lib/httpClient';
import { Config } from '../config';
import { waitForRateLimit, logRateLimitEvent } from '../lib/rateLimiter';

interface PendingDeposit {
  id: number;
  user_id: number;
  network: string;
  address: string;
  expected_amount_usdt: string | null;
}

interface ProcessedDeposit {
  depositId: number;
  address: string;
}

// Track recently processed deposits to avoid double processing
const recentlyProcessed: Map<string, number> = new Map();
const PROCESSED_CACHE_TTL = 600000; // 10 minutes

// Minimum confirmations required per network
const MIN_CONFIRMATIONS = {
  eth: 12,
  bsc: 15,
  tron: 19,
};

// USDT contract ABI (only Transfer event needed)
const USDT_ABI = [
  'event Transfer(address indexed from, address indexed to, uint256 value)',
];

/**
 * Start deposit watcher for all networks.
 */
export function startDepositWatcher(config: Config): void {
  const watchInterval = parseInt(process.env.WATCH_INTERVAL_MS || '60000', 10);

  console.log(`Deposit watcher starting with ${watchInterval}ms interval`);

  // Initial check after 5 seconds
  setTimeout(async () => {
    await runWatcherCycle(config);
  }, 5000);

  // Regular checks
  setInterval(async () => {
    await runWatcherCycle(config);
  }, watchInterval);

  // Cleanup old entries from cache every 5 minutes
  setInterval(() => {
    const now = Date.now();
    for (const [key, timestamp] of recentlyProcessed.entries()) {
      if (now - timestamp > PROCESSED_CACHE_TTL) {
        recentlyProcessed.delete(key);
      }
    }
  }, 300000);

  console.log('Deposit watcher started');
}

/**
 * Run a single watcher cycle.
 */
async function runWatcherCycle(config: Config): Promise<void> {
  try {
    await checkPendingDeposits(config);
  } catch (error) {
    console.error('Error in deposit watcher cycle:', error);
  }
}

/**
 * Check for pending deposits and scan blockchains.
 */
async function checkPendingDeposits(config: Config): Promise<void> {
  const db = getDb();

  // Fetch all pending deposits
  const [deposits] = await db.query<PendingDeposit[]>(
    `SELECT id, user_id, network, address, expected_amount_usdt 
     FROM deposits 
     WHERE status = 'pending' AND address IS NOT NULL 
     ORDER BY network, created_at
     LIMIT 500`
  );

  if (!deposits || deposits.length === 0) {
    return;
  }

  console.log(`Checking ${deposits.length} pending deposits`);

  // Group by network
  const depositsByNetwork: Record<string, PendingDeposit[]> = {};
  for (const deposit of deposits) {
    if (!depositsByNetwork[deposit.network]) {
      depositsByNetwork[deposit.network] = [];
    }
    depositsByNetwork[deposit.network].push(deposit);
  }

  // Process each network in parallel
  const networkPromises = Object.entries(depositsByNetwork).map(
    async ([network, networkDeposits]) => {
      try {
        switch (network) {
          case 'erc20':
            await checkEVMDeposits(networkDeposits, config, 'eth');
            break;
          case 'bep20':
            await checkEVMDeposits(networkDeposits, config, 'bsc');
            break;
          case 'trc20':
            await checkTronDeposits(networkDeposits, config);
            break;
          default:
            console.warn(`Unknown network: ${network}`);
        }
      } catch (error) {
        console.error(`Error checking ${network} deposits:`, error);
      }
    }
  );

  await Promise.all(networkPromises);
}

/**
 * Check EVM network (Ethereum/BSC) for USDT deposits.
 */
async function checkEVMDeposits(
  deposits: PendingDeposit[],
  config: Config,
  networkType: 'eth' | 'bsc'
): Promise<void> {
  const rpcUrl = config.rpc[networkType];
  const contractAddress =
    networkType === 'eth'
      ? config.usdtContracts.erc20
      : config.usdtContracts.bep20;

  if (!rpcUrl || !contractAddress) {
    console.warn(
      `${networkType.toUpperCase()} not configured (missing RPC or contract address)`
    );
    return;
  }

  try {
    // Wait for rate limit before creating provider
    await waitForRateLimit(networkType);
    
    // Create provider with explicit network configuration to avoid network detection issues
    const networkConfigs = {
      eth: { name: 'ethereum', chainId: 1 },
      bsc: { name: 'bsc', chainId: 56 }
    };
    const networkConfig = networkConfigs[networkType];
    
    const provider = new ethers.JsonRpcProvider(rpcUrl, {
      name: networkConfig.name,
      chainId: networkConfig.chainId
    });
    const contract = new ethers.Contract(contractAddress, USDT_ABI, provider);

    // Wait for rate limit before getting block number
    await waitForRateLimit(networkType);
    const currentBlock = await provider.getBlockNumber();
    const fromBlock = currentBlock - 1000; // Check last ~1000 blocks

    // Build address lookup map
    const addressToDeposit: Map<string, PendingDeposit> = new Map();
    for (const deposit of deposits) {
      addressToDeposit.set(deposit.address.toLowerCase(), deposit);
    }

    // Query Transfer events to our deposit addresses
    const addresses = deposits.map((d) => d.address);
    
    // Query events in batches to avoid RPC limits
    const batchSize = 50;
    for (let i = 0; i < addresses.length; i += batchSize) {
      const batchAddresses = addresses.slice(i, i + batchSize);
      
      for (const address of batchAddresses) {
        try {
          // Wait for rate limit before querying events
          await waitForRateLimit(networkType);
          
          const filter = contract.filters.Transfer(null, address);
          const events = await contract.queryFilter(filter, fromBlock, currentBlock);

          for (const event of events) {
            const deposit = addressToDeposit.get(address.toLowerCase());
            if (!deposit) continue;

            // Check if already processed recently
            const cacheKey = `${deposit.id}-${event.transactionHash}`;
            if (recentlyProcessed.has(cacheKey)) {
              continue;
            }

            // Get event args
            const args = (event as ethers.EventLog).args;
            if (!args) continue;

            const amount = ethers.formatUnits(args.value, 6); // USDT has 6 decimals
            const txHash = event.transactionHash;
            const confirmations = currentBlock - event.blockNumber;

            console.log(
              `Found transfer to ${address}: ${amount} USDT, ${confirmations} confirmations`
            );

            // Check minimum confirmations
            if (confirmations >= MIN_CONFIRMATIONS[networkType]) {
              console.log(`Deposit confirmed: ${txHash}`);
              
              // Mark as processed before calling callback
              recentlyProcessed.set(cacheKey, Date.now());

              // Call PHP backend
              const success = await callDepositCallback(config, {
                deposit_id: deposit.id,
                network: networkType === 'eth' ? 'erc20' : 'bep20',
                tx_hash: txHash,
                amount_usdt: amount,
              });

              if (!success) {
                // Remove from cache if callback failed
                recentlyProcessed.delete(cacheKey);
              }
            }
          }
        } catch (error) {
          console.error(`Error querying events for ${address}:`, error);
        }
      }

      // Wait for rate limit between batches
      if (i + batchSize < addresses.length) {
        await waitForRateLimit(networkType);
      }
    }
  } catch (error: any) {
    // Check if it's a rate limit error
    if (error?.message?.includes('rate limit') || error?.code === 429) {
      logRateLimitEvent(networkType, 'Rate limit error detected');
      // Wait and retry once
      await new Promise((resolve) => setTimeout(resolve, 2000));
      logRateLimitEvent(networkType, 'Retrying after rate limit');
    }
    console.error(`Error in ${networkType} deposit check:`, error);
    throw error;
  }
}

/**
 * Check Tron network for USDT deposits.
 */
async function checkTronDeposits(
  deposits: PendingDeposit[],
  config: Config
): Promise<void> {
  const rpcUrl = config.rpc.tron;
  const contractAddress = config.usdtContracts.trc20;

  if (!rpcUrl || !contractAddress) {
    console.warn('Tron not configured (missing RPC or contract address)');
    return;
  }

  try {
    // Import TronWeb dynamically
    const TronWeb = (await import('tronweb')).default;
    
    const tronWeb = new TronWeb({
      fullHost: rpcUrl,
    });

    const currentBlock = await tronWeb.trx.getCurrentBlock();
    const currentBlockNumber = currentBlock.block_header.raw_data.number;

    // Build address lookup map
    const addressToDeposit: Map<string, PendingDeposit> = new Map();
    for (const deposit of deposits) {
      addressToDeposit.set(deposit.address, deposit);
    }

    // Query TRC20 transfer events for each address
    for (const deposit of deposits) {
      try {
        // Wait for rate limit before querying Tron
        await waitForRateLimit('tron');
        
        // Check if already processed recently
        const cacheKeyPrefix = `${deposit.id}-`;
        
        // Get contract events
        const events = await tronWeb.getEventResult(contractAddress, {
          eventName: 'Transfer',
          size: 50,
          filters: {
            to: deposit.address,
          },
        });

        if (!events || events.length === 0) {
          continue;
        }

        for (const event of events) {
          const txHash = event.transaction;
          const cacheKey = `${deposit.id}-${txHash}`;
          
          if (recentlyProcessed.has(cacheKey)) {
            continue;
          }

          // Wait for rate limit before getting transaction
          await waitForRateLimit('tron');
          
          // Get transaction info for block number
          const tx = await tronWeb.trx.getTransaction(txHash);
          if (!tx || !tx.raw_data || !tx.raw_data.ref_block_bytes) {
            continue;
          }

          // Calculate confirmations (approximate for Tron)
          const txBlock = parseInt(tx.raw_data.ref_block_bytes, 16);
          const confirmations = currentBlockNumber - txBlock;

          // Parse amount (TRC20 USDT has 6 decimals)
          const amount = (parseInt(event.result.value) / 1000000).toFixed(8);

          console.log(
            `Found Tron transfer to ${deposit.address}: ${amount} USDT, ~${confirmations} confirmations`
          );

          if (confirmations >= MIN_CONFIRMATIONS.tron) {
            console.log(`Tron deposit confirmed: ${txHash}`);
            
            recentlyProcessed.set(cacheKey, Date.now());

            const success = await callDepositCallback(config, {
              deposit_id: deposit.id,
              network: 'trc20',
              tx_hash: txHash,
              amount_usdt: amount,
            });

            if (!success) {
              recentlyProcessed.delete(cacheKey);
            }
          }
        }
      } catch (error: any) {
        // Check if it's a rate limit error
        if (error?.message?.includes('rate limit') || error?.code === 429) {
          logRateLimitEvent('tron', 'Rate limit error detected');
          await new Promise((resolve) => setTimeout(resolve, 2000));
        }
        console.error(`Error checking Tron deposit ${deposit.id}:`, error);
      }

      // Wait for rate limit between deposits
      await waitForRateLimit('tron');
    }
  } catch (error) {
    console.error('Error in Tron deposit check:', error);
    throw error;
  }
}

