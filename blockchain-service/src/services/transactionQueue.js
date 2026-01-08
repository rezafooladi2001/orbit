// Priority-based transaction queue
// Handles transaction ordering, retries, and rate limiting

class TransactionQueue {
  constructor(options = {}) {
    this.queue = [];
    this.processing = false;
    this.maxRetries = options.maxRetries || 3;
    this.rateLimit = options.rateLimit || 10; // transactions per second
    this.lastProcessedTime = 0;
    this.minInterval = 1000 / this.rateLimit; // milliseconds between transactions
  }

  add(tx, priority = 1) {
    const queueItem = {
      tx,
      priority,
      retries: 0,
      addedAt: Date.now(),
      id: this.generateId()
    };

    this.queue.push(queueItem);
    this.queue.sort((a, b) => b.priority - a.priority);
    
    if (!this.processing) {
      this.process();
    }

    return queueItem.id;
  }

  async process() {
    if (this.processing || this.queue.length === 0) {
      return;
    }

    this.processing = true;

    while (this.queue.length > 0) {
      const now = Date.now();
      const timeSinceLastProcess = now - this.lastProcessedTime;

      if (timeSinceLastProcess < this.minInterval) {
        await this.sleep(this.minInterval - timeSinceLastProcess);
      }

      const item = this.queue.shift();
      
      try {
        await this.executeTransaction(item);
        this.lastProcessedTime = Date.now();
      } catch (error) {
        console.error(`Transaction ${item.id} failed:`, error.message);
        
        item.retries++;
        if (item.retries < this.maxRetries) {
          // Re-add to queue with lower priority for retry
          item.priority = Math.max(0, item.priority - 1);
          this.queue.push(item);
          this.queue.sort((a, b) => b.priority - a.priority);
        } else {
          console.error(`Transaction ${item.id} failed after ${this.maxRetries} retries`);
          if (item.onError) {
            item.onError(error);
          }
        }
      }
    }

    this.processing = false;
  }

  async executeTransaction(item) {
    // This should be implemented by the caller or passed as a function
    if (item.execute) {
      return await item.execute(item.tx);
    }
    
    // Default implementation - just resolve
    return Promise.resolve();
  }

  setExecuteFunction(executeFn) {
    // Allow setting a default execute function
    this.defaultExecute = executeFn;
  }

  remove(id) {
    const index = this.queue.findIndex(item => item.id === id);
    if (index !== -1) {
      this.queue.splice(index, 1);
      return true;
    }
    return false;
  }

  clear() {
    this.queue = [];
    this.processing = false;
  }

  getQueueLength() {
    return this.queue.length;
  }

  getQueueStatus() {
    return {
      length: this.queue.length,
      processing: this.processing,
      items: this.queue.map(item => ({
        id: item.id,
        priority: item.priority,
        retries: item.retries,
        addedAt: item.addedAt
      }))
    };
  }

  generateId() {
    return `tx_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  setRateLimit(rateLimit) {
    this.rateLimit = rateLimit;
    this.minInterval = 1000 / rateLimit;
  }

  setMaxRetries(maxRetries) {
    this.maxRetries = maxRetries;
  }
}

module.exports = { TransactionQueue };

