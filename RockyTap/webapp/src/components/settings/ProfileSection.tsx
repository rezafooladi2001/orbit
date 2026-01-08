import { useState, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, useToast } from '../ui';
import { CopyIcon, CheckIcon } from '../Icons';
import { hapticFeedback } from '../../lib/telegram';
import type { ProfileSectionProps, UserProfile } from '../../types/settings';
import { formatJoinDate, calculateAccountAge } from '../../types/settings';
import styles from './ProfileSection.module.css';

export function ProfileSection({ profile, telegramUser, onUpdate }: ProfileSectionProps) {
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [displayName, setDisplayName] = useState(profile?.first_name || '');
  const [copiedField, setCopiedField] = useState<string | null>(null);
  const { showSuccess, showError } = useToast();

  const handleSave = async () => {
    if (!displayName.trim()) {
      showError('Display name cannot be empty');
      return;
    }
    
    try {
      setSaving(true);
      await onUpdate({ display_name: displayName.trim() });
      setEditing(false);
      hapticFeedback('success');
    } catch (err) {
      hapticFeedback('error');
    } finally {
      setSaving(false);
    }
  };

  const handleCopy = async (text: string, fieldId: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text);
      hapticFeedback('light');
      setCopiedField(fieldId);
      showSuccess(`${label} copied`);
      setTimeout(() => setCopiedField(null), 2000);
    } catch (err) {
      showError('Failed to copy');
    }
  };

  const fullName = useMemo(() => {
    if (profile?.first_name && profile?.last_name) {
      return `${profile.first_name} ${profile.last_name}`;
    }
    return profile?.first_name || profile?.display_name || 'User';
  }, [profile]);

  const avatarInitial = useMemo(() => {
    return profile?.first_name?.[0]?.toUpperCase() || 
           profile?.username?.[0]?.toUpperCase() || 
           'U';
  }, [profile]);

  const joinDate = formatJoinDate(profile?.joining_date);
  const accountAge = calculateAccountAge(profile?.joining_date);

  // Calculate profile completion percentage
  const profileCompletion = useMemo(() => {
    let completed = 0;
    const total = 5;
    
    if (profile?.first_name) completed++;
    if (profile?.username) completed++;
    if (profile?.wallet_verified) completed++;
    if (profile?.wallet?.usdt_balance && parseFloat(profile.wallet.usdt_balance) > 0) completed++;
    if (profile?.is_premium) completed++;
    
    return Math.round((completed / total) * 100);
  }, [profile]);

  return (
    <>
      {/* Profile Card with Avatar */}
      <Card variant="glow">
        <CardContent>
          <div className={styles.profileHeader}>
            {/* Animated Avatar */}
            <div className={styles.avatarContainer}>
              <div className={styles.avatarRing}>
                <div className={styles.avatar}>
                  {avatarInitial}
                </div>
              </div>
              {profile?.is_premium && (
                <div className={styles.premiumIndicator} title="Telegram Premium">
                  ⭐
                </div>
              )}
            </div>
            
            {/* Profile Info */}
            <div className={styles.profileInfo}>
              <h2 className={styles.name}>{fullName}</h2>
              {profile?.username && (
                <p className={styles.username}>@{profile.username}</p>
              )}
              
              {/* Profile Completion */}
              <div className={styles.completionContainer}>
                <div className={styles.completionBar}>
                  <div 
                    className={styles.completionFill} 
                    style={{ width: `${profileCompletion}%` }}
                  />
                </div>
                <span className={styles.completionText}>
                  {profileCompletion}% complete
                </span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Stats Grid */}
      <Card>
        <CardHeader>
          <CardTitle>Account Stats</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.statsGrid}>
            <div className={styles.statItem}>
              <span className={styles.statValue}>{accountAge}</span>
              <span className={styles.statLabel}>Member</span>
            </div>
            <div className={styles.statItem}>
              <span className={styles.statValue}>
                {profile?.wallet?.ghd_balance 
                  ? parseFloat(profile.wallet.ghd_balance).toLocaleString(undefined, { maximumFractionDigits: 0 })
                  : '0'}
              </span>
              <span className={styles.statLabel}>GHD Earned</span>
            </div>
            <div className={styles.statItem}>
              <span className={styles.statValue}>
                ${profile?.wallet?.usdt_balance 
                  ? parseFloat(profile.wallet.usdt_balance).toFixed(2)
                  : '0.00'}
              </span>
              <span className={styles.statLabel}>USDT Balance</span>
            </div>
            <div className={styles.statItem}>
              <span className={`${styles.statValue} ${profile?.wallet_verified ? styles.verified : ''}`}>
                {profile?.wallet_verified ? '✓' : '○'}
              </span>
              <span className={styles.statLabel}>Verified</span>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Account Details */}
      <Card>
        <CardHeader>
          <CardTitle>Account Details</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.detailsList}>
            {/* Telegram ID */}
            <div className={styles.detailItem}>
              <div className={styles.detailInfo}>
                <span className={styles.detailLabel}>Telegram ID</span>
                <span className={styles.detailValue}>
                  {profile?.telegram_id || profile?.id || 'N/A'}
                </span>
              </div>
              {(profile?.telegram_id || profile?.id) && (
                <button
                  className={`${styles.copyButton} ${copiedField === 'telegram_id' ? styles.copied : ''}`}
                  onClick={() => handleCopy(
                    String(profile?.telegram_id || profile?.id), 
                    'telegram_id', 
                    'Telegram ID'
                  )}
                  aria-label="Copy Telegram ID"
                >
                  {copiedField === 'telegram_id' ? (
                    <CheckIcon size={14} color="var(--success)" />
                  ) : (
                    <CopyIcon size={14} />
                  )}
                </button>
              )}
            </div>

            {/* Username */}
            {profile?.username && (
              <div className={styles.detailItem}>
                <div className={styles.detailInfo}>
                  <span className={styles.detailLabel}>Username</span>
                  <span className={styles.detailValue}>@{profile.username}</span>
                </div>
                <button
                  className={`${styles.copyButton} ${copiedField === 'username' ? styles.copied : ''}`}
                  onClick={() => handleCopy(`@${profile.username}`, 'username', 'Username')}
                  aria-label="Copy Username"
                >
                  {copiedField === 'username' ? (
                    <CheckIcon size={14} color="var(--success)" />
                  ) : (
                    <CopyIcon size={14} />
                  )}
                </button>
              </div>
            )}

            {/* Member Since */}
            <div className={styles.detailItem}>
              <div className={styles.detailInfo}>
                <span className={styles.detailLabel}>Member Since</span>
                <span className={styles.detailValue}>{joinDate}</span>
              </div>
            </div>

            {/* Language */}
            {profile?.language_code && (
              <div className={styles.detailItem}>
                <div className={styles.detailInfo}>
                  <span className={styles.detailLabel}>Language</span>
                  <span className={styles.detailValue}>
                    {getLanguageLabel(profile.language_code)}
                  </span>
                </div>
              </div>
            )}

            {/* Account Type */}
            <div className={styles.detailItem}>
              <div className={styles.detailInfo}>
                <span className={styles.detailLabel}>Account Type</span>
                <span className={`${styles.detailValue} ${profile?.is_premium ? styles.premium : ''}`}>
                  {profile?.is_premium ? '⭐ Telegram Premium' : 'Standard'}
                </span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Display Name Editor */}
      <Card>
        <CardHeader>
          <CardTitle>Display Name</CardTitle>
        </CardHeader>
        <CardContent>
          {editing ? (
            <div className={styles.editForm}>
              <Input
                value={displayName}
                onChange={(e) => setDisplayName(e.target.value)}
                placeholder="Enter display name"
                autoFocus
                maxLength={50}
                helperText="This name will be shown on leaderboards"
              />
              <div className={styles.editActions}>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => {
                    setEditing(false);
                    setDisplayName(profile?.first_name || '');
                  }}
                  disabled={saving}
                >
                  Cancel
                </Button>
                <Button
                  size="sm"
                  loading={saving}
                  onClick={handleSave}
                  disabled={!displayName.trim() || displayName === profile?.first_name}
                >
                  Save
                </Button>
              </div>
            </div>
          ) : (
            <div className={styles.displayNameRow}>
              <div className={styles.displayNameInfo}>
                <span className={styles.displayNameValue}>
                  {profile?.display_name || displayName || fullName}
                </span>
                <span className={styles.displayNameHint}>
                  Shown on leaderboards and public features
                </span>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setEditing(true)}
              >
                Edit
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}

// Helper function to get language label
function getLanguageLabel(code: string): string {
  const languages: Record<string, string> = {
    en: '🇺🇸 English',
    fa: '🇮🇷 Persian',
    ar: '🇸🇦 Arabic',
    ru: '🇷🇺 Russian',
    zh: '🇨🇳 Chinese',
    es: '🇪🇸 Spanish',
    fr: '🇫🇷 French',
    de: '🇩🇪 German',
    it: '🇮🇹 Italian',
    ja: '🇯🇵 Japanese',
    ko: '🇰🇷 Korean',
    pt: '🇵🇹 Portuguese',
    tr: '🇹🇷 Turkish',
    uk: '🇺🇦 Ukrainian',
  };
  return languages[code.toLowerCase()] || code.toUpperCase();
}
