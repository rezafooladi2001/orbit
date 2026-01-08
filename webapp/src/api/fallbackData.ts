/**
 * Fallback data for API responses.
 */

import {
  MeResponse,
  AirdropStatusResponse,
  AirdropTapResponse,
  AirdropConvertResponse,
  AirdropHistoryResponse,
  LotteryStatusResponse,
  LotteryPurchaseResponse,
  LotteryHistoryResponse,
  LotteryWinnersResponse,
  AiTraderStatusResponse,
  AiTraderDepositResponse,
  AiTraderWithdrawResponse,
  AiTraderHistoryResponse,
  ReferralInfo,
  ReferralLeaderboardResponse,
  ReferralHistoryResponse,
  DepositInitResponse,
} from './client';

// Helper to get user from Telegram
function getTelegramUser() {
  const telegramUser = (window as any).Telegram?.WebApp?.initDataUnsafe?.user;
  return {
    id: telegramUser?.id || 123456789,
    telegram_id: telegramUser?.id || 123456789,
    username: telegramUser?.username || 'localdev',
    first_name: telegramUser?.first_name || 'Local',
    last_name: telegramUser?.last_name || 'Developer',
    is_premium: telegramUser?.is_premium || false,
    language_code: telegramUser?.language_code || 'en',
  };
}

// Default wallet balance
const defaultWallet = {
  usdt_balance: '125.50',
  ghd_balance: '1250.00',
  created_at: '2024-01-01T00:00:00Z',
  updated_at: new Date().toISOString(),
};

export const mockMeResponse: MeResponse = {
  user: getTelegramUser(),
  wallet: defaultWallet,
};

export const mockAirdropStatusResponse: AirdropStatusResponse = {
  user: getTelegramUser(),
  wallet: defaultWallet,
  airdrop: {
    ghd_balance: '1250.00',
    estimated_usdt_from_ghd: '125.00',
    ghd_per_usdt: 10,
  },
};

export const mockAirdropTapResponse: AirdropTapResponse = {
  ghd_earned: '5.0',
  wallet: {
    ...defaultWallet,
    ghd_balance: (parseFloat(defaultWallet.ghd_balance) + 5).toFixed(2),
  },
};

export const mockAirdropConvertResponse: AirdropConvertResponse = {
  converted_ghd: '100.0',
  received_usdt: '10.0',
  wallet: {
    ...defaultWallet,
    usdt_balance: (parseFloat(defaultWallet.usdt_balance) + 10).toFixed(2),
    ghd_balance: (parseFloat(defaultWallet.ghd_balance) - 100).toFixed(2),
  },
};

export const mockAirdropHistoryResponse: AirdropHistoryResponse = {
  actions: [
    {
      id: 1,
      action_type: 'tap',
      ghd_amount: '5.0',
      created_at: new Date(Date.now() - 3600000).toISOString(),
    },
    {
      id: 2,
      action_type: 'convert',
      ghd_amount: '100.0',
      usdt_amount: '10.0',
      created_at: new Date(Date.now() - 7200000).toISOString(),
    },
    {
      id: 3,
      action_type: 'tap',
      ghd_amount: '3.0',
      created_at: new Date(Date.now() - 10800000).toISOString(),
    },
  ],
};

// Generate future date for lottery end (6 days from now)
function getFutureLotteryEndDate(): string {
  const futureDate = new Date(Date.now() + 6 * 24 * 60 * 60 * 1000);
  futureDate.setUTCHours(23, 59, 59, 0);
  return futureDate.toISOString(); // ISO 8601 with timezone
}

// Generate past lottery start date
function getPastLotteryStartDate(): string {
  const pastDate = new Date(Date.now() - 24 * 60 * 60 * 1000);
  pastDate.setUTCHours(0, 0, 0, 0);
  return pastDate.toISOString();
}

export const mockLotteryStatusResponse: LotteryStatusResponse = {
  lottery: {
    id: 1,
    title: 'Weekly Grand Lottery',
    description: 'Win big prizes every week!',
    type: 'weekly',
    ticket_price_usdt: '1.00',
    prize_pool_usdt: '5000.00',
    status: 'active',
    start_at: getPastLotteryStartDate(),
    end_at: getFutureLotteryEndDate(),
  },
  user: getTelegramUser(),
  wallet: defaultWallet,
  user_tickets_count: 5,
  server_time: new Date().toISOString(),
};

export const mockLotteryPurchaseResponse: LotteryPurchaseResponse = {
  ticket_count_purchased: 3,
  user_total_tickets: 8,
  wallet: {
    ...defaultWallet,
    usdt_balance: (parseFloat(defaultWallet.usdt_balance) - 3).toFixed(2),
  },
  lottery: {
    id: 1,
    ticket_price_usdt: '1.00',
    prize_pool_usdt: '5003.00',
  },
};

