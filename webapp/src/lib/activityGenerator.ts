/**
 * Platform Activity Generator
 * Generates live platform activity data.
 */

// ==================== Name Pools ====================

// International name pool (150+ names across cultures)
export const FIRST_NAMES = [
  // Western names
  'James', 'John', 'Robert', 'Michael', 'David', 'William', 'Richard', 'Joseph',
  'Thomas', 'Christopher', 'Daniel', 'Matthew', 'Anthony', 'Andrew', 'Joshua',
  'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan',
  'Jessica', 'Sarah', 'Karen', 'Lisa', 'Nancy', 'Betty', 'Margaret', 'Sandra',
  'Emma', 'Olivia', 'Ava', 'Isabella', 'Sophia', 'Mia', 'Charlotte', 'Amelia',
  'Harper', 'Evelyn', 'Liam', 'Noah', 'Oliver', 'Elijah', 'Lucas', 'Mason',
  
  // Asian names
  'Wei', 'Fang', 'Ming', 'Li', 'Chen', 'Yang', 'Huang', 'Zhao', 'Wu', 'Zhou',
  'Yuki', 'Hana', 'Sakura', 'Kenji', 'Hiroshi', 'Takeshi', 'Akira', 'Yuto',
  'Ji-won', 'Min-jun', 'Seo-yeon', 'Ha-eun', 'Ye-jun', 'Do-yun', 'Soo-min',
  'Priya', 'Raj', 'Amit', 'Sunita', 'Vikram', 'Neha', 'Arun', 'Deepa',
  
  // Middle Eastern names
  'Mohammed', 'Ahmed', 'Ali', 'Hassan', 'Omar', 'Fatima', 'Aisha', 'Maryam',
  'Yusuf', 'Ibrahim', 'Khalid', 'Noor', 'Layla', 'Zara', 'Sara', 'Hana',
  
  // Latin/Hispanic names
  'Carlos', 'Miguel', 'Jose', 'Luis', 'Antonio', 'Juan', 'Pedro', 'Diego',
  'Maria', 'Ana', 'Rosa', 'Carmen', 'Sofia', 'Isabella', 'Valentina', 'Lucia',
  'Gabriel', 'Rafael', 'Fernando', 'Alejandro', 'Pablo', 'Ricardo',
  
  // Eastern European names
  'Ivan', 'Dmitri', 'Alexei', 'Sergei', 'Vladimir', 'Andrei', 'Nikolai',
  'Olga', 'Natasha', 'Irina', 'Elena', 'Tatiana', 'Anna', 'Katya',
  
  // African names
  'Kwame', 'Kofi', 'Ama', 'Akua', 'Chidi', 'Ngozi', 'Amara', 'Zuri',
  'Jabari', 'Imani', 'Ayana', 'Malik', 'Tariq', 'Aaliyah',
  
  // Additional popular names
  'Alex', 'Jordan', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Quinn', 'Avery',
  'Charlie', 'Drew', 'Finley', 'Harper', 'Jamie', 'Kai', 'Logan', 'Max',
  'Parker', 'Reese', 'Sage', 'Tyler', 'Sam', 'Pat', 'Chris', 'Kim',
];

// Trading pairs for AI Trader
export const TRADING_PAIRS = [
  { symbol: 'BTC/USDT', icon: '₿', color: '#F7931A', name: 'Bitcoin' },
  { symbol: 'ETH/USDT', icon: 'Ξ', color: '#627EEA', name: 'Ethereum' },
  { symbol: 'SOL/USDT', icon: '◎', color: '#00FFA3', name: 'Solana' },
  { symbol: 'BNB/USDT', icon: '⬡', color: '#F3BA2F', name: 'BNB' },
  { symbol: 'ADA/USDT', icon: '₳', color: '#0033AD', name: 'Cardano' },
  { symbol: 'XRP/USDT', icon: '✕', color: '#23292F', name: 'XRP' },
  { symbol: 'DOGE/USDT', icon: 'Ð', color: '#C2A633', name: 'Dogecoin' },
  { symbol: 'MATIC/USDT', icon: '⬡', color: '#8247E5', name: 'Polygon' },
  { symbol: 'DOT/USDT', icon: '●', color: '#E6007A', name: 'Polkadot' },
  { symbol: 'AVAX/USDT', icon: '🔺', color: '#E84142', name: 'Avalanche' },
];

// ==================== Configuration ====================

