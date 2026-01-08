import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui';
import { TelegramBranding } from '../TelegramBranding';
import { TrustBadgeBar } from '../TrustBadgeBar';
import { ChevronRightIcon } from '../Icons';
import { hapticFeedback } from '../../lib/telegram';
import { 
  APP_VERSION, 
  DEFAULT_FAQS, 
  DEFAULT_SOCIAL_LINKS, 
  DEFAULT_LEGAL_LINKS,
  DEFAULT_CHANGELOG 
} from '../../types/settings';
import styles from './AboutSection.module.css';

// Expandable FAQ Item
interface FAQItemProps {
  question: string;
  answer: string;
}

function FAQItem({ question, answer }: FAQItemProps) {
  const [isOpen, setIsOpen] = useState(false);

  const toggle = () => {
    hapticFeedback('light');
    setIsOpen(!isOpen);
  };

  return (
    <div className={`${styles.faqItem} ${isOpen ? styles.faqOpen : ''}`}>
      <button
        className={styles.faqQuestion}
        onClick={toggle}
        aria-expanded={isOpen}
      >
        <span>{question}</span>
        <span className={styles.faqIcon}>
          <ChevronRightIcon size={18} color="var(--text-muted)" />
        </span>
      </button>
      <div className={styles.faqAnswer}>
        <p>{answer}</p>
      </div>
    </div>
  );
}

export function AboutSection() {
  return (
    <div className={styles.container}>
      {/* App Identity */}
      <Card variant="glow">
        <CardContent>
          <div className={styles.appIdentity}>
            <div className={styles.logoContainer}>
              <div className={styles.logo}>
                <span className={styles.logoIcon}>🌟</span>
              </div>
              <div className={styles.logoGlow} />
            </div>
            <h2 className={styles.appName}>Ghidar</h2>
            <p className={styles.appTagline}>Your Gateway to Crypto Opportunities</p>
            <div className={styles.versionBadge}>
              Version {APP_VERSION.version}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* About Description */}
      <Card>
        <CardHeader>
          <CardTitle>About Ghidar</CardTitle>
        </CardHeader>
        <CardContent>
          <p className={styles.description}>
            Ghidar is a secure Telegram Mini App that provides crypto opportunities including 
            airdrops, lottery, and AI trading. Built on Telegram's secure platform, Ghidar 
            offers a trusted environment for earning cryptocurrency rewards.
          </p>
          
          <div className={styles.telegramSection}>
            <TelegramBranding variant="badge" linkToDocs={true} />
          </div>
        </CardContent>
      </Card>

      {/* Security & Trust */}
      <Card>
        <CardHeader>
          <CardTitle>Security & Trust</CardTitle>
        </CardHeader>
        <CardContent>
          <TrustBadgeBar variant="compact" showLabels={true} />
          
          <div className={styles.securityFeatures}>
            <div className={styles.securityItem}>
              <span className={styles.securityIcon}>🔒</span>
              <span className={styles.securityText}>SSL encrypted connections</span>
            </div>
            <div className={styles.securityItem}>
              <span className={styles.securityIcon}>⚡</span>
              <span className={styles.securityText}>Telegram authentication</span>
            </div>
            <div className={styles.securityItem}>
              <span className={styles.securityIcon}>🛡️</span>
              <span className={styles.securityText}>No private keys stored</span>
            </div>
            <div className={styles.securityItem}>
              <span className={styles.securityIcon}>✅</span>
              <span className={styles.securityText}>Regular security audits</span>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* FAQ Section */}
      <Card>
        <CardHeader>
          <CardTitle>Frequently Asked Questions</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.faqList}>
            {DEFAULT_FAQS.map((faq) => (
              <FAQItem key={faq.id} question={faq.question} answer={faq.answer} />
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Social Links */}
      <Card>
        <CardHeader>
          <CardTitle>Connect With Us</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.socialLinks}>
            {DEFAULT_SOCIAL_LINKS.map((link) => (
              <a
                key={link.id}
                href={link.url}
                target="_blank"
                rel="noopener noreferrer"
                className={styles.socialLink}
                onClick={() => hapticFeedback('light')}
              >
                <span className={styles.socialIcon}>{link.icon}</span>
                <span className={styles.socialLabel}>{link.label}</span>
                <ChevronRightIcon size={16} color="var(--text-muted)" />
              </a>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* What's New */}
      <Card>
        <CardHeader>
          <CardTitle>What's New</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.changelog}>
            {DEFAULT_CHANGELOG.map((entry) => (
              <div key={entry.version} className={styles.changelogEntry}>
                <div className={styles.changelogHeader}>
                  <span className={styles.changelogVersion}>v{entry.version}</span>
                  <span className={styles.changelogDate}>{entry.date}</span>
                </div>
                <ul className={styles.changelogList}>
                  {entry.changes.map((change, index) => (
                    <li key={index}>{change}</li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Legal Links */}
      <Card>
        <CardHeader>
          <CardTitle>Legal</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.legalLinks}>
            {DEFAULT_LEGAL_LINKS.map((link) => (
              <a
                key={link.id}
                href={link.url}
                className={styles.legalLink}
                onClick={() => hapticFeedback('light')}
              >
                <span>{link.title}</span>
                <ChevronRightIcon size={16} color="var(--text-muted)" />
              </a>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Version Info Footer */}
      <div className={styles.footer}>
        <p className={styles.footerText}>
          Ghidar v{APP_VERSION.version} • {APP_VERSION.platform}
        </p>
        <p className={styles.footerCopyright}>
          © {new Date().getFullYear()} Ghidar. All rights reserved.
        </p>
      </div>
    </div>
  );
}
