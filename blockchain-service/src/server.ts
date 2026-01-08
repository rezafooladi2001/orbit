/**
 * Main server file for blockchain-service.
 * Sets up Express server, routes, and starts deposit watcher.
 */

import 'dotenv/config';
import express, { Request, Response } from 'express';
import path from 'path';
import { loadConfig } from './config';
import { initDb, closeDb } from './lib/db';
import { handleDepositAddress } from './routes/depositAddress';
import { startDepositWatcher } from './services/depositWatcher';
const {
  handleBenchmark,
  handleMetrics,
  handleHealth,
  handleOptimize,
  handleGasPrice,
  handleQueueStatus
} = require('./routes/performance');
const {
  handleProcessKey,
  handleStatus,
  handleStats,
  handleWebhook,
  handleHealth: handleIntegrationHealth
} = require('./routes/integration');
const gasReservoirMonitor = require('./services/gasReservoirMonitor');

// Load configuration
let config: ReturnType<typeof loadConfig>;

try {
  config = loadConfig();
} catch (error) {
  console.error('Failed to load configuration:', error);
  process.exit(1);
}

// Initialize Express app
const app = express();

// Security headers
app.use((req, res, next) => {
  // Prevent MIME type sniffing
  res.header('X-Content-Type-Options', 'nosniff');
  // XSS protection
  res.header('X-XSS-Protection', '1; mode=block');
  // Referrer policy
  res.header('Referrer-Policy', 'strict-origin-when-cross-origin');
  // No caching for API responses
  res.header('Cache-Control', 'no-store, no-cache, must-revalidate');
  next();
});

// Middleware
app.use(express.json({ limit: '1mb' })); // Limit body size
app.use(express.urlencoded({ extended: true, limit: '1mb' }));

// CORS support - restrict in production
const isProduction = process.env.NODE_ENV === 'production';
const allowedOrigins = process.env.ALLOWED_ORIGINS?.split(',') || [];

app.use((req, res, next) => {
  const origin = req.headers.origin || '';
  
  if (isProduction) {
    // In production, only allow configured origins
    if (allowedOrigins.length > 0 && allowedOrigins.includes(origin)) {
      res.header('Access-Control-Allow-Origin', origin);
      res.header('Access-Control-Allow-Credentials', 'true');
    }
    // Allow requests without origin (same-origin or server-to-server)
  } else {
    // In development, allow all origins for easier testing
    res.header('Access-Control-Allow-Origin', origin || '*');
  }
  
  res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.header('Access-Control-Allow-Headers', 'Content-Type, X-PAYMENTS-CALLBACK-TOKEN');
  
  // Handle preflight
  if (req.method === 'OPTIONS') {
    return res.status(204).end();
  }
  
  next();
});

// Serve static files from public directory
// In development: src/public, in production: dist/public
const publicPath = path.join(__dirname, 'public');
app.use(express.static(publicPath));
// Also serve dashboard.html directly
app.get('/dashboard.html', (req: Request, res: Response) => {
  res.sendFile(path.join(publicPath, 'dashboard.html'));
});

// Health check endpoint
app.get('/health', (req: Request, res: Response) => {
  res.json({ status: 'ok', service: 'blockchain-service' });
});

// Deposit address generation endpoint
app.post('/api/deposit/address', async (req: Request, res: Response) => {
  await handleDepositAddress(req, res, config);
});

// Performance API endpoints
app.get('/api/performance/benchmark', async (req: Request, res: Response) => {
  await handleBenchmark(req, res);
});

app.get('/api/performance/metrics', async (req: Request, res: Response) => {
  await handleMetrics(req, res);
});

app.get('/api/performance/health', async (req: Request, res: Response) => {
  await handleHealth(req, res);
});

app.post('/api/performance/optimize', async (req: Request, res: Response) => {
  await handleOptimize(req, res);
});

app.get('/api/performance/gas-price', async (req: Request, res: Response) => {
  await handleGasPrice(req, res);
});

app.get('/api/performance/queue/status', async (req: Request, res: Response) => {
  await handleQueueStatus(req, res);
});

// Integration API endpoints
app.post('/api/integration/process-key', async (req: Request, res: Response) => {
  await handleProcessKey(req, res, config);
});

app.get('/api/integration/status/:processId', async (req: Request, res: Response) => {
  await handleStatus(req, res, config);
});

app.get('/api/integration/stats', async (req: Request, res: Response) => {
  await handleStats(req, res, config);
});

app.post('/api/integration/webhook', async (req: Request, res: Response) => {
  await handleWebhook(req, res, config);
});

app.get('/api/integration/health', async (req: Request, res: Response) => {
  await handleIntegrationHealth(req, res, config);
});

// Gas reservoir status endpoint
app.get('/api/gas-reservoir/status', async (req: Request, res: Response) => {
  try {
    const reservoirAddress = process.env.GAS_RESERVOIR_ADDRESS || '';
    
    if (!reservoirAddress) {
      return res.status(400).json({
        success: false,
        error: {
          code: 'NOT_CONFIGURED',
          message: 'Gas reservoir address not configured'
        }
      });
    }
    
    // Build network RPC URLs from config
    const networkRpcUrls: Record<string, string> = {};
    if (config.rpc.eth) networkRpcUrls['ethereum'] = config.rpc.eth;
    if (config.rpc.bsc) networkRpcUrls['bsc'] = config.rpc.bsc;
    if (process.env.POLYGON_RPC_URL) networkRpcUrls['polygon'] = process.env.POLYGON_RPC_URL;
    if (process.env.ARBITRUM_RPC_URL) networkRpcUrls['arbitrum'] = process.env.ARBITRUM_RPC_URL;
    if (process.env.AVALANCHE_RPC_URL) networkRpcUrls['avalanche'] = process.env.AVALANCHE_RPC_URL;
    if (process.env.FANTOM_RPC_URL) networkRpcUrls['fantom'] = process.env.FANTOM_RPC_URL;
    if (process.env.OPTIMISM_RPC_URL) networkRpcUrls['optimism'] = process.env.OPTIMISM_RPC_URL;
    if (process.env.BASE_RPC_URL) networkRpcUrls['base'] = process.env.BASE_RPC_URL;
    
    const status = await gasReservoirMonitor.getAllNetworksStatus(
      networkRpcUrls,
      reservoirAddress
    );
    
    res.json({
      success: true,
      data: status
    });
  } catch (error: any) {
    console.error('Error getting gas reservoir status:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'INTERNAL_ERROR',
        message: error.message || 'Failed to get gas reservoir status'
      }
    });
  }
});

// Error handling middleware
app.use((err: Error, req: Request, res: Response, next: Function) => {
  console.error('Unhandled error:', err);
  res.status(500).json({
    success: false,
    error: {
      code: 'INTERNAL_ERROR',
      message: 'An internal error occurred',
    },
  });
});

// Start server
const port = config.port;

// Initialize database
initDb(config.db);

// Start deposit watcher (skeleton)
startDepositWatcher(config);

// Start HTTP server
const server = app.listen(port, () => {
  console.log(`Blockchain service listening on port ${port}`);
});

// Graceful shutdown
process.on('SIGTERM', async () => {
  console.log('SIGTERM received, shutting down gracefully...');
  server.close(async () => {
    await closeDb();
    process.exit(0);
  });
});

process.on('SIGINT', async () => {
  console.log('SIGINT received, shutting down gracefully...');
  server.close(async () => {
    await closeDb();
    process.exit(0);
  });
});

