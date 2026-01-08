/**
 * Settings Types for Ghidar Mini App
 * Proper TypeScript interfaces for all settings-related components
 */

// ==================== User Profile Types ====================

export interface WalletInfo {
  usdt_balance: string;
  ghd_balance: string;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface UserProfile {
  id: number;
  telegram_id: number;
  username: string | null;
  first_name: string | null;
  last_name: string | null;
  is_premium?: boolean;
  language_code?: string;
  joining_date?: number | null;
  display_name?: string;
  wallet_verified?: boolean;
  wallet?: WalletInfo;
  photo_url?: string | null;
}

export interface TelegramUserInfo {
  id: number;
  first_name?: string;
  last_name?: string;
  username?: string;
  language_code?: string;
  is_premium?: boolean;
  photo_url?: string;
}

// ==================== User Preferences Types ====================

export interface NotificationPreferences {
  lottery_wins: boolean;
  rewards: boolean;
  news: boolean;
  security_alerts: boolean;
}

export interface UserPreferences {
  notifications_enabled: boolean;
  notification_channels?: NotificationPreferences;
  language?: string;
  theme?: 'auto' | 'dark' | 'light';
  haptic_feedback?: boolean;
  sound_effects?: boolean;
}

// ==================== Security Types ====================

export interface SecurityFeature {
  id: string;
  title: string;
  description: string;
  status: 'active' | 'inactive' | 'pending';
  icon?: string;
}

export interface ActiveSession {
  id: string;
  device: string;
  platform: string;
  location?: string;
  last_active: string;
  is_current: boolean;
}

export interface SecurityStatus {
  two_factor_enabled: boolean;
  wallet_verified: boolean;
  security_score: number; // 0-100
  last_security_check?: string;
  active_sessions: ActiveSession[];
  security_recommendations: string[];
}

// ==================== Account Types ====================

export type AccountTier = 'bronze' | 'silver' | 'gold' | 'platinum' | 'diamond';

export interface AccountTierInfo {
  current_tier: AccountTier;
  tier_level: number; // 1-5
  tier_progress: number; // 0-100 percentage to next tier
  next_tier?: AccountTier;
  benefits: string[];
}

export interface AccountActivity {
  total_taps: number;
  lottery_wins: number;
  total_referrals: number;
  ai_trader_deposits: number;
  days_active: number;
  last_activity?: string;
}

export interface AccountInfo {
  tier: AccountTierInfo;
  activity: AccountActivity;
  verification_status: 'verified' | 'pending' | 'not_verified';
  created_at: string;
}

// ==================== About/App Info Types ====================

export interface AppVersion {
  version: string;
  build_number?: string;
  last_updated: string;
  platform: string;
}

export interface FAQItem {
  id: string;
  question: string;
  answer: string;
  category?: string;
}

export interface SocialLink {
  id: string;
  platform: string;
  url: string;
  icon: string;
  label: string;
}

export interface ChangelogEntry {
  version: string;
  date: string;
  changes: string[];
  type: 'major' | 'minor' | 'patch';
}

export interface LegalLink {
  id: string;
  title: string;
  url: string;
  type: 'terms' | 'privacy' | 'licenses' | 'cookies';
}

// ==================== Component Props Types ====================

export interface ProfileSectionProps {
  profile: UserProfile;
  telegramUser: TelegramUserInfo | null;
  onUpdate: (updates: Partial<UserProfile>) => Promise<void>;
}

export interface AccountSettingsProps {
  profile: UserProfile;
  accountInfo?: AccountInfo;
  onNavigateToTransactions?: () => void;
  onRequestDataExport?: () => Promise<void>;
  onRequestAccountDeletion?: () => Promise<void>;
}

export interface SecuritySettingsProps {
  profile: UserProfile;
  securityStatus?: SecurityStatus;
  onUpdate: (updates: Partial<UserProfile>) => Promise<void>;
  onLogoutAllDevices?: () => Promise<void>;
}

export interface AppSettingsProps {
  preferences: UserPreferences;
  onUpdate: (updates: Partial<UserPreferences>) => Promise<void>;
  onResetOnboarding?: () => void;
}

export interface AboutSectionProps {
  appVersion?: AppVersion;
  faqs?: FAQItem[];
  socialLinks?: SocialLink[];
  changelog?: ChangelogEntry[];
  legalLinks?: LegalLink[];
}

// ==================== Settings Screen State ====================

export type SettingsSection = 'profile' | 'account' | 'security' | 'app' | 'about';

export interface SettingsSectionConfig {
  id: SettingsSection;
  label: string;
  icon: string;
  description?: string;
}

export const SETTINGS_SECTIONS: SettingsSectionConfig[] = [
  { id: 'profile', label: 'Profile', icon: 'user', description: 'Your personal information' },
  { id: 'account', label: 'Account', icon: 'wallet', description: 'Account details and activity' },
  { id: 'security', label: 'Security', icon: 'shield', description: 'Security settings and sessions' },
  { id: 'app', label: 'App', icon: 'settings', description: 'App preferences and notifications' },
  { id: 'about', label: 'About', icon: 'info', description: 'About Ghidar and legal info' },
];

// ==================== Constants ====================

export const APP_VERSION: AppVersion = {
  version: '1.0.0',
  build_number: '100',
  last_updated: new Date().toISOString(),
  platform: 'Telegram Mini App',
};

export const DEFAULT_SOCIAL_LINKS: SocialLink[] = [
  { id: 'telegram', platform: 'Telegram', url: 'https://t.me/ghidar', icon: '⚡', label: 'Telegram Channel' },
  { id: 'twitter', platform: 'Twitter', url: 'https://twitter.com/ghidar', icon: '𝕏', label: 'Twitter/X' },
  { id: 'website', platform: 'Website', url: 'https://ghidar.io', icon: '🌐', label: 'Official Website' },
];

export const DEFAULT_LEGAL_LINKS: LegalLink[] = [
  { id: 'terms', title: 'Terms of Service', url: '/terms', type: 'terms' },
  { id: 'privacy', title: 'Privacy Policy', url: '/privacy', type: 'privacy' },
  { id: 'licenses', title: 'Open Source Licenses', url: '/licenses', type: 'licenses' },
];

export const DEFAULT_FAQS: FAQItem[] = [
  {
    id: 'what-is-ghidar',
    question: 'What is Ghidar?',
    answer: 'Ghidar is a Telegram Mini App that provides crypto opportunities including airdrops, lottery, and AI trading.',
    category: 'general',
  },
  {
    id: 'how-to-earn',
    question: 'How can I earn rewards?',
    answer: 'You can earn GHD tokens by tapping, participating in lotteries, referring friends, and using the AI Trader.',
    category: 'earnings',
  },
  {
    id: 'is-it-safe',
    question: 'Is Ghidar safe to use?',
    answer: 'Yes! Ghidar uses Telegram\'s secure authentication and never stores your private keys. All transactions are encrypted.',
    category: 'security',
  },
  {
    id: 'how-to-withdraw',
    question: 'How do I withdraw my earnings?',
    answer: 'You can withdraw USDT to your external wallet after completing wallet verification. Go to your wallet section to start.',
    category: 'wallet',
  },
];

export const DEFAULT_CHANGELOG: ChangelogEntry[] = [
  {
    version: '1.0.0',
    date: '2024-01-01',
    changes: [
      'Initial release of Ghidar Mini App',
      'Airdrop tap-to-earn feature',
      'Lottery system with daily draws',
      'AI Trader integration',
      'Referral program',
    ],
    type: 'major',
  },
];

// ==================== Utility Functions ====================

export function getSecurityScoreColor(score: number): string {
  if (score >= 80) return 'var(--success)';
  if (score >= 50) return 'var(--warning)';
  return 'var(--error)';
}

export function getSecurityScoreLabel(score: number): string {
  if (score >= 80) return 'Excellent';
  if (score >= 60) return 'Good';
  if (score >= 40) return 'Fair';
  return 'Needs Improvement';
}

export function getTierColor(tier: AccountTier): string {
  const colors: Record<AccountTier, string> = {
    bronze: '#cd7f32',
    silver: '#c0c0c0',
    gold: 'var(--brand-gold)',
    platinum: '#e5e4e2',
    diamond: '#b9f2ff',
  };
  return colors[tier];
}

export function formatJoinDate(timestamp: number | null | undefined): string {
  if (!timestamp) return 'Unknown';
  return new Date(timestamp * 1000).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

export function calculateAccountAge(joiningDate: number | null | undefined): string {
  if (!joiningDate) return 'Unknown';
  const now = Date.now();
  const joinTime = joiningDate * 1000;
  const diffDays = Math.floor((now - joinTime) / (1000 * 60 * 60 * 24));
  
  if (diffDays < 1) return 'Today';
  if (diffDays === 1) return '1 day';
  if (diffDays < 30) return `${diffDays} days`;
  if (diffDays < 365) {
    const months = Math.floor(diffDays / 30);
    return months === 1 ? '1 month' : `${months} months`;
  }
  const years = Math.floor(diffDays / 365);
  return years === 1 ? '1 year' : `${years} years`;
}

