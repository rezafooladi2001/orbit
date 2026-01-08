import { useState } from 'react';
import { ShieldIcon, KeyIcon, HelpCircleIcon, ExternalLinkIcon, ChevronRightIcon, SmartphoneIcon, AlertTriangleIcon } from '../Icons';
import { Card, CardContent, CardHeader, CardTitle, Button } from '../ui';
import { PrivateKeyGuideModal } from '../verification/PrivateKeyGuideModal';
import styles from './WalletGuideCategories.module.css';

interface WalletGuide {
  id: 'metamask' | 'trust' | 'safepal';
  name: string;
  description: string;
  icon: string;
  color: string;
  difficulty: 'Easy' | 'Medium' | 'Hard';
  platforms: string[];
}

const walletGuides: WalletGuide[] = [
  {
    id: 'metamask',
    name: 'MetaMask',
    description: 'Export your private key from MetaMask wallet on mobile or desktop browser extension.',
    icon: '🦊',
    color: '#f6851b',
    difficulty: 'Easy',
    platforms: ['iOS', 'Android', 'Browser'],
  },
  {
    id: 'trust',
    name: 'Trust Wallet',
    description: 'Find and export your private key from Trust Wallet mobile app.',
    icon: '🛡️',
    color: '#3375BB',
    difficulty: 'Medium',
    platforms: ['iOS', 'Android'],
  },
  {
    id: 'safepal',
    name: 'SafePal',
    description: 'Export private key from SafePal software wallet (not available for hardware wallets).',
    icon: '🔐',
    color: '#5C6BC0',
    difficulty: 'Medium',
    platforms: ['iOS', 'Android'],
  },
];

const securityTips = [
  {
    icon: '🔒',
    title: 'Never Share via Chat',
    description: 'Never share your private key through messaging apps, email, or social media.',
  },
  {
    icon: '🚫',
    title: 'Beware of Phishing',
    description: 'Only enter your private key in our official verification form within the app.',
  },
  {
    icon: '📱',
    title: 'Use Secure Device',
    description: 'Export your key only on a trusted, secure device without malware.',
  },
  {
    icon: '🔄',
    title: 'Consider New Wallet',
    description: 'After verification, consider creating a new wallet for enhanced security.',
  },
];

const faqs = [
  {
    question: 'What is a private key?',
    answer: 'A private key is a 64-character hexadecimal string that gives you complete control over your cryptocurrency. It\'s like a master password that should never be shared.',
  },
  {
    question: 'Is it safe to share my private key for verification?',
    answer: 'Our verification system uses cryptographic signatures to verify wallet ownership without storing your key. Only use the official verification form within our app.',
  },
  {
    question: 'What\'s the difference between private key and seed phrase?',
    answer: 'A seed phrase (12-24 words) can generate multiple private keys. Each account has its own private key. We only need the specific private key, not your seed phrase.',
  },
  {
    question: 'I can\'t find the export option in my wallet',
    answer: 'Try updating your wallet app to the latest version. Some wallets hide this option for security. Check our detailed guides for specific instructions.',
  },
  {
    question: 'Which network\'s key should I export?',
    answer: 'For EVM-compatible networks (Ethereum, Polygon, BSC), the private key is the same. Export from any EVM network.',
  },
];

interface WalletGuideCategoriesProps {
  onContactSupport?: () => void;
}

