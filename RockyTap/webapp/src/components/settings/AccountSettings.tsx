import { useState, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, useToast } from '../ui';
import { ChevronRightIcon, HistoryIcon, WarningIcon } from '../Icons';
import { hapticFeedback } from '../../lib/telegram';
import type { AccountSettingsProps, AccountTier } from '../../types/settings';
import { getTierColor } from '../../types/settings';
import styles from './AccountSettings.module.css';

// Tier configurations
const TIER_CONFIG: Record<AccountTier, { label: string; minBalance: number; icon: string }> = {
  bronze: { label: 'Bronze', minBalance: 0, icon: '🥉' },
  silver: { label: 'Silver', minBalance: 100, icon: '🥈' },
  gold: { label: 'Gold', minBalance: 500, icon: '🥇' },
  platinum: { label: 'Platinum', minBalance: 2000, icon: '💎' },
  diamond: { label: 'Diamond', minBalance: 10000, icon: '👑' },
};

function calculateTier(totalEarnings: number): { tier: AccountTier; progress: number; next?: AccountTier } {
  const tiers: AccountTier[] = ['bronze', 'silver', 'gold', 'platinum', 'diamond'];
  
  for (let i = tiers.length - 1; i >= 0; i--) {
    const tierName = tiers[i];
    const config = TIER_CONFIG[tierName];
    
    if (totalEarnings >= config.minBalance) {
      const nextTier = tiers[i + 1];
      let progress = 100;
      
      if (nextTier) {
        const nextConfig = TIER_CONFIG[nextTier];
        const current = totalEarnings - config.minBalance;
        const required = nextConfig.minBalance - config.minBalance;
        progress = Math.min(Math.round((current / required) * 100), 100);
      }
      
      return { tier: tierName, progress, next: nextTier };
    }
  }
  
  return { tier: 'bronze', progress: 0, next: 'silver' };
}