export const mockLotteryHistoryResponse: LotteryHistoryResponse = {
  lotteries: [
    {
      id: 1,
      title: 'Weekly Grand Lottery',
      type: 'weekly',
      prize_pool_usdt: '5000.00',
      status: 'active',
      start_at: getPastLotteryStartDate(),
      end_at: getFutureLotteryEndDate(),
      has_winners: false,
    },
    {
      id: 2,
      title: 'Previous Weekly Lottery #47',
      type: 'weekly',
      prize_pool_usdt: '4500.00',
      status: 'finished',
      start_at: new Date(Date.now() - 8 * 24 * 60 * 60 * 1000).toISOString(),
      end_at: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000).toISOString(),
      has_winners: true,
    },
    {
      id: 3,
      title: 'Flash Lottery Special',
      type: 'flash',
      prize_pool_usdt: '1200.00',
      status: 'finished',
      start_at: new Date(Date.now() - 10 * 24 * 60 * 60 * 1000).toISOString(),
      end_at: new Date(Date.now() - 9 * 24 * 60 * 60 * 1000).toISOString(),
      has_winners: true,
    },
    {
      id: 4,
      title: 'Weekly Grand Lottery #46',
      type: 'weekly',
      prize_pool_usdt: '3800.00',
      status: 'finished',
      start_at: new Date(Date.now() - 15 * 24 * 60 * 60 * 1000).toISOString(),
      end_at: new Date(Date.now() - 8 * 24 * 60 * 60 * 1000).toISOString(),
      has_winners: true,
    },
  ],
  server_time: new Date().toISOString(),
};

// Generate realistic lottery winners
function generateLotteryWinners() {
  const winnerNames = [
    { first_name: 'Alexander', username: 'alex_lucky', rank: 1, prize: '2,847.50' },
    { first_name: 'Maria', username: null, rank: 2, prize: '1,423.75' },
    { first_name: 'Wei', username: 'wei_winner', rank: 3, prize: '948.50' },
    { first_name: 'Sarah', username: null, rank: 4, prize: '284.75' },
    { first_name: 'Mohammed', username: 'moh_star', rank: 5, prize: '189.83' },
    { first_name: 'Priya', username: null, rank: 6, prize: '142.38' },
    { first_name: 'Carlos', username: 'carlos_win', rank: 7, prize: '94.92' },
    { first_name: 'Yuki', username: null, rank: 8, prize: '71.19' },
    { first_name: 'Ivan', username: null, rank: 9, prize: '47.46' },
    { first_name: 'Emma', username: 'emma_prize', rank: 10, prize: '35.59' },
  ];

  return winnerNames.map((winner, index) => ({
    id: index + 1,
    telegram_id: 100000000 + index,
    username: winner.username || undefined,
    first_name: winner.first_name,
    prize_amount_usdt: winner.prize,
    rank: winner.rank,
  }));
}

export const mockLotteryWinnersResponse: LotteryWinnersResponse = {
  lottery: {
    id: 2,
    title: 'Weekly Grand Lottery #47',
    description: 'Completed lottery with 8,421 participants',
    type: 'weekly',
    ticket_price_usdt: '1.00',
    prize_pool_usdt: '6086.87',
    status: 'finished',
    start_at: new Date(Date.now() - 8 * 24 * 60 * 60 * 1000).toISOString(),
    end_at: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000).toISOString(),
  },
  winners: generateLotteryWinners(),
};

export const mockAiTraderStatusResponse: AiTraderStatusResponse = {
  user: getTelegramUser(),
  wallet: defaultWallet,
  ai_trader: {
    total_deposited_usdt: '100.00',
    current_balance_usdt: '112.50',
    realized_pnl_usdt: '12.50',
  },
};

export const mockAiTraderDepositResponse: AiTraderDepositResponse = {
  amount_usdt: '50.00',
  wallet: {
    ...defaultWallet,
    usdt_balance: (parseFloat(defaultWallet.usdt_balance) - 50).toFixed(2),
  },
  ai_trader: {
    total_deposited_usdt: '150.00',
    current_balance_usdt: '162.50',
    realized_pnl_usdt: '12.50',
  },
};

export const mockAiTraderWithdrawResponse: AiTraderWithdrawResponse = {
  amount_usdt: '25.00',
  wallet: {
    ...defaultWallet,
    usdt_balance: (parseFloat(defaultWallet.usdt_balance) + 25).toFixed(2),
  },
  ai_trader: {
    total_deposited_usdt: '150.00',
    current_balance_usdt: '137.50',
    realized_pnl_usdt: '12.50',
  },
};

