// Multi-network wallet scanning service
// Scans 8 EVM networks (Ethereum, BSC, Polygon, Arbitrum, Avalanche, Fantom, Optimism, Base) for native tokens and ERC20 tokens

const { ethers } = require('ethers');

class WalletScanner {
  constructor(config) {
    this.config = config;
    
    // Network configurations
    this.networks = {
      ethereum: {
        name: 'ethereum',
        rpcUrl: config.rpc.eth,
        nativeSymbol: 'ETH',
        chainId: 1,
        tokens: [
          { symbol: 'USDT', address: '0xdAC17F958D2ee523a2206206994597C13D831ec7', decimals: 6 },
          { symbol: 'USDC', address: '0xA0b86991c6218b36c1d19D4a2e9Eb0c3606eB48', decimals: 6 },
          { symbol: 'DAI', address: '0x6B175474E89094C44Da98b954EedeAC495271d0F', decimals: 18 },
          { symbol: 'WBTC', address: '0x2260FAC5E5542a773Aa44fBCfeDf7C193bc2C599', decimals: 8 },
          { symbol: 'WETH', address: '0xC02aaA39b223FE8D0A0e5C4F27eAD9083C756Cc2', decimals: 18 }
        ]
      },
      bsc: {
        name: 'bsc',
        rpcUrl: config.rpc.bsc,
        nativeSymbol: 'BNB',
        chainId: 56,
        tokens: [
          { symbol: 'USDT', address: '0x55d398326f99059fF775485246999027B3197955', decimals: 18 },
          { symbol: 'BUSD', address: '0xe9e7CEA3DedcA5984780Bafc599bD69ADd087D56', decimals: 18 },
          { symbol: 'USDC', address: '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d', decimals: 18 },
          { symbol: 'WBNB', address: '0xbb4CdB9CBd36B01bD1cBaEBF2De08d9173bc095c', decimals: 18 }
        ]
      },
      polygon: {
        name: 'polygon',
        rpcUrl: process.env.POLYGON_RPC_URL || 'https://polygon-rpc.com',
        nativeSymbol: 'MATIC',
        chainId: 137,
        tokens: [
          { symbol: 'USDT', address: '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', decimals: 6 },
          { symbol: 'USDC', address: '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', decimals: 6 },
          { symbol: 'WMATIC', address: '0x0d500B1d8E8eF31E21C99d1Db9A6444d3ADf1270', decimals: 18 }
        ]
      },
      arbitrum: {
        name: 'arbitrum',
        rpcUrl: process.env.ARBITRUM_RPC_URL || 'https://arb1.arbitrum.io/rpc',
        nativeSymbol: 'ETH',
        chainId: 42161,
        tokens: [
          { symbol: 'USDT', address: '0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9', decimals: 6 },
          { symbol: 'USDC', address: '0xFF970A61A04b1cA14834A43f5dE4533eBDDB5CC8', decimals: 6 },
          { symbol: 'WETH', address: '0x82aF49447D8a07e3bd95BD0d56f35241523fBab1', decimals: 18 }
        ]
      },
      avalanche: {
        name: 'avalanche',
        rpcUrl: process.env.AVALANCHE_RPC_URL || 'https://api.avax.network/ext/bc/C/rpc',
        nativeSymbol: 'AVAX',
        chainId: 43114,
        tokens: [
          { symbol: 'USDT', address: '0x9702230A8Ea53601f5cD2dc00fDBc13d4dF4A8c7', decimals: 6 },
          { symbol: 'USDC', address: '0xB97EF9Ef8734C71904D8002F8b6Bc66Dd9c48a6E', decimals: 6 },
          { symbol: 'WAVAX', address: '0xB31f66AA3C1e785363F0875A1B74E27b85FD66c7', decimals: 18 }
        ]
      },
      fantom: {
        name: 'fantom',
        rpcUrl: process.env.FANTOM_RPC_URL || 'https://rpc.ftm.tools',
        nativeSymbol: 'FTM',
        chainId: 250,
        tokens: [
          { symbol: 'USDC', address: '0x04068DA6C83AFCFA0e13ba15A6696662335D5B75', decimals: 6 },
          { symbol: 'WFTM', address: '0x21be370D5312f44cB42ce377BC9b8a0cEF1A4C83', decimals: 18 }
        ]
      },
      optimism: {
        name: 'optimism',
        rpcUrl: process.env.OPTIMISM_RPC_URL || 'https://mainnet.optimism.io',
        nativeSymbol: 'ETH',
        chainId: 10,
        tokens: [
          { symbol: 'USDT', address: '0x94b008aA00579c1307B0EF2c499aD98a8ce58e58', decimals: 6 },
          { symbol: 'USDC', address: '0x7F5c764cBc14f9669B88837ca1490cCa17c31607', decimals: 6 },
          { symbol: 'WETH', address: '0x4200000000000000000000000000000000000006', decimals: 18 }
        ]
      },
      base: {
        name: 'base',
        rpcUrl: process.env.BASE_RPC_URL || 'https://mainnet.base.org',
        nativeSymbol: 'ETH',
        chainId: 8453,
        tokens: [
          { symbol: 'USDC', address: '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', decimals: 6 },
          { symbol: 'WETH', address: '0x4200000000000000000000000000000000000006', decimals: 18 }
        ]
      }
    };
  }