export const ACTIVITY_CONFIG = {
  // Platform base statistics (believable starting points)
  baseStats: {
    totalUsers: 127845,      // ~128K users - believable for a growing platform
    dailyActiveUsers: 18234, // ~14% DAU rate - realistic
    onlineUsers: 2847,       // ~2.2% online at any time - realistic
    totalPaidOut: 2847392,   // ~$2.8M total paid - shows success without being absurd
    totalTrades: 4582917,    // ~4.5M trades - shows activity
    totalLotteryWinners: 8421, // Believable winner count
  },
  
  // Growth rates (per hour)
  growthRates: {
    users: { min: 3, max: 12 },           // 3-12 new users per hour
    paidOut: { min: 150, max: 890 },      // $150-890 paid out per hour
    trades: { min: 450, max: 1200 },      // 450-1200 trades per hour
    winners: { min: 1, max: 5 },          // 1-5 lottery winners per hour
  },
  
  // Activity patterns by hour (UTC) - multipliers
  hourlyPatterns: {
    0: 0.4, 1: 0.3, 2: 0.25, 3: 0.2, 4: 0.2, 5: 0.25,
    6: 0.4, 7: 0.6, 8: 0.8, 9: 0.95, 10: 1.0, 11: 1.0,
    12: 1.1, 13: 1.15, 14: 1.2, 15: 1.2, 16: 1.15, 17: 1.1,
    18: 1.0, 19: 0.95, 20: 0.85, 21: 0.7, 22: 0.55, 23: 0.45,
  } as Record<number, number>,
  
  // Weekend boost multiplier
  weekendBoost: 1.15,
  
  // Amount ranges for different activities
  amounts: {
    deposit: { min: 50, max: 2000, common: { min: 100, max: 500 } },
    lotteryWin: { min: 5, max: 5000, common: { min: 10, max: 250 } },
    aiTraderProfit: { min: 0.5, max: 75, common: { min: 2, max: 25 } },
    aiTraderDeposit: { min: 100, max: 5000, common: { min: 200, max: 1000 } },
    referralReward: { min: 1, max: 50, common: { min: 3, max: 15 } },
    lotteryTickets: { min: 1, max: 100, common: { min: 1, max: 10 } },
  },
  
  // Event frequencies (events per minute during peak hours)
  eventFrequency: {
    deposit: 0.8,
    lotteryPurchase: 1.2,
    aiTraderDeposit: 0.5,
    aiTraderProfit: 2.0,
    referralJoin: 0.6,
    lotteryWin: 0.15,
  },
};

// ==================== Utility Functions ====================

/**
 * Get a random item from an array
 */
export function randomItem<T>(arr: T[]): T {
  return arr[Math.floor(Math.random() * arr.length)];
}

/**
 * Get a random number between min and max
 */
export function randomBetween(min: number, max: number): number {
  return Math.random() * (max - min) + min;
}

/**
 * Get a random integer between min and max (inclusive)
 */
