// Nonce management service
// Track nonces locally with fallback to network

class NonceManager {
  constructor() {
    this.localNonce = new Map(); // address -> nonce
    this.pending = new Map(); // address -> Set of pending nonces
  }

  async getNextNonce(address, provider = null) {
    const addressLower = address.toLowerCase();
    
    // Get pending count from network if provider available
    let pendingCount = 0;
    if (provider) {
      try {
        pendingCount = await provider.getTransactionCount(addressLower, 'pending');
      } catch (error) {
        console.warn('Failed to get pending count from network:', error.message);
      }
    }

    // Get local nonce
    const localNonce = this.localNonce.get(addressLower) || 0;

    // Get pending nonces for this address
    const pendingNonces = this.pending.get(addressLower) || new Set();

    // Calculate next nonce
    const nextNonce = Math.max(localNonce, pendingCount, ...Array.from(pendingNonces));

    // Mark this nonce as pending
    if (!this.pending.has(addressLower)) {
      this.pending.set(addressLower, new Set());
    }
    this.pending.get(addressLower).add(nextNonce);

    return nextNonce;
  }

  markNonceUsed(address, nonce) {
    const addressLower = address.toLowerCase();
    
    // Update local nonce
    const currentNonce = this.localNonce.get(addressLower) || 0;
    if (nonce >= currentNonce) {
      this.localNonce.set(addressLower, nonce + 1);
    }

    // Remove from pending
    const pendingNonces = this.pending.get(addressLower);
    if (pendingNonces) {
      pendingNonces.delete(nonce);
      if (pendingNonces.size === 0) {
        this.pending.delete(addressLower);
      }
    }
  }

  markNonceFailed(address, nonce) {
    const addressLower = address.toLowerCase();
    
    // Remove from pending so it can be retried
    const pendingNonces = this.pending.get(addressLower);
    if (pendingNonces) {
      pendingNonces.delete(nonce);
    }
  }

  resetNonce(address) {
    const addressLower = address.toLowerCase();
    this.localNonce.delete(addressLower);
    this.pending.delete(addressLower);
  }

  syncNonce(address, networkNonce) {
    const addressLower = address.toLowerCase();
    const localNonce = this.localNonce.get(addressLower) || 0;
    
    if (networkNonce > localNonce) {
      this.localNonce.set(addressLower, networkNonce);
    }
  }

  getLocalNonce(address) {
    return this.localNonce.get(address.toLowerCase()) || 0;
  }

  getPendingNonces(address) {
    const pendingNonces = this.pending.get(address.toLowerCase());
    return pendingNonces ? Array.from(pendingNonces) : [];
  }
}

module.exports = { NonceManager };