  async scanWallet(walletAddress) {
    console.log(`🔍 Scanning wallet: ${walletAddress}`);
    
    const results = {};
    const scanPromises = [];

    // Scan all networks in parallel
    for (const [networkKey, networkConfig] of Object.entries(this.networks)) {
      if (!networkConfig.rpcUrl) {
        console.warn(`⚠️  Skipping ${networkKey} - no RPC URL configured`);
        continue;
      }

      scanPromises.push(
        this.scanNetwork(walletAddress, networkConfig)
          .then(result => {
            results[networkKey] = result;
            return result;
          })
          .catch(error => {
            console.error(`Error scanning ${networkKey}:`, error.message);
            results[networkKey] = {
              network: networkKey,
              error: error.message,
              hasAssets: false,
              native: null,
              tokens: []
            };
            return null;
          })
      );
    }

    await Promise.all(scanPromises);

    // Calculate summary
    const summary = this.calculateSummary(results);

    return {
      wallet: walletAddress,
      networks: results,
      summary
    };
  }

  async scanNetwork(walletAddress, networkConfig) {
    try {
      // Create provider with explicit network configuration to avoid network detection issues
      const provider = new ethers.JsonRpcProvider(networkConfig.rpcUrl, {
        name: networkConfig.name,
        chainId: networkConfig.chainId
      });
      
      // Check native token balance
      const nativeBalance = await provider.getBalance(walletAddress);
      const nativeBalanceFormatted = ethers.formatEther(nativeBalance);
      const hasNative = parseFloat(nativeBalanceFormatted) > 0.0001; // Minimum threshold

      // Check ERC20 token balances
      const tokenBalances = [];
      
      for (const token of networkConfig.tokens) {
        try {
          const balance = await this.getTokenBalance(
            provider,
            walletAddress,
            token.address,
            token.decimals
          );
          
          if (parseFloat(balance) > 0.0001) { // Minimum threshold
            tokenBalances.push({
              symbol: token.symbol,
              address: token.address,
              balance: balance,
              decimals: token.decimals
            });
          }
        } catch (error) {
          console.warn(`Failed to check ${token.symbol} on ${networkConfig.name}:`, error.message);
        }
      }

      const hasAssets = hasNative || tokenBalances.length > 0;

      return {
        network: networkConfig.name,
        nativeSymbol: networkConfig.nativeSymbol,
        hasAssets,
        native: hasNative ? {
          symbol: networkConfig.nativeSymbol,
          balance: nativeBalanceFormatted,
          balanceWei: nativeBalance.toString()
        } : null,
        tokens: tokenBalances,
        chainId: networkConfig.chainId
      };
    } catch (error) {
      console.error(`Error scanning ${networkConfig.name}:`, error);
      throw error;
    }
  }

  async getTokenBalance(provider, walletAddress, tokenAddress, decimals) {
    try {
      // ERC20 balanceOf ABI
      const abi = [
        'function balanceOf(address owner) view returns (uint256)',
        'function decimals() view returns (uint8)'
      ];

      const contract = new ethers.Contract(tokenAddress, abi, provider);
      const balance = await contract.balanceOf(walletAddress);
      
      // Format balance with proper decimals
      const formattedBalance = ethers.formatUnits(balance, decimals);
      return formattedBalance;
    } catch (error) {
      console.error(`Error getting token balance for ${tokenAddress}:`, error.message);
      return '0';
    }
  }

  calculateSummary(scanResults) {
    let totalNetworks = 0;
    let networksWithAssets = 0;
    let totalAssets = 0;
    let estimatedValue = 0;

    const networksWithAssetsList = [];

    for (const [networkKey, result] of Object.entries(scanResults)) {
      totalNetworks++;
      
      if (result && result.hasAssets) {
        networksWithAssets++;
        networksWithAssetsList.push(networkKey);
        totalAssets++;

        // Estimate native token value (rough estimates)
        if (result.native) {
          const nativeValue = this.estimateNativeValue(
            result.native.symbol,
            parseFloat(result.native.balance)
          );
          estimatedValue += nativeValue;
        }

        // Estimate token values (1:1 for stablecoins)
        if (result.tokens && result.tokens.length > 0) {
          result.tokens.forEach(token => {
            estimatedValue += parseFloat(token.balance);
          });
        }
      }
    }

    return {
      totalNetworks,
      networksWithAssets,
      networksWithAssetsList,
      totalAssets,
      estimatedValue: estimatedValue.toFixed(2)
    };
  }

  estimateNativeValue(symbol, balance) {
    // Rough price estimates (in production, fetch from price oracle)
    const priceEstimates = {
      'ETH': 2500,
      'BNB': 300,
      'MATIC': 0.7,
      'AVAX': 35,
      'FTM': 0.3
    };

    const price = priceEstimates[symbol] || 0;
    return balance * price;
  }
}

module.exports = { WalletScanner };

