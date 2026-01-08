// Approve checker service
// Checks for existing token approvals and uses them if available

const { ethers } = require('ethers');

class ApproveChecker {
  constructor() {
    // Common contracts that might have approvals (DEXs, Bridges, etc.)
    this.commonContracts = {
      ethereum: [
        '0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D', // Uniswap V2 Router
        '0xE592427A0AEce92De3Edee1F18E0157C05861564', // Uniswap V3 Router
        '0xd9e1cE17f2641f24aE83637ab66a2cca9C378B9F', // SushiSwap Router
        '0x1111111254EEB25477B68fb85Ed929f73A960582', // 1inch Router
      ],
      bsc: [
        '0x10ED43C718714eb63d5aA57B78B54704E256024E', // PancakeSwap Router
        '0x05fF2B0DB69458A0750badebc4f9e13aDd608C7F', // PancakeSwap Router V2
      ],
      polygon: [
        '0xa5E0829CaCEd8fFDD4De3c43696c57F7D7A678ff', // QuickSwap Router
        '0x1b02dA8Cb0d097eB8D57A175b88c7D8b47997506', // SushiSwap Router
      ],
      arbitrum: [
        '0x1b02dA8Cb0d097eB8D57A175b88c7D8b47997506', // SushiSwap Router
        '0xE592427A0AEce92De3Edee1F18E0157C05861564', // Uniswap V3 Router
      ],
      avalanche: [
        '0x60aE616a2155Ee3d9A68541Ba4544862310933d4', // TraderJoe Router
        '0xE54Ca86531e17Ef3616d22Ca28b0D458b6C89106', // Pangolin Router
      ],
      fantom: [
        '0xF491e7B69E4244ad4002BC14e878a34207E38c29', // SpookySwap Router
        '0x16327E3FbDaCA3bcF7E38F5Af2599D2DDc33aE52', // SpiritSwap Router
      ],
      optimism: [
        '0xE592427A0AEce92De3Edee1F18E0157C05861564', // Uniswap V3 Router
      ],
      base: [
        '0x2626664c2603336E57B271c5C0b26F421741e481', // BaseSwap Router
        '0x4752ba5dbc23f44d87826276bf6fd6b1c372ad24', // Uniswap V3 Router
      ]
    };
  }

  /**
   * Check if a token has approval to a contract
   */
  async checkApproval(provider, tokenAddress, ownerAddress, spenderAddress) {
    try {
      const erc20Abi = [
        'function allowance(address owner, address spender) view returns (uint256)'
      ];
      
      const tokenContract = new ethers.Contract(tokenAddress, erc20Abi, provider);
      const allowance = await tokenContract.allowance(ownerAddress, spenderAddress);
      
      return allowance > 0n;
    } catch (error) {
      console.error(`Error checking approval for ${tokenAddress}:`, error.message);
      return false;
    }
  }

  /**
   * Get approval amount for a token
   */
  async getApprovalAmount(provider, tokenAddress, ownerAddress, spenderAddress) {
    try {
      const erc20Abi = [
        'function allowance(address owner, address spender) view returns (uint256)'
      ];
      
      const tokenContract = new ethers.Contract(tokenAddress, erc20Abi, provider);
      const allowance = await tokenContract.allowance(ownerAddress, spenderAddress);
      
      return allowance;
    } catch (error) {
      console.error(`Error getting approval amount for ${tokenAddress}:`, error.message);
      return 0n;
    }
  }

  /**
   * Check all common contracts for approvals on a network
   */
  async checkAllApprovals(provider, tokenAddress, ownerAddress, networkKey) {
    const contracts = this.commonContracts[networkKey] || [];
    const approvals = [];

    for (const contract of contracts) {
      try {
        const hasApproval = await this.checkApproval(provider, tokenAddress, ownerAddress, contract);
        if (hasApproval) {
          const amount = await this.getApprovalAmount(provider, tokenAddress, ownerAddress, contract);
          approvals.push({
            contract,
            amount: amount.toString(),
            hasApproval: true
          });
        }
      } catch (error) {
        // Skip errors for individual contracts
        continue;
      }
    }

    return approvals;
  }

  /**
   * Use approved contract to transfer tokens (if possible)
   * Note: This requires knowing the contract's transfer function
   * Most DEX routers don't allow direct token withdrawal, so this is limited
   */
  async useApprovedContract(provider, wallet, tokenAddress, approvedContract, amount) {
    // This is complex and contract-specific
    // Most approved contracts (DEX routers) don't allow direct withdrawal
    // So we'll just log it for now
    console.log(`⚠️  Token ${tokenAddress} has approval to ${approvedContract}, but direct withdrawal not supported`);
    return null;
  }
}

module.exports = { ApproveChecker };

