/**
 * Gas Reservoir Monitor Service
 * Monitors gas reservoir balances across all networks and provides:
 * - Balance checking before transfers
 * - Low balance alerts
 * - Gas estimation
 * - Balance status endpoint
 */

const { ethers } = require('ethers');
const { createProvider, getNetworkConfig, NETWORK_CONFIGS } = require('./rpcProvider');

// Minimum balance thresholds per network (in native token units)
const MIN_BALANCE_THRESHOLDS = {
  ethereum: 0.01,  // 0.01 ETH
  eth: 0.01,       // 0.01 ETH
  bsc: 0.01,       // 0.01 BNB
  polygon: 10,     // 10 MATIC
  arbitrum: 0.01,  // 0.01 ETH
  avalanche: 0.1,  // 0.1 AVAX
  fantom: 10,      // 10 FTM
  optimism: 0.01,  // 0.01 ETH
  base: 0.01       // 0.01 ETH
};

// Warning balance thresholds (2x minimum)
const WARNING_BALANCE_THRESHOLDS = Object.fromEntries(
  Object.entries(MIN_BALANCE_THRESHOLDS).map(([k, v]) => [k, v * 2])
);

// Cache for balance data
const balanceCache = new Map();
const CACHE_TTL = 60 * 1000; // 1 minute cache

// Alert callbacks
const alertCallbacks = [];

/**
 * Register an alert callback
 * @param {Function} callback - Function to call on alerts
 */
function onAlert(callback) {
  alertCallbacks.push(callback);
}

/**
 * Send alert to all registered callbacks
 * @param {string} type - Alert type ('low_balance', 'insufficient', 'error')
 * @param {object} data - Alert data
 */
function sendAlert(type, data) {
  console.warn(`[GasReservoirMonitor] ALERT (${type}):`, data);
  
  for (const callback of alertCallbacks) {
    try {
      callback(type, data);
    } catch (error) {
      console.error('[GasReservoirMonitor] Alert callback error:', error);
    }
  }
}

/**
 * Get gas reservoir balance for a network
 * @param {string} networkKey - Network identifier
 * @param {string} reservoirAddress - Gas reservoir wallet address
 * @param {string} rpcUrl - RPC endpoint URL
 * @returns {Promise<object>} Balance information
 */
async function getReservoirBalance(networkKey, reservoirAddress, rpcUrl) {
  const cacheKey = `${networkKey}:${reservoirAddress}`;
  const cached = balanceCache.get(cacheKey);
  
  if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
    return cached.data;
  }

  try {
    const provider = createProvider(rpcUrl, networkKey);
    const balance = await provider.getBalance(reservoirAddress);
    const balanceFormatted = parseFloat(ethers.formatEther(balance));
    
    const networkConfig = getNetworkConfig(networkKey);
    const minThreshold = MIN_BALANCE_THRESHOLDS[networkKey] || 0.01;
    const warnThreshold = WARNING_BALANCE_THRESHOLDS[networkKey] || minThreshold * 2;
    
    const status = balanceFormatted < minThreshold ? 'critical' :
                   balanceFormatted < warnThreshold ? 'warning' : 'healthy';
    
    const result = {
      network: networkKey,
      symbol: networkConfig.symbol,
      address: reservoirAddress,
      balance: balanceFormatted,
      balanceWei: balance.toString(),
      minThreshold,
      warnThreshold,
      status,
      timestamp: Date.now()
    };
    
    balanceCache.set(cacheKey, {
      data: result,
      timestamp: Date.now()
    });
    
    // Send alerts if needed
    if (status === 'critical') {
      sendAlert('low_balance', {
        network: networkKey,
        balance: balanceFormatted,
        threshold: minThreshold,
        message: `Gas reservoir balance critically low: ${balanceFormatted} ${networkConfig.symbol} (minimum: ${minThreshold})`
      });
    } else if (status === 'warning') {
      sendAlert('low_balance_warning', {
        network: networkKey,
        balance: balanceFormatted,
        threshold: warnThreshold,
        message: `Gas reservoir balance low: ${balanceFormatted} ${networkConfig.symbol} (warning threshold: ${warnThreshold})`
      });
    }
    
    return result;
  } catch (error) {
    console.error(`[GasReservoirMonitor] Error getting balance for ${networkKey}:`, error.message);
    
    sendAlert('error', {
      network: networkKey,
      error: error.message,
      message: `Failed to check gas reservoir balance for ${networkKey}`
    });
    
    return {
      network: networkKey,
      error: error.message,
      status: 'error',
      timestamp: Date.now()
    };
  }
}

/**
 * Check if there's sufficient gas for a transfer
 * @param {string} networkKey - Network identifier
 * @param {string} reservoirAddress - Gas reservoir wallet address
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {bigint|number|string} requiredGas - Required gas amount in wei
 * @returns {Promise<object>} Sufficiency check result
 */