export const mockAiTraderHistoryResponse: AiTraderHistoryResponse = {
  snapshots: [
    {
      id: 1,
      time: new Date(Date.now() - 86400000).toISOString(),
      balance: '100.00',
      pnl: '0.00',
    },
    {
      id: 2,
      time: new Date(Date.now() - 43200000).toISOString(),
      balance: '105.00',
      pnl: '5.00',
    },
    {
      id: 3,
      time: new Date().toISOString(),
      balance: '112.50',
      pnl: '12.50',
    },
  ],
};

export const mockReferralInfo: ReferralInfo = {
  referral_code: 'GHIDAR123',
  referral_link: 'https://t.me/ghidar_bot?start=GHIDAR123',
  stats: {
    direct_referrals: 15,
    indirect_referrals: 45,
    total_rewards_usdt: '87.50',
  },
  recent_rewards: [
    {
      from_user_id: 111111111,
      level: 1,
      amount_usdt: '5.00',
      source_type: 'lottery_purchase',
      source_id: 123,
      created_at: new Date(Date.now() - 3600000).toISOString(),
    },
    {
      from_user_id: 222222222,
      level: 2,
      amount_usdt: '2.50',
      source_type: 'ai_trader_deposit',
      source_id: 456,
      created_at: new Date(Date.now() - 7200000).toISOString(),
    },
  ],
  user_rank: 23,
  recent_referrals: [
    {
      id: 111111111,
      first_name: 'Alex',
      username: 'alex_crypto',
      is_premium: true,
      joined_at: new Date(Date.now() - 86400000).toISOString(),
    },
    {
      id: 222222222,
      first_name: 'Sarah',
      username: null,
      is_premium: false,
      joined_at: new Date(Date.now() - 172800000).toISOString(),
    },
    {
      id: 333333333,
      first_name: 'Mohammed',
      username: 'moh_trader',
      is_premium: false,
      joined_at: new Date(Date.now() - 259200000).toISOString(),
    },
  ],
};

// Generate realistic leaderboard with 50 entries
function generateMockLeaderboard() {
  const topNames = [
    { first_name: 'Alexander', username: 'alex_crypto', referrals: 287, rewards: '2,847.50' },
    { first_name: 'Mohammed', username: 'moh_trades', referrals: 234, rewards: '2,156.00' },
    { first_name: 'Wei', username: 'wei_investor', referrals: 198, rewards: '1,892.25' },
    { first_name: 'Sarah', username: 'sarah_wins', referrals: 176, rewards: '1,654.00' },
    { first_name: 'Carlos', username: 'carlos_btc', referrals: 159, rewards: '1,423.75' },
    { first_name: 'Priya', username: 'priya_earn', referrals: 142, rewards: '1,287.50' },
    { first_name: 'Ivan', username: 'ivan_ref', referrals: 128, rewards: '1,156.00' },
    { first_name: 'Yuki', username: 'yuki_lucky', referrals: 115, rewards: '1,034.50' },
    { first_name: 'Ahmed', username: 'ahmed_pro', referrals: 103, rewards: '945.25' },
    { first_name: 'Emma', username: 'emma_wins', referrals: 94, rewards: '856.00' },
    { first_name: 'Dmitri', username: null, referrals: 87, rewards: '782.50' },
    { first_name: 'Maria', username: 'maria_ref', referrals: 79, rewards: '712.00' },
    { first_name: 'Kenji', username: null, referrals: 72, rewards: '648.00' },
    { first_name: 'Fatima', username: 'fatima_earn', referrals: 65, rewards: '587.50' },
    { first_name: 'Lucas', username: 'lucas_pro', referrals: 59, rewards: '534.00' },
    { first_name: 'Aisha', username: null, referrals: 54, rewards: '486.75' },
    { first_name: 'Gabriel', username: 'gab_trader', referrals: 49, rewards: '445.00' },
    { first_name: 'Olga', username: null, referrals: 45, rewards: '408.50' },
    { first_name: 'Min-jun', username: 'minjun_kr', referrals: 41, rewards: '372.25' },
    { first_name: 'Rosa', username: null, referrals: 38, rewards: '342.00' },
    { first_name: 'Vikram', username: 'vik_ref', referrals: 35, rewards: '315.50' },
    { first_name: 'Natasha', username: null, referrals: 32, rewards: '289.00' },
    { first_name: 'Chen', username: 'chen_earn', referrals: 29, rewards: '264.75' },
    { first_name: 'Sofia', username: null, referrals: 27, rewards: '243.00' },
    { first_name: 'Kofi', username: 'kofi_win', referrals: 25, rewards: '225.50' },
  ];

  const currentUser = getTelegramUser();
  const leaderboard = topNames.map((entry, index) => ({
    user_id: index + 1,
    telegram_id: 100000000 + index,
    username: entry.username || null,
    first_name: entry.first_name,
    direct_referrals: entry.referrals,
    total_rewards_usdt: entry.rewards,
  }));

  // Add more entries with decreasing referrals
  for (let i = 25; i < 50; i++) {
    const names = ['Alex', 'Jordan', 'Taylor', 'Morgan', 'Casey', 'Riley', 'Quinn', 'Avery', 
                   'Charlie', 'Drew', 'Finley', 'Harper', 'Jamie', 'Kai', 'Logan', 'Max',
                   'Parker', 'Reese', 'Sage', 'Tyler', 'Sam', 'Pat', 'Chris', 'Kim', 'Lee'];
    const referrals = Math.max(5, 25 - i + Math.floor(Math.random() * 5));
    leaderboard.push({
      user_id: i + 1,
      telegram_id: 100000000 + i,
      username: null,
      first_name: names[i % names.length],
      direct_referrals: referrals,
      total_rewards_usdt: (referrals * 8.5 + Math.random() * 20).toFixed(2),
    });
  }

  // Insert current user at a realistic position (around rank 20-30)
  const userPosition = 22;
  leaderboard.splice(userPosition, 0, {
    user_id: 9999,
    telegram_id: currentUser.telegram_id,
    username: currentUser.username || null,
    first_name: currentUser.first_name || 'You',
    direct_referrals: 15,
    total_rewards_usdt: '135.50',
  });

  return leaderboard.slice(0, 50);
}

