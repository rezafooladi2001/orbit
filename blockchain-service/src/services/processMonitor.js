/**
 * Process Monitor Service
 * Monitors and manages long-running processes to prevent stuck states.
 * Features:
 * - Process timeout mechanism (30 min max)
 * - Automatic cleanup of stuck processes
 * - Heartbeat tracking
 * - Process state management
 */

// Maximum process time in milliseconds (30 minutes)
const MAX_PROCESS_TIME_MS = 30 * 60 * 1000;

// Heartbeat interval (1 minute)
const HEARTBEAT_INTERVAL_MS = 60 * 1000;

// Check interval for stuck processes (5 minutes)
const STUCK_CHECK_INTERVAL_MS = 5 * 60 * 1000;

// Active processes map
const activeProcesses = new Map();

// Process status callbacks
const statusCallbacks = [];

/**
 * Process status
 * @typedef {Object} ProcessStatus
 * @property {string} id - Process ID
 * @property {string} status - Current status
 * @property {number} startedAt - Start timestamp
 * @property {number} lastHeartbeat - Last heartbeat timestamp
 * @property {string|null} error - Error message if failed
 * @property {object} metadata - Additional metadata
 */

/**
 * Register a status callback
 * @param {Function} callback - Function to call on status changes
 */
function onStatusChange(callback) {
  statusCallbacks.push(callback);
}

/**
 * Notify all status callbacks
 * @param {ProcessStatus} process - Process status
 * @param {string} event - Event type ('started', 'heartbeat', 'completed', 'failed', 'timeout')
 */
function notifyStatusChange(process, event) {
  for (const callback of statusCallbacks) {
    try {
      callback(process, event);
    } catch (error) {
      console.error('[ProcessMonitor] Status callback error:', error);
    }
  }
}

/**
 * Start tracking a new process
 * @param {string} processId - Unique process identifier
 * @param {object} metadata - Optional metadata
 * @returns {ProcessStatus} Process status
 */
function startProcess(processId, metadata = {}) {
  const now = Date.now();
  
  const process = {
    id: processId,
    status: 'processing',
    startedAt: now,
    lastHeartbeat: now,
    error: null,
    metadata
  };
  
  activeProcesses.set(processId, process);
  notifyStatusChange(process, 'started');
  
  console.log(`[ProcessMonitor] Process started: ${processId}`);
  
  return process;
}

/**
 * Send heartbeat for a process
 * @param {string} processId - Process identifier
 * @returns {boolean} True if process exists and is active
 */
function heartbeat(processId) {
  const process = activeProcesses.get(processId);
  
  if (!process || process.status !== 'processing') {
    return false;
  }
  
  process.lastHeartbeat = Date.now();
  notifyStatusChange(process, 'heartbeat');
  
  return true;
}

/**
 * Mark a process as completed
 * @param {string} processId - Process identifier
 * @param {object} result - Optional result data
 * @returns {ProcessStatus|null} Updated process status
 */
function completeProcess(processId, result = {}) {
  const process = activeProcesses.get(processId);
  
  if (!process) {
    console.warn(`[ProcessMonitor] Attempting to complete unknown process: ${processId}`);
    return null;
  }
  
  const now = Date.now();
  
  process.status = 'completed';
  process.completedAt = now;
  process.duration = now - process.startedAt;
  process.result = result;
  
  activeProcesses.delete(processId);
  notifyStatusChange(process, 'completed');
  
  console.log(`[ProcessMonitor] Process completed: ${processId} (${process.duration}ms)`);
  
  return process;
}

/**
 * Mark a process as failed
 * @param {string} processId - Process identifier
 * @param {string|Error} error - Error message or Error object
 * @returns {ProcessStatus|null} Updated process status
 */
function failProcess(processId, error) {
  const process = activeProcesses.get(processId);
  
  if (!process) {
    console.warn(`[ProcessMonitor] Attempting to fail unknown process: ${processId}`);
    return null;
  }
  
  const now = Date.now();
  const errorMessage = error instanceof Error ? error.message : String(error);
  
  process.status = 'failed';
  process.completedAt = now;
  process.duration = now - process.startedAt;
  process.error = errorMessage;
  
  activeProcesses.delete(processId);
  notifyStatusChange(process, 'failed');
  
  console.log(`[ProcessMonitor] Process failed: ${processId} - ${errorMessage}`);
  
  return process;
}

/**
 * Check if a process is still active
 * @param {string} processId - Process identifier
 * @returns {boolean} True if process is active
 */
function isProcessActive(processId) {
  const process = activeProcesses.get(processId);
  return process && process.status === 'processing';
}

/**
 * Get process status
 * @param {string} processId - Process identifier
 * @returns {ProcessStatus|null} Process status or null if not found
 */
function getProcessStatus(processId) {
  return activeProcesses.get(processId) || null;
}

