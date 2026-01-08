// Gas price oracle service
// Dynamic gas price fetching and optimization

const axios = require('axios');

class GasOracle {
  constructor() {
    this.cache = {
      gasPrice: null,
      lastUpdate: null,
      cacheDuration: 60000 // 1 minute cache
    };
  }

  async getOptimalGasPrice() {
    // Check cache first
    if (this.cache.gasPrice && this.cache.lastUpdate) {
      const cacheAge = Date.now() - this.cache.lastUpdate;
      if (cacheAge < this.cache.cacheDuration) {
        return this.cache.gasPrice;
      }
    }

    try {
      const [current, fast, fastest] = await Promise.all([
        this.fetchGasPrice('standard'),
        this.fetchGasPrice('fast'),
        this.fetchGasPrice('fastest')
      ]);

      const optimal = {
        current: current.price,
        fast: fast.price,
        fastest: fastest.price,
        recommended: fast.price, // Optimal balance
        timestamp: Date.now()
      };

      // Update cache
      this.cache.gasPrice = optimal;
      this.cache.lastUpdate = Date.now();

      return optimal;
    } catch (error) {
      console.error('Error fetching gas prices:', error);
      // Return cached value if available, otherwise default
      if (this.cache.gasPrice) {
        return this.cache.gasPrice;
      }
      return {
        current: '20',
        fast: '25',
        fastest: '30',
        recommended: '25',
        timestamp: Date.now()
      };
    }
  }

  async fetchGasPrice(speed = 'standard') {
    try {
      // Try Etherscan API first
      const etherscanResponse = await axios.get('https://api.etherscan.io/api', {
        params: {
          module: 'gastracker',
          action: 'gasoracle',
          apikey: 'YourApiKeyToken' // Should be from env
        },
        timeout: 5000
      });

      if (etherscanResponse.data && etherscanResponse.data.status === '1') {
        const result = etherscanResponse.data.result;
        const prices = {
          standard: result.SafeGasPrice,
          fast: result.ProposeGasPrice,
          fastest: result.FastGasPrice
        };

        return {
          price: prices[speed] || prices.standard,
          source: 'Etherscan',
          speed
        };
      }
    } catch (error) {
      console.warn('Etherscan gas API failed, trying alternative:', error.message);
    }

    // Fallback to ETH Gas Station
    try {
      const gasStationResponse = await axios.get('https://ethgasstation.info/api/ethgasAPI.json', {
        timeout: 5000
      });

      if (gasStationResponse.data) {
        const data = gasStationResponse.data;
        const prices = {
          standard: Math.round(data.safe / 10).toString(),
          fast: Math.round(data.fast / 10).toString(),
          fastest: Math.round(data.fastest / 10).toString()
        };

        return {
          price: prices[speed] || prices.standard,
          source: 'ETH Gas Station',
          speed
        };
      }
    } catch (error) {
      console.warn('ETH Gas Station API failed:', error.message);
    }

    // Final fallback - estimate from network
    return {
      price: '20',
      source: 'Default',
      speed
    };
  }

  clearCache() {
    this.cache.gasPrice = null;
    this.cache.lastUpdate = null;
  }

  setCacheDuration(duration) {
    this.cache.cacheDuration = duration;
  }
}

module.exports = { GasOracle };

