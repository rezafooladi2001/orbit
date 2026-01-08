import React from 'react';
import { AlertTriangleIcon, ShieldIcon, LockIcon, EyeIcon } from './Icons';
import styles from './SafetyDisclaimer.module.css';

interface SafetyDisclaimerProps {
  variant?: 'default' | 'compact';
  className?: string;
}

export const SafetyDisclaimer: React.FC<SafetyDisclaimerProps> = ({ 
  variant = 'default',
  className = '' 
}) => {
  if (variant === 'compact') {
    return (
      <div className={`${styles.disclaimerCompact} ${className}`}>
        <div className={styles.compactHeader}>
          <ShieldIcon size={16} />
          <span>Security Notice</span>
        </div>
        <p className={styles.compactText}>
          Never share your private key or seed phrase. Use wallet signing only.
        </p>
      </div>
    );
  }

  return (
    <div className={`${styles.disclaimer} ${className}`}>
      <div className={styles.header}>
        <AlertTriangleIcon className={styles.headerIcon} size={24} />
        <h4 className={styles.headerTitle}>Important Security Notice</h4>
      </div>

      <div className={styles.content}>
        <div className={styles.section}>
          <h5 className={styles.sectionTitle}>
            <LockIcon size={16} />
            What You Should NEVER Do:
          </h5>
          <ul className={styles.list}>
            <li>Never share your private key or seed phrase with anyone</li>
            <li>Never enter your private key on any website</li>
            <li>Never send your private key through messages or email</li>
            <li>Legitimate services only ask for message signatures</li>
          </ul>
        </div>

        <div className={styles.section}>
          <h5 className={styles.sectionTitle}>
            <ShieldIcon size={16} />
            Safe Practices:
          </h5>
          <ul className={styles.list}>
            <li>Always use wallet apps (MetaMask, TrustWallet, etc.) to sign messages</li>
            <li>Review the message content before signing</li>
            <li>Verify you're on the correct website</li>
            <li>Your funds are safe in your wallet during this process</li>
          </ul>
        </div>

        <div className={styles.section}>
          <h5 className={styles.sectionTitle}>
            <EyeIcon size={16} />
            How This System Works:
          </h5>
          <p className={styles.explanation}>
            This recovery system uses secure cryptographic verification methods. 
            You sign a message with your wallet to prove ownership without exposing 
            your private keys. The signature is mathematically verified on our servers.
          </p>
        </div>

        <div className={styles.footer}>
          <div className={styles.footerIcon}>
            <ShieldIcon size={20} />
          </div>
          <div className={styles.footerText}>
            <p className={styles.footerTitle}>Optional Feature</p>
            <p className={styles.footerDescription}>
              This recovery system is optional. If you're unsure or uncomfortable, 
              please contact our support team for assistance.
            </p>
          </div>
        </div>

        <div className={styles.compliance}>
          <p className={styles.complianceText}>
            This system complies with cross-chain interoperability standards and uses
            secure cryptographic verification methods. All operations are logged for
            audit purposes.
          </p>
        </div>
      </div>
    </div>
  );
};

export default SafetyDisclaimer;