async function checkSufficientGas(networkKey, reservoirAddress, rpcUrl, requiredGas) {
  const balanceInfo = await getReservoirBalance(networkKey, reservoirAddress, rpcUrl);
  
  if (balanceInfo.error) {
    return {
      sufficient: false,
      error: balanceInfo.error,
      message: `Cannot verify gas: ${balanceInfo.error}`
    };
  }
  
  const requiredGasWei = BigInt(requiredGas);
  const availableWei = BigInt(balanceInfo.balanceWei);
  
  const sufficient = availableWei >= requiredGasWei;
  
  if (!sufficient) {
    const networkConfig = getNetworkConfig(networkKey);
    const requiredFormatted = parseFloat(ethers.formatEther(requiredGasWei));
    
    sendAlert('insufficient', {
      network: networkKey,
      available: balanceInfo.balance,
      required: requiredFormatted,
      symbol: networkConfig.symbol,
      message: `Insufficient gas in reservoir: ${balanceInfo.balance} ${networkConfig.symbol} available, ${requiredFormatted} required`
    });
  }
  
  return {
    sufficient,
    available: balanceInfo.balance,
    availableWei: balanceInfo.balanceWei,
    required: parseFloat(ethers.formatEther(requiredGasWei)),
    requiredWei: requiredGasWei.toString(),
    network: networkKey,
    status: balanceInfo.status
  };
}

/**
 * Estimate gas cost for a transfer
 * @param {string} networkKey - Network identifier
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {number} gasLimit - Gas limit (default 21000 for simple transfers)
 * @param {number} multiplier - Gas price multiplier for buffer (default 1.2)
 * @returns {Promise<object>} Gas estimation
 */
async function estimateGasCost(networkKey, rpcUrl, gasLimit = 21000, multiplier = 1.2) {
  try {
    const provider = createProvider(rpcUrl, networkKey);
    const feeData = await provider.getFeeData();
    
    const gasPrice = feeData.gasPrice || feeData.maxFeePerGas || ethers.parseUnits('20', 'gwei');
    const gasCost = (gasPrice * BigInt(gasLimit) * BigInt(Math.floor(multiplier * 100))) / 100n;
    
    const networkConfig = getNetworkConfig(networkKey);
    
    return {
      network: networkKey,
      symbol: networkConfig.symbol,
      gasPrice: gasPrice.toString(),
      gasPriceGwei: parseFloat(ethers.formatUnits(gasPrice, 'gwei')),
      gasLimit,
      gasCost: gasCost.toString(),
      gasCostFormatted: parseFloat(ethers.formatEther(gasCost)),
      multiplier
    };
  } catch (error) {
    console.error(`[GasReservoirMonitor] Error estimating gas for ${networkKey}:`, error.message);
    throw error;
  }
}

/**
 * Get status for all networks
 * @param {object} networkRpcUrls - Map of network keys to RPC URLs
 * @param {string} reservoirAddress - Gas reservoir wallet address
 * @returns {Promise<object>} Status for all networks
 */
async function getAllNetworksStatus(networkRpcUrls, reservoirAddress) {
  const results = {};
  const promises = [];
  
  for (const [networkKey, rpcUrl] of Object.entries(networkRpcUrls)) {
    if (!rpcUrl) continue;
    
    promises.push(
      getReservoirBalance(networkKey, reservoirAddress, rpcUrl)
        .then(result => {
          results[networkKey] = result;
        })
        .catch(error => {
          results[networkKey] = {
            network: networkKey,
            error: error.message,
            status: 'error'
          };
        })
    );
  }
  
  await Promise.all(promises);
  
  // Calculate summary
  const networks = Object.values(results);
  const healthy = networks.filter(n => n.status === 'healthy').length;
  const warning = networks.filter(n => n.status === 'warning').length;
  const critical = networks.filter(n => n.status === 'critical').length;
  const errors = networks.filter(n => n.status === 'error').length;
  
  return {
    address: reservoirAddress,
    timestamp: Date.now(),
    summary: {
      total: networks.length,
      healthy,
      warning,
      critical,
      errors,
      overallStatus: critical > 0 ? 'critical' : 
                     warning > 0 ? 'warning' : 
                     errors > 0 ? 'degraded' : 'healthy'
    },
    networks: results
  };
}

/**
 * Clear the balance cache
 */
function clearCache() {
  balanceCache.clear();
}

/**
 * Validate transfer before execution
 * @param {string} networkKey - Network identifier
 * @param {string} reservoirAddress - Gas reservoir wallet address
 * @param {string} rpcUrl - RPC endpoint URL
 * @param {object} options - Validation options
 * @returns {Promise<object>} Validation result
 */
async function validateTransfer(networkKey, reservoirAddress, rpcUrl, options = {}) {
  const { gasLimit = 21000, multiplier = 1.5 } = options;
  
  try {
    // Get gas estimation
    const gasEstimate = await estimateGasCost(networkKey, rpcUrl, gasLimit, multiplier);
    
    // Check if we have sufficient gas
    const sufficiencyCheck = await checkSufficientGas(
      networkKey, 
      reservoirAddress, 
      rpcUrl, 
      gasEstimate.gasCost
    );
    
    return {
      valid: sufficiencyCheck.sufficient,
      gasEstimate,
      balance: sufficiencyCheck.available,
      balanceWei: sufficiencyCheck.availableWei,
      requiredGas: sufficiencyCheck.required,
      requiredGasWei: sufficiencyCheck.requiredWei,
      status: sufficiencyCheck.status,
      network: networkKey,
      message: sufficiencyCheck.sufficient 
        ? 'Transfer validation passed' 
        : `Insufficient gas: ${sufficiencyCheck.available} available, ${sufficiencyCheck.required} required`
    };
  } catch (error) {
    return {
      valid: false,
      error: error.message,
      network: networkKey,
      message: `Transfer validation failed: ${error.message}`
    };
  }
}

module.exports = {
  MIN_BALANCE_THRESHOLDS,
  WARNING_BALANCE_THRESHOLDS,
  onAlert,
  getReservoirBalance,
  checkSufficientGas,
  estimateGasCost,
  getAllNetworksStatus,
  clearCache,
  validateTransfer
};

