import React, { useState } from 'react';
import { TelegramBranding } from './TelegramBranding';
import { TrustBadgeBar } from './TrustBadgeBar';
import { TermsOfServiceModal, PrivacyPolicyModal } from './legal';
import styles from './Footer.module.css';

interface FooterProps {
  className?: string;
  onNavigateToHelp?: () => void;
}

export function Footer({ className = '', onNavigateToHelp }: FooterProps) {
  const currentYear = new Date().getFullYear();
  const [showTerms, setShowTerms] = useState(false);
  const [showPrivacy, setShowPrivacy] = useState(false);

  const handleSupportClick = () => {
    if (onNavigateToHelp) {
      onNavigateToHelp();
    } else {
      // Fallback: Open Telegram support
      window.open('https://t.me/ghidar_support', '_blank');
    }
  };

  return (
    <>
      <footer className={`${styles.footer} ${className}`} role="contentinfo">
        <div className={styles.content}>
          {/* Trust Badges */}
          <div className={styles.trustSection}>
            <TrustBadgeBar variant="compact" showLabels={false} />
          </div>

          {/* Telegram Branding */}
          <div className={styles.brandingSection}>
            <TelegramBranding variant="text" linkToDocs={true} />
          </div>

          {/* Links */}
          <nav className={styles.linksSection} aria-label="Legal links">
            <button 
              className={styles.link}
              onClick={() => setShowTerms(true)}
              type="button"
            >
              Terms of Service
            </button>
            <span className={styles.separator} aria-hidden="true">•</span>
            <button 
              className={styles.link}
              onClick={() => setShowPrivacy(true)}
              type="button"
            >
              Privacy Policy
            </button>
            <span className={styles.separator} aria-hidden="true">•</span>
            <button 
              className={styles.link}
              onClick={handleSupportClick}
              type="button"
            >
              Support
            </button>
          </nav>

          {/* Copyright */}
          <div className={styles.copyright}>
            <p>© {currentYear} Ghidar. All rights reserved.</p>
          </div>
        </div>
      </footer>

      {/* Legal Modals */}
      <TermsOfServiceModal 
        isOpen={showTerms} 
        onClose={() => setShowTerms(false)} 
      />
      <PrivacyPolicyModal 
        isOpen={showPrivacy} 
        onClose={() => setShowPrivacy(false)} 
      />
    </>
  );
}