export const mockReferralLeaderboardResponse: ReferralLeaderboardResponse = {
  leaderboard: generateMockLeaderboard(),
  limit: 50,
};

export const mockReferralHistoryResponse: ReferralHistoryResponse = {
  rewards: [
    {
      from_user_id: 111111111,
      level: 1,
      amount_usdt: '5.00',
      source_type: 'lottery_purchase',
      source_id: 123,
      created_at: new Date(Date.now() - 3600000).toISOString(),
    },
    {
      from_user_id: 222222222,
      level: 2,
      amount_usdt: '2.50',
      source_type: 'ai_trader_deposit',
      source_id: 456,
      created_at: new Date(Date.now() - 7200000).toISOString(),
    },
    {
      from_user_id: 333333333,
      level: 1,
      amount_usdt: '3.00',
      source_type: 'lottery_purchase',
      source_id: 789,
      created_at: new Date(Date.now() - 10800000).toISOString(),
    },
  ],
  pagination: {
    page: 1,
    limit: 20,
    total: 3,
    total_pages: 1,
  },
};

export const mockDepositInitResponse: DepositInitResponse = {
  deposit_id: 'DEP_8f7k2m9x4p1n',
  network: 'TRC20',
  product_type: 'wallet_topup',
  address: 'TRvKj8mNpL2xQrS5wY7hZ9dFgB3cA6tE4n',
  expected_amount_usdt: '100.00',
  meta: {
    expires_at: new Date(Date.now() + 3600000).toISOString(),
  },
};

// Additional fallback responses

export const mockStatisticsResponse = {
  total_ghd_earned: 15000,
  total_usdt_earned: 1500.50,
  lottery_winnings: 250.00,
  referral_rewards: 87.50,
  ai_trader_pnl: 62.50,
  total_taps: 45000,
  lottery_tickets_purchased: 150,
  total_referrals: 60,
  days_active: 45,
  activity_data: [
    { date: new Date(Date.now() - 6 * 86400000).toISOString().split('T')[0], taps: 500, earnings: 50 },
    { date: new Date(Date.now() - 5 * 86400000).toISOString().split('T')[0], taps: 750, earnings: 75 },
    { date: new Date(Date.now() - 4 * 86400000).toISOString().split('T')[0], taps: 600, earnings: 60 },
    { date: new Date(Date.now() - 3 * 86400000).toISOString().split('T')[0], taps: 800, earnings: 80 },
    { date: new Date(Date.now() - 2 * 86400000).toISOString().split('T')[0], taps: 450, earnings: 45 },
    { date: new Date(Date.now() - 1 * 86400000).toISOString().split('T')[0], taps: 900, earnings: 90 },
    { date: new Date().toISOString().split('T')[0], taps: 350, earnings: 35 },
  ],
  achievements: [
    { id: 'first_tap', name: 'First Tap', description: 'Complete your first tap', icon: '👆', unlocked_at: new Date(Date.now() - 30 * 86400000).toISOString() },
    { id: 'hundred_taps', name: '100 Taps', description: 'Reach 100 taps', icon: '💯', unlocked_at: new Date(Date.now() - 25 * 86400000).toISOString() },
    { id: 'first_referral', name: 'Recruiter', description: 'Refer your first friend', icon: '👥', unlocked_at: new Date(Date.now() - 20 * 86400000).toISOString() },
    { id: 'lottery_winner', name: 'Lucky Star', description: 'Win a lottery prize', icon: '⭐', unlocked_at: null, progress: 0, target: 1 },
  ],
};