export function AccountSettings({ 
  profile, 
  onNavigateToTransactions,
  onRequestDataExport,
  onRequestAccountDeletion 
}: AccountSettingsProps) {
  const [exportLoading, setExportLoading] = useState(false);
  const [deletionRequested, setDeletionRequested] = useState(false);
  const { showSuccess, showError } = useToast();

  // Calculate account tier based on total earnings
  const totalEarnings = useMemo(() => {
    const usdtBalance = parseFloat(profile?.wallet?.usdt_balance || '0');
    const ghdBalance = parseFloat(profile?.wallet?.ghd_balance || '0');
    // Assuming 1000 GHD = 1 USDT for tier calculation
    return usdtBalance + (ghdBalance / 1000);
  }, [profile]);

  const tierInfo = useMemo(() => calculateTier(totalEarnings), [totalEarnings]);
  const tierConfig = TIER_CONFIG[tierInfo.tier];
  const nextTierConfig = tierInfo.next ? TIER_CONFIG[tierInfo.next] : null;

  const verificationStatus = profile?.wallet_verified ? 'Verified' : 'Not Verified';

  const handleExportData = async () => {
    if (!onRequestDataExport) return;
    
    try {
      setExportLoading(true);
      hapticFeedback('light');
      await onRequestDataExport();
    } catch (err) {
      showError('Failed to request data export');
    } finally {
      setExportLoading(false);
    }
  };

  const handleRequestDeletion = async () => {
    if (!onRequestAccountDeletion) return;
    
    try {
      hapticFeedback('warning');
      await onRequestAccountDeletion();
      setDeletionRequested(true);
    } catch (err) {
      showError('Failed to submit deletion request');
    }
  };

  const handleNavigateToTransactions = () => {
    if (onNavigateToTransactions) {
      hapticFeedback('light');
      onNavigateToTransactions();
    }
  };

  return (
    <>
      {/* Account Tier Card */}
      <Card variant="glow">
        <CardHeader>
          <CardTitle>Account Tier</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.tierDisplay}>
            <div className={styles.tierBadge} style={{ borderColor: getTierColor(tierInfo.tier) }}>
              <span className={styles.tierIcon}>{tierConfig.icon}</span>
              <span className={styles.tierName} style={{ color: getTierColor(tierInfo.tier) }}>
                {tierConfig.label}
              </span>
            </div>
            
            {tierInfo.next && nextTierConfig && (
              <div className={styles.tierProgress}>
                <div className={styles.progressHeader}>
                  <span className={styles.progressLabel}>Progress to {nextTierConfig.label}</span>
                  <span className={styles.progressPercent}>{tierInfo.progress}%</span>
                </div>
                <div className={styles.progressBar}>
                  <div 
                    className={styles.progressFill} 
                    style={{ 
                      width: `${tierInfo.progress}%`,
                      background: `linear-gradient(90deg, ${getTierColor(tierInfo.tier)}, ${getTierColor(tierInfo.next)})`
                    }}
                  />
                </div>
                <div className={styles.progressHint}>
                  <span>${totalEarnings.toFixed(2)} earned</span>
                  <span>${nextTierConfig.minBalance} required</span>
                </div>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Account Balance */}
      <Card>
        <CardHeader>
          <CardTitle>Balance Overview</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.balanceGrid}>
            <div className={styles.balanceItem}>
              <span className={styles.balanceLabel}>USDT Balance</span>
              <span className={styles.balanceValue}>
                ${parseFloat(profile?.wallet?.usdt_balance || '0').toFixed(2)}
              </span>
            </div>
            <div className={styles.balanceItem}>
              <span className={styles.balanceLabel}>GHD Balance</span>
              <span className={styles.balanceValue}>
                {parseFloat(profile?.wallet?.ghd_balance || '0').toLocaleString(undefined, {
                  maximumFractionDigits: 0,
                })}
              </span>
            </div>
          </div>

          <div className={styles.verificationStatus}>
            <span className={styles.verificationLabel}>Wallet Verification</span>
            <span className={`${styles.verificationBadge} ${profile?.wallet_verified ? styles.verified : styles.unverified}`}>
              {profile?.wallet_verified && '✓ '}{verificationStatus}
            </span>
          </div>
        </CardContent>
      </Card>

      {/* Quick Actions */}
      <Card>
        <CardHeader>
          <CardTitle>Quick Actions</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.actionsList}>
            <button 
              className={styles.actionItem} 
              onClick={handleNavigateToTransactions}
              disabled={!onNavigateToTransactions}
            >
              <div className={styles.actionIcon}>
                <HistoryIcon size={20} color="var(--brand-primary)" />
              </div>
              <div className={styles.actionInfo}>
                <span className={styles.actionTitle}>Transaction History</span>
                <span className={styles.actionDescription}>View all your transactions</span>
              </div>
              <ChevronRightIcon size={20} color="var(--text-muted)" />
            </button>
          </div>
        </CardContent>
      </Card>

      {/* Data & Privacy */}
      <Card>
        <CardHeader>
          <CardTitle>Data & Privacy</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.privacySection}>
            <div className={styles.privacyItem}>
              <div className={styles.privacyInfo}>
                <h4 className={styles.privacyTitle}>Export Your Data</h4>
                <p className={styles.privacyDescription}>
                  Download a copy of all your account data in JSON format (GDPR compliant)
                </p>
              </div>
              <Button
                variant="outline"
                size="sm"
                loading={exportLoading}
                onClick={handleExportData}
                disabled={!onRequestDataExport}
              >
                Export
              </Button>
            </div>

            <div className={styles.divider} />

            <div className={styles.privacyItem}>
              <div className={styles.privacyInfo}>
                <h4 className={styles.privacyTitle}>Delete Account</h4>
                <p className={styles.privacyDescription}>
                  Permanently delete your account and all associated data
                </p>
              </div>
              <Button
                variant="danger"
                size="sm"
                onClick={handleRequestDeletion}
                disabled={!onRequestAccountDeletion || deletionRequested}
              >
                {deletionRequested ? 'Requested' : 'Delete'}
              </Button>
            </div>
          </div>

          {deletionRequested && (
            <div className={styles.deletionWarning}>
              <WarningIcon size={16} color="var(--warning)" />
              <span>Contact support through Help to complete deletion</span>
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}