export function randomIntBetween(min: number, max: number): number {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Generate a weighted random number (biased toward common range)
 */
export function weightedAmount(config: { min: number; max: number; common: { min: number; max: number } }): number {
  // 70% chance of common range, 30% chance of full range
  if (Math.random() < 0.7) {
    return randomBetween(config.common.min, config.common.max);
  }
  return randomBetween(config.min, config.max);
}

/**
 * Get current activity multiplier based on time
 */
export function getActivityMultiplier(): number {
  const now = new Date();
  const hour = now.getUTCHours();
  const dayOfWeek = now.getUTCDay();
  
  let multiplier = ACTIVITY_CONFIG.hourlyPatterns[hour] || 1.0;
  
  // Weekend boost (Saturday = 6, Sunday = 0)
  if (dayOfWeek === 0 || dayOfWeek === 6) {
    multiplier *= ACTIVITY_CONFIG.weekendBoost;
  }
  
  return multiplier;
}

/**
 * Generate a masked username (e.g., "Ale***")
 */
export function generateMaskedName(): string {
  const name = randomItem(FIRST_NAMES);
  const visibleChars = Math.min(3, name.length);
  return name.slice(0, visibleChars) + '***';
}

/**
 * Generate a random time ago string
 */
export function randomTimeAgo(): string {
  const options = [
    'just now',
    '1m ago',
    '2m ago',
    '3m ago',
    '5m ago',
    '8m ago',
    '12m ago',
    '15m ago',
    '20m ago',
    '30m ago',
    '45m ago',
    '1h ago',
  ];
  // Weight toward recent times
  const weights = [0.2, 0.15, 0.12, 0.1, 0.1, 0.08, 0.07, 0.06, 0.05, 0.04, 0.02, 0.01];
  const random = Math.random();
  let cumulative = 0;
  for (let i = 0; i < weights.length; i++) {
    cumulative += weights[i];
    if (random < cumulative) {
      return options[i];
    }
  }
  return options[0];
}

/**
 * Format currency with proper decimals
 */
export function formatCurrency(amount: number, decimals: number = 2): string {
  return amount.toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}

// ==================== Event Generators ====================

export type ActivityEventType = 
  | 'deposit'
  | 'lottery_purchase'
  | 'lottery_win'
  | 'ai_trader_deposit'
  | 'ai_trader_profit'
  | 'referral_join'
  | 'referral_reward';

export interface ActivityEvent {
  id: string;
  type: ActivityEventType;
  userName: string;
  amount: number;
  currency: 'USDT' | 'GHD' | 'tickets';
  extra?: {
    tradingPair?: typeof TRADING_PAIRS[0];
    ticketCount?: number;
    lotteryName?: string;
    rank?: number;
  };
  timeAgo: string;
  timestamp: number;
}

/**
 * Generate a random activity event
 */
export function generateActivityEvent(type?: ActivityEventType): ActivityEvent {
  const eventTypes: ActivityEventType[] = [
    'deposit',
    'lottery_purchase',
    'lottery_win',
    'ai_trader_deposit',
    'ai_trader_profit',
    'referral_join',
    'referral_reward',
  ];
  
  const selectedType = type || randomItem(eventTypes);
  const userName = generateMaskedName();
  const id = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
  
  switch (selectedType) {
    case 'deposit':
      return {
        id,
        type: 'deposit',
        userName,
        amount: Math.round(weightedAmount(ACTIVITY_CONFIG.amounts.deposit)),
        currency: 'USDT',
        timeAgo: randomTimeAgo(),
        timestamp: Date.now(),
      };
      
    case 'lottery_purchase':
      const ticketCount = randomIntBetween(
        ACTIVITY_CONFIG.amounts.lotteryTickets.common.min,
        ACTIVITY_CONFIG.amounts.lotteryTickets.common.max
      );
      return {
        id,
        type: 'lottery_purchase',
        userName,
        amount: ticketCount,
        currency: 'tickets',
        extra: { ticketCount },
        timeAgo: randomTimeAgo(),
        timestamp: Date.now(),
      };
      
    case 'lottery_win':
      const winAmount = weightedAmount(ACTIVITY_CONFIG.amounts.lotteryWin);
      const rank = winAmount > 1000 ? 1 : winAmount > 500 ? randomIntBetween(1, 3) : randomIntBetween(1, 50);
      return {
        id,
        type: 'lottery_win',
        userName,
        amount: Math.round(winAmount * 100) / 100,
        currency: 'USDT',
        extra: { 
          lotteryName: 'Weekly Grand Lottery',
          rank,
        },
        timeAgo: randomTimeAgo(),
        timestamp: Date.now(),
      };
      
    case 'ai_trader_deposit':
      return {
        id,
        type: 'ai_trader_deposit',
        userName,
        amount: Math.round(weightedAmount(ACTIVITY_CONFIG.amounts.aiTraderDeposit)),
        currency: 'USDT',
        timeAgo: randomTimeAgo(),
        timestamp: Date.now(),
      };
      
    case 'ai_trader_profit':
      return {
        id,
        type: 'ai_trader_profit',
        userName,
        amount: Math.round(weightedAmount(ACTIVITY_CONFIG.amounts.aiTraderProfit) * 100) / 100,
        currency: 'USDT',
        extra: { tradingPair: randomItem(TRADING_PAIRS) },
        timeAgo: randomTimeAgo(),
        timestamp: Date.now(),
      };
      
    case 'referral_join':
      return {
        id,
        type: 'referral_join',
        userName,
        amount: 0,
        currency: 'USDT',
        timeAgo: randomTimeAgo(),
        timestamp: Date.now(),
      };
      
    case 'referral_reward':
      return {
        id,
        type: 'referral_reward',
        userName,
        amount: Math.round(weightedAmount(ACTIVITY_CONFIG.amounts.referralReward) * 100) / 100,
        currency: 'USDT',
        timeAgo: randomTimeAgo(),
        timestamp: Date.now(),
      };
      
    default:
      return {
        id,
        type: 'deposit',
        userName,
        amount: Math.round(weightedAmount(ACTIVITY_CONFIG.amounts.deposit)),
        currency: 'USDT',
        timeAgo: randomTimeAgo(),
        timestamp: Date.now(),
      };
  }
}

/**
 * Generate multiple activity events
 */
export function generateActivityEvents(count: number): ActivityEvent[] {
  const events: ActivityEvent[] = [];
  
  // Weight event types by frequency
  const typeWeights: { type: ActivityEventType; weight: number }[] = [
    { type: 'ai_trader_profit', weight: 0.30 },
    { type: 'lottery_purchase', weight: 0.25 },
    { type: 'deposit', weight: 0.15 },
    { type: 'ai_trader_deposit', weight: 0.12 },
    { type: 'referral_join', weight: 0.08 },
    { type: 'referral_reward', weight: 0.06 },
    { type: 'lottery_win', weight: 0.04 },
  ];
  
  for (let i = 0; i < count; i++) {
    const random = Math.random();
    let cumulative = 0;
    let selectedType: ActivityEventType = 'deposit';
    
    for (const { type, weight } of typeWeights) {
      cumulative += weight;
      if (random < cumulative) {
        selectedType = type;
        break;
      }
    }
    
    events.push(generateActivityEvent(selectedType));
  }
  
  return events;
}

// ==================== Statistics Generators ====================

/**
 * Get dynamic platform statistics with time-based variations
 */
export function getPlatformStats(): {
  totalUsers: number;
  dailyActive: number;
  onlineNow: number;
  totalPaidOut: number;
  totalTrades: number;
  todaysWinners: number;
} {
  const now = Date.now();
  const startOfDay = new Date();
  startOfDay.setUTCHours(0, 0, 0, 0);
  const hoursSinceStart = (now - startOfDay.getTime()) / (1000 * 60 * 60);
  
  const multiplier = getActivityMultiplier();
  const { baseStats, growthRates } = ACTIVITY_CONFIG;
  
  // Calculate growth since "start" (using a seed date)
  const seedDate = new Date('2024-01-01').getTime();
  const daysSinceSeed = (now - seedDate) / (1000 * 60 * 60 * 24);
  
  // Total users grow slowly over time
  const totalUsers = Math.floor(
    baseStats.totalUsers + (daysSinceSeed * randomBetween(growthRates.users.min, growthRates.users.max) * 24)
  );
  
  // Daily active is a percentage of total with some variance
  const dau = Math.floor(totalUsers * randomBetween(0.12, 0.16));
  
  // Online now fluctuates with time of day
  const baseOnline = Math.floor(dau * 0.15);
  const onlineNow = Math.floor(baseOnline * multiplier * randomBetween(0.85, 1.15));
  
  // Total paid out grows continuously
  const totalPaidOut = Math.floor(
    baseStats.totalPaidOut + (daysSinceSeed * randomBetween(growthRates.paidOut.min, growthRates.paidOut.max) * 24)
  );
  
  // Total trades grow continuously
  const totalTrades = Math.floor(
    baseStats.totalTrades + (daysSinceSeed * randomBetween(growthRates.trades.min, growthRates.trades.max) * 24)
  );
  
  // Today's winners based on hours passed
  const todaysWinners = Math.floor(
    hoursSinceStart * randomBetween(growthRates.winners.min, growthRates.winners.max) / 2
  );
  
  return {
    totalUsers,
    dailyActive: dau,
    onlineNow: Math.max(500, onlineNow), // Minimum 500 online
    totalPaidOut,
    totalTrades,
    todaysWinners: Math.max(0, todaysWinners),
  };
}

/**
 * Generate fluctuating online user count
 */
export function generateOnlineCount(baseCount: number): number {
  const variance = Math.floor(baseCount * 0.02); // 2% variance
  const change = randomIntBetween(-variance, variance);
  return Math.max(500, baseCount + change);
}

// ==================== Leaderboard Generators ====================

export interface LeaderboardEntry {
  id: number;
  rank: number;
  userName: string;
  maskedName: string;
  referralCount: number;
  earnings: number;
  isTopTier: boolean;
}

/**
 * Generate a realistic referral leaderboard
 */
export function generateReferralLeaderboard(count: number = 50): LeaderboardEntry[] {
  const entries: LeaderboardEntry[] = [];
  
  // Top performers (exponential distribution)
  for (let i = 0; i < count; i++) {
    const rank = i + 1;
    const name = randomItem(FIRST_NAMES);
    
    // Referral count follows power law (top users have way more)
    let referralCount: number;
    let earnings: number;
    
    if (rank <= 3) {
      referralCount = randomIntBetween(180, 350);
      earnings = referralCount * randomBetween(8, 15);
    } else if (rank <= 10) {
      referralCount = randomIntBetween(80, 180);
      earnings = referralCount * randomBetween(6, 10);
    } else if (rank <= 25) {
      referralCount = randomIntBetween(30, 80);
      earnings = referralCount * randomBetween(4, 8);
    } else {
      referralCount = randomIntBetween(5, 30);
      earnings = referralCount * randomBetween(3, 6);
    }
    
    entries.push({
      id: 100000 + i,
      rank,
      userName: name,
      maskedName: name.slice(0, 3) + '***',
      referralCount,
      earnings: Math.round(earnings * 100) / 100,
      isTopTier: rank <= 10,
    });
  }
  
  // Sort by referral count (ensure proper ordering)
  entries.sort((a, b) => b.referralCount - a.referralCount);
  
  // Re-assign ranks after sorting
  entries.forEach((entry, index) => {
    entry.rank = index + 1;
  });
  
  return entries;
}

// ==================== Lottery Winner Generators ====================

export interface LotteryWinner {
  id: number;
  userName: string;
  maskedName: string;
  prizeAmount: number;
  rank: number;
  lotteryName: string;
  wonAt: string;
}

/**
 * Generate recent lottery winners
 */
export function generateRecentLotteryWinners(count: number = 10): LotteryWinner[] {
  const winners: LotteryWinner[] = [];
  const lotteryNames = ['Weekly Grand Lottery', 'Daily Mini Draw', 'Flash Lottery'];
  
  for (let i = 0; i < count; i++) {
    const name = randomItem(FIRST_NAMES);
    const rank = i < 3 ? i + 1 : randomIntBetween(4, 50);
    
    let prizeAmount: number;
    if (rank === 1) {
      prizeAmount = randomBetween(1000, 5000);
    } else if (rank <= 3) {
      prizeAmount = randomBetween(300, 1000);
    } else if (rank <= 10) {
      prizeAmount = randomBetween(50, 300);
    } else {
      prizeAmount = randomBetween(5, 50);
    }
    
    winners.push({
      id: 200000 + i,
      userName: name,
      maskedName: name.slice(0, 3) + '***',
      prizeAmount: Math.round(prizeAmount * 100) / 100,
      rank,
      lotteryName: rank === 1 ? 'Weekly Grand Lottery' : randomItem(lotteryNames),
      wonAt: randomTimeAgo(),
    });
  }
  
  return winners;
}

// ==================== AI Trader Activity Generators ====================

export interface AITraderActivity {
  id: string;
  userName: string;
  type: 'profit' | 'trade' | 'deposit';
  amount: number;
  tradingPair?: typeof TRADING_PAIRS[0];
  direction?: 'long' | 'short';
  timestamp: number;
}

/**
 * Generate AI Trader live activity
 */
export function generateAITraderActivity(): AITraderActivity {
  const type = Math.random() > 0.3 ? 'profit' : (Math.random() > 0.5 ? 'trade' : 'deposit');
  const userName = generateMaskedName();
  const pair = randomItem(TRADING_PAIRS);
  
  let amount: number;
  if (type === 'profit') {
    amount = weightedAmount(ACTIVITY_CONFIG.amounts.aiTraderProfit);
  } else if (type === 'trade') {
    amount = randomBetween(50, 500);
  } else {
    amount = weightedAmount(ACTIVITY_CONFIG.amounts.aiTraderDeposit);
  }
  
  return {
    id: Math.random().toString(36).substring(7),
    userName,
    type,
    amount: Math.round(amount * 100) / 100,
    tradingPair: pair,
    direction: Math.random() > 0.5 ? 'long' : 'short',
    timestamp: Date.now(),
  };
}

// ==================== Export Everything ====================

export default {
  FIRST_NAMES,
  TRADING_PAIRS,
  ACTIVITY_CONFIG,
  generateMaskedName,
  generateActivityEvent,
  generateActivityEvents,
  getPlatformStats,
  generateOnlineCount,
  generateReferralLeaderboard,
  generateRecentLotteryWinners,
  generateAITraderActivity,
  getActivityMultiplier,
  randomTimeAgo,
  formatCurrency,
  randomItem,
  randomBetween,
  randomIntBetween,
  weightedAmount,
};