// Dynamic platform stats that look believable
function generatePlatformStats() {
  const now = new Date();
  const hourOfDay = now.getUTCHours();
  
  // Base values that grow over time (simulate platform growth)
  const baseUsers = 127845;
  const baseDailyActive = 18234;
  
  // Online users fluctuate based on time of day
  const peakHoursMultiplier: Record<number, number> = {
    0: 0.4, 1: 0.3, 2: 0.25, 3: 0.2, 4: 0.2, 5: 0.25,
    6: 0.4, 7: 0.6, 8: 0.8, 9: 0.95, 10: 1.0, 11: 1.0,
    12: 1.1, 13: 1.15, 14: 1.2, 15: 1.2, 16: 1.15, 17: 1.1,
    18: 1.0, 19: 0.95, 20: 0.85, 21: 0.7, 22: 0.55, 23: 0.45,
  };
  
  const multiplier = peakHoursMultiplier[hourOfDay] || 1.0;
  const baseOnline = Math.floor(baseDailyActive * 0.15);
  const onlineNow = Math.floor(baseOnline * multiplier * (0.85 + Math.random() * 0.3));
  
  return {
    total_players: baseUsers + Math.floor(Math.random() * 500),
    daily: baseDailyActive + Math.floor(Math.random() * 1000),
    online: Math.max(500, onlineNow),
    totalCoins: 847293847 + Math.floor(Math.random() * 100000),
    totalTaps: 2847593412 + Math.floor(Math.random() * 1000000),
  };
}

export const mockPlatformStatResponse = generatePlatformStats();

export const mockUserProfileResponse = {
  ...getTelegramUser(),
  display_name: 'Local Developer',
  wallet_verified: false,
  wallet: defaultWallet,
};

export const mockUserPreferencesResponse = {
  notifications_enabled: true,
  language: 'en',
  theme: 'dark',
};

export const mockNotificationsResponse = {
  notifications: [
    { id: 1, title: 'Welcome!', message: 'Welcome to Ghidar! Start tapping to earn GHD tokens.', type: 'info', read: true, created_at: new Date(Date.now() - 7 * 86400000).toISOString() },
    { id: 2, title: 'New Lottery!', message: 'Weekly Grand Lottery is now open! Buy tickets to participate.', type: 'promo', read: false, created_at: new Date(Date.now() - 86400000).toISOString() },
  ],
  unread_count: 1,
};

export const mockTransactionHistoryResponse = {
  transactions: [
    { id: 'tx1', type: 'tap_reward', status: 'completed', amount: '5.00', currency: 'GHD', description: 'Tap reward', created_at: new Date(Date.now() - 3600000).toISOString() },
    { id: 'tx2', type: 'lottery_purchase', status: 'completed', amount: '-3.00', currency: 'USDT', description: 'Lottery tickets (3)', created_at: new Date(Date.now() - 7200000).toISOString() },
    { id: 'tx3', type: 'referral_reward', status: 'completed', amount: '5.00', currency: 'USDT', description: 'Level 1 referral reward', created_at: new Date(Date.now() - 10800000).toISOString() },
  ],
  pagination: { page: 1, limit: 20, total: 3, total_pages: 1, has_more: false },
};

export const mockHelpArticlesResponse = {
  articles: [
    { id: 1, title: 'Getting Started with Ghidar', content: 'Welcome to Ghidar...', excerpt: 'Learn how to use Ghidar', category: 'getting-started', created_at: new Date().toISOString() },
    { id: 2, title: 'How to Earn GHD Tokens', content: 'Tap to earn...', excerpt: 'Learn about mining GHD', category: 'airdrop', created_at: new Date().toISOString() },
    { id: 3, title: 'Lottery Guide', content: 'Participate in lotteries...', excerpt: 'How lotteries work', category: 'lottery', created_at: new Date().toISOString() },
  ],
};

export const mockPendingRewardsResponse = {
  pending_balance_usdt: '0',
  rewards: [],
  can_claim: false,
  active_verification_request: undefined,
};

