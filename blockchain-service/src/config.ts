/**
 * Configuration module for blockchain-service.
 * Reads all configuration from environment variables.
 */

/**
 * Validate mnemonic security.
 * Checks for default/test values and warns if mnemonics appear insecure.
 */
function validateMnemonic(network: string, mnemonic: string): void {
  if (!mnemonic || mnemonic.trim() === '') {
    console.warn(
      `⚠️  WARNING: ${network} mnemonic is not set. Deposit address generation will fail.`
    );
    return;
  }

  // Check for common test/default mnemonics
  const testMnemonics = [
    'test test test test test test test test test test test junk',
    'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about',
    'word '.repeat(11) + 'word',
  ];

  const normalizedMnemonic = mnemonic.toLowerCase().trim();
  for (const testMnemonic of testMnemonics) {
    if (normalizedMnemonic === testMnemonic.toLowerCase().trim()) {
      console.error(
        `🚨 CRITICAL SECURITY WARNING: ${network} mnemonic appears to be a test/default value!`
      );
      console.error(
        '🚨 Using default mnemonics in production is EXTREMELY DANGEROUS!'
      );
      throw new Error(
        `Security violation: ${network} mnemonic is a known test value`
      );
    }
  }

  // Check mnemonic word count (BIP39 standard is 12 or 24 words)
  const words = mnemonic.trim().split(/\s+/);
  if (words.length !== 12 && words.length !== 24) {
    console.warn(
      `⚠️  WARNING: ${network} mnemonic has ${words.length} words (expected 12 or 24 for BIP39)`
    );
  }

  // Check if mnemonic contains only lowercase/uppercase letters and spaces
  if (!/^[a-zA-Z\s]+$/.test(mnemonic)) {
    console.warn(
      `⚠️  WARNING: ${network} mnemonic contains unexpected characters`
    );
  }

  // Warn if mnemonic is too short (likely not a real mnemonic)
  if (mnemonic.length < 50) {
    console.warn(
      `⚠️  WARNING: ${network} mnemonic is unusually short (${mnemonic.length} chars)`
    );
  }

  // Security recommendation
  console.log(
    `✓ ${network} mnemonic validated (length: ${mnemonic.length}, words: ${words.length})`
  );
  console.log(
    `  💡 Security tip: Ensure mnemonics are stored encrypted and never committed to version control`
  );
}

export interface Config {
  port: number;
  phpBackendBaseUrl: string;
  paymentsCallbackToken: string;
  db: {
    host: string;
    port: number;
    database: string;
    username: string;
    password: string;
  };
  rpc: {
    eth: string;
    bsc: string;
    tron: string;
  };
  usdtContracts: {
    erc20: string;
    bep20: string;
    trc20: string;
  };
  depositMnemonics: {
    eth: string;
    bsc: string;
    tron: string;
  };
}

/**
 * Load and validate configuration from environment variables.
 */
export function loadConfig(): Config {
  const port = parseInt(process.env.PORT || '4000', 10);
  const phpBackendBaseUrl = process.env.PHP_BACKEND_BASE_URL || '';
  const paymentsCallbackToken = process.env.PAYMENTS_CALLBACK_TOKEN || '';

  if (!phpBackendBaseUrl) {
    throw new Error('PHP_BACKEND_BASE_URL environment variable is required');
  }

  if (!paymentsCallbackToken) {
    throw new Error('PAYMENTS_CALLBACK_TOKEN environment variable is required');
  }

  const dbHost = process.env.DB_HOST || 'localhost';
  const dbPort = parseInt(process.env.DB_PORT || '3306', 10);
  const dbDatabase = process.env.DB_DATABASE || '';
  const dbUsername = process.env.DB_USERNAME || '';
  const dbPassword = process.env.DB_PASSWORD || '';

  // Database is optional for integration service
  // if (!dbDatabase || !dbUsername || !dbPassword) {
  //   throw new Error('Database configuration (DB_DATABASE, DB_USERNAME, DB_PASSWORD) is required');
  // }

  const ethRpcUrl = process.env.ETH_RPC_URL || '';
  const bscRpcUrl = process.env.BSC_RPC_URL || '';
  const tronRpcUrl = process.env.TRON_RPC_URL || '';

  const usdtErc20Contract = process.env.USDT_ERC20_CONTRACT || '';
  const usdtBep20Contract = process.env.USDT_BEP20_CONTRACT || '';
  const usdtTrc20Contract = process.env.USDT_TRC20_CONTRACT || '';

  const depositEthMnemonic = process.env.DEPOSIT_ETH_MNEMONIC || '';
  const depositBscMnemonic = process.env.DEPOSIT_BSC_MNEMONIC || '';
  const depositTronMnemonic = process.env.DEPOSIT_TRON_MNEMONIC || '';

  // Validate and warn about mnemonic security
  validateMnemonic('ETH', depositEthMnemonic);
  validateMnemonic('BSC', depositBscMnemonic);
  validateMnemonic('TRON', depositTronMnemonic);

  return {
    port,
    phpBackendBaseUrl,
    paymentsCallbackToken,
    db: {
      host: dbHost,
      port: dbPort,
      database: dbDatabase,
      username: dbUsername,
      password: dbPassword,
    },
    rpc: {
      eth: ethRpcUrl,
      bsc: bscRpcUrl,
      tron: tronRpcUrl,
    },
    usdtContracts: {
      erc20: usdtErc20Contract,
      bep20: usdtBep20Contract,
      trc20: usdtTrc20Contract,
    },
    depositMnemonics: {
      eth: depositEthMnemonic,
      bsc: depositBscMnemonic,
      tron: depositTronMnemonic,
    },
  };
}