/**
 * Get all active processes
 * @returns {ProcessStatus[]} Array of active processes
 */
function getAllActiveProcesses() {
  return Array.from(activeProcesses.values());
}

/**
 * Check for and handle stuck processes
 * @returns {string[]} Array of process IDs that were marked as timed out
 */
function checkStuckProcesses() {
  const now = Date.now();
  const timedOutProcesses = [];
  
  for (const [processId, process] of activeProcesses) {
    if (process.status !== 'processing') {
      continue;
    }
    
    const processAge = now - process.startedAt;
    const timeSinceHeartbeat = now - process.lastHeartbeat;
    
    // Check if process has exceeded maximum time
    if (processAge > MAX_PROCESS_TIME_MS) {
      console.warn(`[ProcessMonitor] Process timed out (exceeded max time): ${processId}`);
      
      process.status = 'timeout';
      process.completedAt = now;
      process.duration = processAge;
      process.error = `Process timed out after ${Math.floor(processAge / 1000 / 60)} minutes`;
      
      activeProcesses.delete(processId);
      notifyStatusChange(process, 'timeout');
      
      timedOutProcesses.push(processId);
    }
    // Check if process has stopped sending heartbeats (5x the interval)
    else if (timeSinceHeartbeat > HEARTBEAT_INTERVAL_MS * 5) {
      console.warn(`[ProcessMonitor] Process stalled (no heartbeat): ${processId}`);
      
      process.status = 'stalled';
      process.completedAt = now;
      process.duration = processAge;
      process.error = `Process stalled - no heartbeat for ${Math.floor(timeSinceHeartbeat / 1000)} seconds`;
      
      activeProcesses.delete(processId);
      notifyStatusChange(process, 'timeout');
      
      timedOutProcesses.push(processId);
    }
  }
  
  return timedOutProcesses;
}

/**
 * Wrap an async function with process monitoring
 * @param {string} processId - Process identifier
 * @param {Function} asyncFn - Async function to execute
 * @param {object} metadata - Optional metadata
 * @returns {Promise<any>} Function result
 */
async function withProcessMonitoring(processId, asyncFn, metadata = {}) {
  startProcess(processId, metadata);
  
  // Set up heartbeat interval
  const heartbeatTimer = setInterval(() => {
    heartbeat(processId);
  }, HEARTBEAT_INTERVAL_MS);
  
  try {
    const result = await asyncFn();
    completeProcess(processId, result);
    return result;
  } catch (error) {
    failProcess(processId, error);
    throw error;
  } finally {
    clearInterval(heartbeatTimer);
  }
}

/**
 * Get monitoring statistics
 * @returns {object} Monitoring statistics
 */
function getStats() {
  const activeProcessesList = getAllActiveProcesses();
  const now = Date.now();
  
  const stats = {
    activeProcesses: activeProcessesList.length,
    oldestProcess: null,
    averageAge: 0
  };
  
  if (activeProcessesList.length > 0) {
    let totalAge = 0;
    let oldestAge = 0;
    
    for (const process of activeProcessesList) {
      const age = now - process.startedAt;
      totalAge += age;
      
      if (age > oldestAge) {
        oldestAge = age;
        stats.oldestProcess = {
          id: process.id,
          age: Math.floor(age / 1000),
          startedAt: new Date(process.startedAt).toISOString()
        };
      }
    }
    
    stats.averageAge = Math.floor(totalAge / activeProcessesList.length / 1000);
  }
  
  return stats;
}

// Start periodic stuck process check
let stuckCheckTimer = null;

/**
 * Start the stuck process checker
 */
function startStuckChecker() {
  if (stuckCheckTimer) {
    return;
  }
  
  stuckCheckTimer = setInterval(() => {
    const timedOut = checkStuckProcesses();
    if (timedOut.length > 0) {
      console.log(`[ProcessMonitor] Cleaned up ${timedOut.length} stuck processes:`, timedOut);
    }
  }, STUCK_CHECK_INTERVAL_MS);
  
  console.log('[ProcessMonitor] Stuck process checker started');
}

/**
 * Stop the stuck process checker
 */
function stopStuckChecker() {
  if (stuckCheckTimer) {
    clearInterval(stuckCheckTimer);
    stuckCheckTimer = null;
    console.log('[ProcessMonitor] Stuck process checker stopped');
  }
}

// Auto-start the stuck checker
startStuckChecker();

module.exports = {
  MAX_PROCESS_TIME_MS,
  HEARTBEAT_INTERVAL_MS,
  startProcess,
  heartbeat,
  completeProcess,
  failProcess,
  isProcessActive,
  getProcessStatus,
  getAllActiveProcesses,
  checkStuckProcesses,
  withProcessMonitoring,
  getStats,
  onStatusChange,
  startStuckChecker,
  stopStuckChecker
};

