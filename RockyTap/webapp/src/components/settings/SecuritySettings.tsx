import { useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui';
import { InfoIcon, CheckIcon } from '../Icons';
import type { SecuritySettingsProps, SecurityFeature } from '../../types/settings';
import { getSecurityScoreColor, getSecurityScoreLabel } from '../../types/settings';
import styles from './SecuritySettings.module.css';

// Security features configuration
const SECURITY_FEATURES: SecurityFeature[] = [
  {
    id: '2fa',
    title: 'Two-Factor Authentication',
    description: 'Enhanced security through Telegram\'s built-in 2FA',
    status: 'active',
    icon: '🔐',
  },
  {
    id: 'telegram_auth',
    title: 'Telegram Authentication',
    description: 'Secure login via Telegram\'s encrypted protocol',
    status: 'active',
    icon: '⚡',
  },
  {
    id: 'ssl',
    title: 'SSL Encryption',
    description: 'All data is transmitted over secure HTTPS connections',
    status: 'active',
    icon: '🔒',
  },
  {
    id: 'wallet_verification',
    title: 'Wallet Verification',
    description: 'Required for withdrawals and high-value transactions',
    status: 'pending',
    icon: '💳',
  },
];

function SecurityScoreMeter({ score }: { score: number }) {
  const circumference = 2 * Math.PI * 45;
  const strokeDashoffset = circumference - (score / 100) * circumference;
  const color = getSecurityScoreColor(score);
  const label = getSecurityScoreLabel(score);

  return (
    <div className={styles.scoreMeter}>
      <svg viewBox="0 0 100 100" className={styles.scoreRing}>
        {/* Background circle */}
        <circle
          cx="50"
          cy="50"
          r="45"
          fill="none"
          stroke="var(--bg-elevated)"
          strokeWidth="8"
        />
        {/* Progress circle */}
        <circle
          cx="50"
          cy="50"
          r="45"
          fill="none"
          stroke={color}
          strokeWidth="8"
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={strokeDashoffset}
          transform="rotate(-90 50 50)"
          className={styles.scoreProgress}
        />
      </svg>
      <div className={styles.scoreContent}>
        <span className={styles.scoreValue} style={{ color }}>{score}</span>
        <span className={styles.scoreLabel}>{label}</span>
      </div>
    </div>
  );
}

export function SecuritySettings({ profile }: SecuritySettingsProps) {
  // Calculate security score based on various factors
  const securityScore = useMemo(() => {
    let score = 30; // Base score for using the app
    
    // Telegram authentication (always active)
    score += 20;
    
    // 2FA is managed by Telegram
    score += 20;
    
    // Wallet verification
    if (profile?.wallet_verified) {
      score += 20;
    }
    
    // Has wallet balance (indicates trust)
    if (profile?.wallet?.usdt_balance && parseFloat(profile.wallet.usdt_balance) > 0) {
      score += 10;
    }
    
    return Math.min(score, 100);
  }, [profile]);

  // Update wallet verification status in features
  const features = useMemo(() => {
    return SECURITY_FEATURES.map(feature => {
      if (feature.id === 'wallet_verification') {
        return {
          ...feature,
          status: profile?.wallet_verified ? 'active' : 'pending' as const,
        };
      }
      return feature;
    });
  }, [profile]);

  const recommendations = useMemo(() => {
    const recs: string[] = [];
    
    if (!profile?.wallet_verified) {
      recs.push('Complete wallet verification for enhanced security');
    }
    
    if (!profile?.wallet?.usdt_balance || parseFloat(profile.wallet.usdt_balance) === 0) {
      recs.push('Make your first deposit to unlock all features');
    }
    
    if (recs.length === 0) {
      recs.push('Your account is well secured!');
    }
    
    return recs;
  }, [profile]);

  return (
    <>
      {/* Security Score */}
      <Card variant="glow">
        <CardHeader>
          <CardTitle>Security Score</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.scoreContainer}>
            <SecurityScoreMeter score={securityScore} />
            <div className={styles.scoreInfo}>
              <p className={styles.scoreDescription}>
                Your security score is calculated based on your account settings and verification status.
              </p>
              <div className={styles.recommendations}>
                <h4 className={styles.recommendationsTitle}>Recommendations</h4>
                <ul className={styles.recommendationsList}>
                  {recommendations.map((rec, index) => (
                    <li key={index} className={styles.recommendationItem}>
                      {securityScore >= 80 ? '✓' : '→'} {rec}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Security Features */}
      <Card>
        <CardHeader>
          <CardTitle>Security Features</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.featuresList}>
            {features.map((feature) => (
              <div key={feature.id} className={styles.featureItem}>
                <div className={styles.featureIcon}>
                  {feature.icon}
                </div>
                <div className={styles.featureInfo}>
                  <h4 className={styles.featureTitle}>{feature.title}</h4>
                  <p className={styles.featureDescription}>{feature.description}</p>
                </div>
                <div className={`${styles.featureStatus} ${styles[feature.status]}`}>
                  {feature.status === 'active' && <CheckIcon size={14} color="var(--success)" />}
                  <span>{feature.status === 'active' ? 'Active' : 'Pending'}</span>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Session Info */}
      <Card>
        <CardHeader>
          <CardTitle>Current Session</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.sessionCard}>
            <div className={styles.sessionIcon}>
              📱
            </div>
            <div className={styles.sessionInfo}>
              <div className={styles.sessionDevice}>
                <span className={styles.sessionDeviceName}>This Device</span>
                <span className={styles.sessionBadge}>Current</span>
              </div>
              <p className={styles.sessionDetails}>
                Telegram Mini App • Active now
              </p>
            </div>
          </div>
          
          <div className={styles.sessionNote}>
            <InfoIcon size={16} color="var(--text-muted)" />
            <p>
              Sessions are automatically managed through Telegram. To end this session, 
              close the Mini App from Telegram.
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Security Tips */}
      <Card>
        <CardHeader>
          <CardTitle>Security Tips</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.tipsList}>
            <div className={styles.tipItem}>
              <span className={styles.tipIcon}>🔐</span>
              <div className={styles.tipContent}>
                <h4 className={styles.tipTitle}>Enable Telegram 2FA</h4>
                <p className={styles.tipDescription}>
                  Set up two-step verification in Telegram settings for extra protection.
                </p>
              </div>
            </div>
            
            <div className={styles.tipItem}>
              <span className={styles.tipIcon}>🔑</span>
              <div className={styles.tipContent}>
                <h4 className={styles.tipTitle}>Protect Your Wallet</h4>
                <p className={styles.tipDescription}>
                  Never share your wallet private keys or recovery phrases with anyone.
                </p>
              </div>
            </div>
            
            <div className={styles.tipItem}>
              <span className={styles.tipIcon}>⚠️</span>
              <div className={styles.tipContent}>
                <h4 className={styles.tipTitle}>Beware of Scams</h4>
                <p className={styles.tipDescription}>
                  Ghidar will never ask for your password or private keys via messages.
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </>
  );
}
