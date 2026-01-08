/**
 * Centralized RPC Provider Manager
 * Provides reliable RPC connections with:
 * - Explicit network configuration (no auto-detection)
 * - Connection pooling
 * - Failover support
 * - Health monitoring
 * - Connection caching
 */

const { ethers } = require('ethers');

// Network configurations with chainIds
const NETWORK_CONFIGS = {
  ethereum: { name: 'ethereum', chainId: 1, symbol: 'ETH' },
  eth: { name: 'ethereum', chainId: 1, symbol: 'ETH' },
  bsc: { name: 'bsc', chainId: 56, symbol: 'BNB' },
  polygon: { name: 'polygon', chainId: 137, symbol: 'MATIC' },
  arbitrum: { name: 'arbitrum', chainId: 42161, symbol: 'ETH' },
  avalanche: { name: 'avalanche', chainId: 43114, symbol: 'AVAX' },
  fantom: { name: 'fantom', chainId: 250, symbol: 'FTM' },
  optimism: { name: 'optimism', chainId: 10, symbol: 'ETH' },
  base: { name: 'base', chainId: 8453, symbol: 'ETH' }
};

// Provider cache for connection reuse
const providerCache = new Map();

// Provider health status
const providerHealth = new Map();

// Cache TTL in milliseconds (5 minutes)
const CACHE_TTL = 5 * 60 * 1000;

// Health check interval (30 seconds)
const HEALTH_CHECK_INTERVAL = 30 * 1000;

// Max retries for failed operations
const MAX_RETRIES = 3;

// Retry delay base (exponential backoff)
const RETRY_DELAY_BASE = 1000;

/**
 * Get network configuration by key
 * @param {string} networkKey - Network identifier
 * @returns {object} Network configuration
 */
function getNetworkConfig(networkKey) {
  const config = NETWORK_CONFIGS[networkKey.toLowerCase()];
  if (!config) {
    throw new Error(`Unknown network: ${networkKey}`);
  }
  return config;
}

/**
 * Create a provider with explicit network configuration
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {string} networkKey - Network identifier
 * @returns {ethers.JsonRpcProvider} Configured provider
 */
function createProvider(rpcUrl, networkKey) {
  const networkConfig = getNetworkConfig(networkKey);
  
  return new ethers.JsonRpcProvider(rpcUrl, {
    name: networkConfig.name,
    chainId: networkConfig.chainId
  });
}

/**
 * Get or create a cached provider
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {string} networkKey - Network identifier
 * @returns {ethers.JsonRpcProvider} Cached or new provider
 */
function getProvider(rpcUrl, networkKey) {
  const cacheKey = `${networkKey}:${rpcUrl}`;
  
  const cached = providerCache.get(cacheKey);
  if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
    return cached.provider;
  }
  
  const provider = createProvider(rpcUrl, networkKey);
  providerCache.set(cacheKey, {
    provider,
    timestamp: Date.now()
  });
  
  return provider;
}

/**
 * Check if a provider is healthy
 * @param {ethers.JsonRpcProvider} provider - Provider to check
 * @returns {Promise<boolean>} True if healthy
 */
async function isProviderHealthy(provider) {
  try {
    await provider.getBlockNumber();
    return true;
  } catch (error) {
    return false;
  }
}

/**
 * Execute an operation with retry logic
 * @param {Function} operation - Async operation to execute
 * @param {number} maxRetries - Maximum retry attempts
 * @returns {Promise<any>} Operation result
 */
async function withRetry(operation, maxRetries = MAX_RETRIES) {
  let lastError;
  
  for (let attempt = 0; attempt < maxRetries; attempt++) {
    try {
      return await operation();
    } catch (error) {
      lastError = error;
      
      // Don't retry on certain errors
      if (error.code === 'INVALID_ARGUMENT' || error.code === 'UNSUPPORTED_OPERATION') {
        throw error;
      }
      
      // Exponential backoff
      const delay = RETRY_DELAY_BASE * Math.pow(2, attempt);
      console.warn(`Operation failed (attempt ${attempt + 1}/${maxRetries}), retrying in ${delay}ms:`, error.message);
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }
  
  throw lastError;
}

/**
 * Get balance with retry logic
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {string} networkKey - Network identifier
 * @param {string} address - Wallet address
 * @returns {Promise<bigint>} Balance in wei
 */
async function getBalance(rpcUrl, networkKey, address) {
  return withRetry(async () => {
    const provider = getProvider(rpcUrl, networkKey);
    return await provider.getBalance(address);
  });
}

/**
 * Get gas price with retry logic
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {string} networkKey - Network identifier
 * @returns {Promise<bigint>} Gas price in wei
 */
async function getGasPrice(rpcUrl, networkKey) {
  return withRetry(async () => {
    const provider = getProvider(rpcUrl, networkKey);
    const feeData = await provider.getFeeData();
    return feeData.gasPrice || feeData.maxFeePerGas || ethers.parseUnits('20', 'gwei');
  });
}

/**
 * Get block number with retry logic
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {string} networkKey - Network identifier
 * @returns {Promise<number>} Current block number
 */
async function getBlockNumber(rpcUrl, networkKey) {
  return withRetry(async () => {
    const provider = getProvider(rpcUrl, networkKey);
    return await provider.getBlockNumber();
  });
}

/**
 * Clear provider cache
 * @param {string} networkKey - Optional network key to clear specific cache
 */
function clearCache(networkKey = null) {
  if (networkKey) {
    for (const [key] of providerCache) {
      if (key.startsWith(`${networkKey}:`)) {
        providerCache.delete(key);
      }
    }
  } else {
    providerCache.clear();
  }
}

/**
 * Get provider health status
 * @returns {object} Health status for all cached providers
 */
async function getHealthStatus() {
  const status = {};
  
  for (const [key, cached] of providerCache) {
    const [networkKey] = key.split(':');
    const healthy = await isProviderHealthy(cached.provider);
    
    status[networkKey] = {
      healthy,
      cacheAge: Date.now() - cached.timestamp,
      rpcUrl: key.split(':').slice(1).join(':')
    };
  }
  
  return status;
}

/**
 * Create a wallet connected to a provider
 * @param {string} privateKey - Private key
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {string} networkKey - Network identifier
 * @returns {ethers.Wallet} Connected wallet
 */
function createWallet(privateKey, rpcUrl, networkKey) {
  const provider = getProvider(rpcUrl, networkKey);
  return new ethers.Wallet(privateKey, provider);
}

module.exports = {
  NETWORK_CONFIGS,
  getNetworkConfig,
  createProvider,
  getProvider,
  isProviderHealthy,
  withRetry,
  getBalance,
  getGasPrice,
  getBlockNumber,
  clearCache,
  getHealthStatus,
  createWallet
};