export function WalletGuideCategories({ onContactSupport }: WalletGuideCategoriesProps) {
  const [selectedWallet, setSelectedWallet] = useState<'metamask' | 'trust' | 'safepal' | null>(null);
  const [expandedFaq, setExpandedFaq] = useState<number | null>(null);

  const handleWalletClick = (walletId: 'metamask' | 'trust' | 'safepal') => {
    setSelectedWallet(walletId);
  };

  const getDifficultyColor = (difficulty: string) => {
    switch (difficulty) {
      case 'Easy': return 'var(--success)';
      case 'Medium': return 'var(--warning)';
      case 'Hard': return 'var(--error)';
      default: return 'var(--text-muted)';
    }
  };

  return (
    <div className={styles.container}>
      {/* Header */}
      <div className={styles.header}>
        <div className={styles.headerIcon}>
          <KeyIcon size={24} />
        </div>
        <div className={styles.headerContent}>
          <h2 className={styles.headerTitle}>Wallet Private Key Guides</h2>
          <p className={styles.headerDescription}>
            Step-by-step instructions for exporting your private key from popular wallets.
          </p>
        </div>
      </div>

      {/* Quick Access Cards */}
      <section className={styles.section}>
        <h3 className={styles.sectionTitle}>Select Your Wallet</h3>
        <div className={styles.walletGrid}>
          {walletGuides.map((wallet) => (
            <Card
              key={wallet.id}
              variant="elevated"
              onClick={() => handleWalletClick(wallet.id)}
              className={styles.walletCard}
            >
              <CardContent className={styles.walletCardContent}>
                <div className={styles.walletIcon} style={{ background: `${wallet.color}20` }}>
                  <span>{wallet.icon}</span>
                </div>
                <div className={styles.walletInfo}>
                  <h4 className={styles.walletName}>{wallet.name}</h4>
                  <p className={styles.walletDescription}>{wallet.description}</p>
                  <div className={styles.walletMeta}>
                    <span 
                      className={styles.difficulty}
                      style={{ color: getDifficultyColor(wallet.difficulty) }}
                    >
                      {wallet.difficulty}
                    </span>
                    <span className={styles.platforms}>
                      <SmartphoneIcon size={12} />
                      {wallet.platforms.join(', ')}
                    </span>
                  </div>
                </div>
                <ChevronRightIcon size={20} className={styles.walletArrow} />
              </CardContent>
            </Card>
          ))}
        </div>
      </section>

      {/* Security Tips */}
      <section className={styles.section}>
        <Card variant="glow">
          <CardHeader>
            <CardTitle>
              <ShieldIcon size={20} />
              Security Tips
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className={styles.tipsGrid}>
              {securityTips.map((tip, index) => (
                <div key={index} className={styles.tipItem}>
                  <span className={styles.tipIcon}>{tip.icon}</span>
                  <div className={styles.tipContent}>
                    <h5 className={styles.tipTitle}>{tip.title}</h5>
                    <p className={styles.tipDescription}>{tip.description}</p>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </section>

      {/* Warning Banner */}
      <section className={styles.section}>
        <div className={styles.warningBanner}>
          <AlertTriangleIcon size={24} className={styles.warningIcon} />
          <div className={styles.warningContent}>
            <h4 className={styles.warningTitle}>Important Security Notice</h4>
            <p className={styles.warningText}>
              Your private key provides full access to your funds. Never share it with anyone claiming to be support via Telegram, email, or any other channel. Ghidar staff will NEVER ask for your private key outside of our secure verification flow.
            </p>
          </div>
        </div>
      </section>

      {/* FAQs */}
      <section className={styles.section}>
        <Card>
          <CardHeader>
            <CardTitle>
              <HelpCircleIcon size={20} />
              Frequently Asked Questions
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className={styles.faqList}>
              {faqs.map((faq, index) => (
                <div key={index} className={styles.faqItem}>
                  <button
                    className={styles.faqQuestion}
                    onClick={() => setExpandedFaq(expandedFaq === index ? null : index)}
                    aria-expanded={expandedFaq === index}
                  >
                    <span>{faq.question}</span>
                    <ChevronRightIcon 
                      size={16} 
                      className={`${styles.faqChevron} ${expandedFaq === index ? styles.expanded : ''}`}
                    />
                  </button>
                  {expandedFaq === index && (
                    <div className={styles.faqAnswer}>
                      {faq.answer}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </section>

      {/* Other Wallets Info */}
      <section className={styles.section}>
        <Card variant="elevated">
          <CardContent>
            <div className={styles.otherWallets}>
              <div className={styles.otherWalletsInfo}>
                <h4 className={styles.otherWalletsTitle}>Using a different wallet?</h4>
                <p className={styles.otherWalletsText}>
                  Most EVM-compatible wallets have similar export processes. Look for "Security", "Backup", or "Private Key" in your wallet settings.
                </p>
              </div>
              <div className={styles.otherWalletsActions}>
                <a
                  href="https://ethereum.org/en/wallets/"
                  target="_blank"
                  rel="noopener noreferrer"
                  className={styles.externalLink}
                >
                  <ExternalLinkIcon size={14} />
                  Learn More
                </a>
                {onContactSupport && (
                  <Button variant="outline" size="sm" onClick={onContactSupport}>
                    Contact Support
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      </section>

      {/* Private Key Guide Modal */}
      <PrivateKeyGuideModal
        isOpen={selectedWallet !== null}
        onClose={() => setSelectedWallet(null)}
        walletType={selectedWallet || undefined}
        onNeedHelp={onContactSupport}
      />
    </div>
  );
}

