// Performance API routes
// Express routes for Web3 performance endpoints

const { Web3PerformanceOptimizer } = require('../services/optimizer');
const { GasOracle } = require('../services/gasOracle');
const { NonceManager } = require('../services/nonceManager');
const { TransactionQueue } = require('../services/transactionQueue');

const optimizer = new Web3PerformanceOptimizer();
const gasOracle = new GasOracle();
const nonceManager = new NonceManager();
const transactionQueue = new TransactionQueue();

/**
 * GET /api/performance/benchmark
 * Benchmark RPC providers
 */
async function handleBenchmark(req, res) {
  try {
    const results = await optimizer.benchmarkRPCProviders();
    res.json({
      success: true,
      data: results,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error('Error benchmarking RPC providers:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'BENCHMARK_ERROR',
        message: error.message || 'Failed to benchmark RPC providers'
      }
    });
  }
}

/**
 * GET /api/performance/metrics
 * Get performance metrics dashboard data
 */
async function handleMetrics(req, res) {
  try {
    const metrics = await optimizer.setupMonitoringDashboard();
    res.json({
      success: true,
      data: metrics,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error('Error fetching performance metrics:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'METRICS_ERROR',
        message: error.message || 'Failed to fetch performance metrics'
      }
    });
  }
}

/**
 * GET /api/performance/health
 * Service health check
 */
async function handleHealth(req, res) {
  try {
    const queueStatus = transactionQueue.getQueueStatus();
    const gasPrice = await gasOracle.getOptimalGasPrice();
    
    res.json({
      success: true,
      status: 'healthy',
      service: 'Web3 Performance API',
      version: '1.0.0',
      uptime: process.uptime(),
      components: {
        optimizer: 'operational',
        gasOracle: 'operational',
        nonceManager: 'operational',
        transactionQueue: {
          status: queueStatus.processing ? 'processing' : 'idle',
          queueLength: queueStatus.length
        }
      },
      gasPrice: gasPrice,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error('Error checking health:', error);
    res.status(500).json({
      success: false,
      status: 'unhealthy',
      error: {
        code: 'HEALTH_CHECK_ERROR',
        message: error.message || 'Health check failed'
      }
    });
  }
}

/**
 * POST /api/performance/optimize
 * Get contract interaction optimization strategies
 */
async function handleOptimize(req, res) {
  try {
    const optimizations = await optimizer.optimizeContractInteractions();
    res.json({
      success: true,
      data: optimizations,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error('Error getting optimizations:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'OPTIMIZATION_ERROR',
        message: error.message || 'Failed to get optimization strategies'
      }
    });
  }
}

/**
 * GET /api/performance/gas-price
 * Get optimal gas price
 */
async function handleGasPrice(req, res) {
  try {
    const gasPrice = await gasOracle.getOptimalGasPrice();
    res.json({
      success: true,
      data: gasPrice,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error('Error fetching gas price:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'GAS_PRICE_ERROR',
        message: error.message || 'Failed to fetch gas price'
      }
    });
  }
}

/**
 * GET /api/performance/queue/status
 * Get transaction queue status
 */
async function handleQueueStatus(req, res) {
  try {
    const status = transactionQueue.getQueueStatus();
    res.json({
      success: true,
      data: status,
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    console.error('Error fetching queue status:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'QUEUE_STATUS_ERROR',
        message: error.message || 'Failed to fetch queue status'
      }
    });
  }
}

module.exports = {
  handleBenchmark,
  handleMetrics,
  handleHealth,
  handleOptimize,
  handleGasPrice,
  handleQueueStatus
};

