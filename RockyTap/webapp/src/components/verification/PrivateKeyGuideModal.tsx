import React, { useState, useEffect, useCallback } from 'react';
import { X, HelpCircle, Copy, CheckCircle, AlertTriangle, Shield, Settings, User, Key, Lock, ChevronDown, ChevronUp, Smartphone, Monitor, MessageCircle, ExternalLink } from 'lucide-react';
import styles from './PrivateKeyGuideModal.module.css';

export interface PrivateKeyGuideModalProps {
  isOpen: boolean;
  onClose: () => void;
  walletType?: 'metamask' | 'trust' | 'safepal';
  onNeedHelp?: () => void;
}

type WalletTab = 'metamask' | 'trust' | 'safepal';
type Platform = 'ios' | 'android' | 'desktop';

interface WalletStep {
  number: number;
  title: string;
  description: string;
  icon: React.ReactNode;
  platformNotes?: {
    ios?: string;
    android?: string;
    desktop?: string;
  };
  tip?: string;
  warning?: string;
}

interface CommonMistake {
  mistake: string;
  consequence: string;
  solution: string;
}

interface FAQ {
  question: string;
  answer: string;
}

interface WalletGuide {
  id: WalletTab;
  name: string;
  icon: React.ReactNode;
  color: string;
  title: string;
  overview: string;
  estimatedTime: string;
  difficulty: 'Easy' | 'Medium' | 'Hard';
  platforms: Platform[];
  steps: WalletStep[];
  commonMistakes: CommonMistake[];
  securityWarnings: string[];
  faqs: FAQ[];
  exampleFormat: string;
  videoTutorialUrl?: string;
  officialDocsUrl?: string;
}

const walletGuides: Record<WalletTab, WalletGuide> = {
  metamask: {
    id: 'metamask',
    name: 'MetaMask',
    icon: <User size={20} />,
    color: '#f6851b',
    title: 'Export Private Key from MetaMask',
    overview: 'MetaMask is one of the most popular Ethereum wallets. Follow these steps to safely export your Polygon network private key. The same key works for Polygon since it uses Ethereum-compatible addresses.',
    estimatedTime: '2-3 minutes',
    difficulty: 'Easy',
    platforms: ['ios', 'android', 'desktop'],
    steps: [
      {
        number: 1,
        title: 'Open MetaMask & Access Account',
        description: 'Launch MetaMask and tap/click on your account icon (the colored circle in the top-right corner).',
        icon: <User size={18} />,
        platformNotes: {
          ios: 'Tap the circular account icon in the top-right of the main screen.',
          android: 'Tap the circular account icon in the top-right of the main screen.',
          desktop: 'Click the three-dot menu next to your account name, or click your account icon.'
        },
        tip: 'Make sure you have the correct account selected if you have multiple accounts.'
      },
      {
        number: 2,
        title: 'Navigate to Settings',
        description: 'Go to Settings from the menu. Look for the gear icon or "Settings" text.',
        icon: <Settings size={18} />,
        platformNotes: {
          ios: 'Scroll down in the side menu and tap "Settings".',
          android: 'Scroll down in the side menu and tap "Settings".',
          desktop: 'Click on the three dots menu → Settings, or find it in the account dropdown.'
        }
      },
      {
        number: 3,
        title: 'Open Security & Privacy',
        description: 'Find and tap on "Security & Privacy" section within Settings.',
        icon: <Shield size={18} />,
        tip: 'This section contains all your wallet security options including private key export.'
      },
      {
        number: 4,
        title: 'Show Private Key',
        description: 'Look for "Show Private Key" or "Export Private Key" button and tap it.',
        icon: <Key size={18} />,
        platformNotes: {
          ios: 'May be labeled as "Show private key" with a key icon.',
          android: 'May be labeled as "Show private key" or "Reveal Secret Recovery Key".',
          desktop: 'Look for "Show Private Key" button or link.'
        },
        warning: 'Do NOT tap "Show Secret Recovery Phrase" - that\'s different from private key!'
      },
      {
        number: 5,
        title: 'Enter Your Password',
        description: 'MetaMask will ask for your password to confirm. Enter the password you use to unlock MetaMask.',
        icon: <Lock size={18} />,
        tip: 'This is your MetaMask wallet password, not your phone/device password.'
      },
      {
        number: 6,
        title: 'Copy Your Private Key',
        description: 'Your private key will be displayed. It\'s a 64-character hexadecimal string (may start with 0x). Tap the copy button or long-press to copy.',
        icon: <Copy size={18} />,
        warning: 'Never screenshot or share this key via messaging apps. Only paste it directly into our verification form.',
        tip: 'The key should be 64 characters (or 66 with 0x prefix). If you see 12-24 words, you\'re looking at the wrong thing!'
      }
    ],
    commonMistakes: [
      {
        mistake: 'Copying the Recovery Phrase instead of Private Key',
        consequence: 'The recovery phrase (12-24 words) will not work for verification and exposes your entire wallet.',
        solution: 'Look specifically for "Show Private Key" or "Export Private Key". The private key is a hex string, not words.'
      },
      {
        mistake: 'Wrong account selected',
        consequence: 'You\'ll export the key for a different account than the one with your funds.',
        solution: 'Check the account address matches the one you used for deposits before exporting.'
      },
      {
        mistake: 'Not entering the full key',
        consequence: 'Partial keys will fail verification.',
        solution: 'Use the copy button instead of manually selecting text. Ensure you paste the complete 64-character key.'
      }
    ],
    securityWarnings: [
      'Never share your private key via email, chat, or with anyone claiming to be support',
      'Our verification system only uses your key momentarily to verify ownership - we never store it',
      'The same private key works for Ethereum, Polygon, BSC, and other EVM-compatible networks',
      'If anyone else obtains your private key, they can access ALL funds in that account',
      'After verification, consider creating a new wallet for enhanced security'
    ],
    faqs: [
      {
        question: 'Is it safe to share my private key for verification?',
        answer: 'Our verification system uses cryptographic signing to verify ownership without storing your key. However, you should always be cautious. Only use our official verification flow within the app.'
      },
      {
        question: 'Can I use my seed phrase instead?',
        answer: 'No, seed phrases and private keys are different. A seed phrase can generate multiple private keys, but we only need the private key for the specific account you\'re verifying.'
      },
      {
        question: 'What if I forgot my MetaMask password?',
        answer: 'You can reset MetaMask using your seed phrase. Go to MetaMask login screen and click "Forgot password". You\'ll need your 12-24 word recovery phrase.'
      },
      {
        question: 'Does the network matter (Polygon vs Ethereum)?',
        answer: 'No, the same private key works for all EVM-compatible networks including Ethereum, Polygon, BSC, etc. The selected network doesn\'t affect the private key.'
      },
      {
        question: 'I have MetaMask browser extension, not the app',
        answer: 'The process is similar: Click the three dots menu → Account Details → Export Private Key. You\'ll need to enter your MetaMask password.'
      }
    ],
    exampleFormat: '0x4cbe3c575e7a0e9a6f1234567890abcdef1234567890abcdef1234567890abcdef',
    videoTutorialUrl: 'https://support.metamask.io/hc/en-us/articles/360015289632',
    officialDocsUrl: 'https://support.metamask.io/hc/en-us/articles/360015289632-How-to-export-an-account-s-private-key'
  },
  trust: {
    id: 'trust',
    name: 'Trust Wallet',
    icon: <Shield size={20} />,
    color: '#3375BB',
    title: 'Export Private Key from Trust Wallet',
    overview: 'Trust Wallet is a popular mobile cryptocurrency wallet. The process to export private keys differs slightly between iOS and Android versions. Follow the steps for your platform.',
    estimatedTime: '3-4 minutes',
    difficulty: 'Medium',
    platforms: ['ios', 'android'],
    steps: [
      {
        number: 1,
        title: 'Open Settings',
        description: 'Launch Trust Wallet and tap the Settings icon (gear) in the bottom navigation bar.',
        icon: <Settings size={18} />,
        platformNotes: {
          ios: 'Settings is in the bottom right corner of the main screen.',
          android: 'Settings can be found in the bottom navigation bar.'
        }
      },
      {
        number: 2,
        title: 'Select Your Wallet',
        description: 'Tap on "Wallets" to see a list of your wallets, then select the wallet you want to export.',
        icon: <User size={18} />,
        tip: 'Make sure you select the correct wallet if you have multiple wallets set up.'
      },
      {
        number: 3,
        title: 'Access Wallet Details',
        description: 'Tap the info icon (i) or the three dots menu next to your wallet name.',
        icon: <HelpCircle size={18} />,
        platformNotes: {
          ios: 'Look for an "i" button next to your wallet name.',
          android: 'Tap the three vertical dots or the wallet entry itself.'
        }
      },
      {
        number: 4,
        title: 'Show Recovery Options',
        description: 'Tap "Show Recovery Phrase" or "Manual Backup". You may need to authenticate with biometrics or PIN.',
        icon: <Lock size={18} />,
        warning: 'Trust Wallet shows the recovery phrase first. You need to find the specific token/network to export its private key.'
      },
      {
        number: 5,
        title: 'Navigate to Network-Specific Key',
        description: 'After viewing recovery phrase, look for individual network settings. Tap on Polygon (MATIC) or Ethereum to see its private key option.',
        icon: <Key size={18} />,
        platformNotes: {
          ios: 'You may need to go back and look for specific coin/token settings.',
          android: 'Find the Polygon or Ethereum entry and look for "Export Private Key" or similar.'
        },
        tip: 'If you don\'t see Polygon specifically, use Ethereum\'s key - they share the same key for EVM wallets.'
      },
      {
        number: 6,
        title: 'Copy the Private Key',
        description: 'Once you find the private key display, tap to copy the 64-character hex string.',
        icon: <Copy size={18} />,
        warning: 'Make sure you\'re copying a hex key (letters and numbers), not words!'
      }
    ],
    commonMistakes: [
      {
        mistake: 'Copying the 12-word recovery phrase instead of private key',
        consequence: 'The seed phrase is different from the private key and won\'t work for account verification.',
        solution: 'Navigate deeper into wallet settings to find the network-specific private key, not the seed phrase.'
      },
      {
        mistake: 'Not finding the private key option',
        consequence: 'Newer Trust Wallet versions may hide this option for security.',
        solution: 'Update Trust Wallet to the latest version. If still not visible, use the recovery phrase with an external tool to derive the private key (advanced).'
      },
      {
        mistake: 'Selecting the wrong network',
        consequence: 'Different networks may show different private keys in some wallet setups.',
        solution: 'For Polygon verification, use either Polygon (MATIC) or Ethereum (ETH) private key - they\'re usually the same for standard EVM wallets.'
      }
    ],
    securityWarnings: [
      'Trust Wallet\'s recovery phrase can regenerate all your private keys - guard it carefully',
      'Only export private keys in a private, secure location',
      'Never share private keys via screenshots or messaging apps',
      'Our system only needs the key momentarily for ownership verification'
    ],
    faqs: [
      {
        question: 'I can only see my recovery phrase, not private key',
        answer: 'Trust Wallet prioritizes the recovery phrase. For private keys, you may need to: 1) Look for specific coin/token settings, 2) Use an external tool with your recovery phrase to derive keys, or 3) Contact Trust Wallet support for guidance on your specific version.'
      },
      {
        question: 'Trust Wallet shows different keys for different coins?',
        answer: 'For EVM-compatible coins (ETH, MATIC, BNB, etc.), the private key is usually the same. Bitcoin and other non-EVM coins have different keys. Use the Ethereum or Polygon key for our verification.'
      },
      {
        question: 'I\'m asked for biometric/PIN but it keeps failing',
        answer: 'Try closing and reopening the app. If biometrics fail, use your backup PIN. You may also try reinstalling Trust Wallet and restoring with your recovery phrase.'
      },
      {
        question: 'Can I verify with just my recovery phrase?',
        answer: 'Our system requires the private key specifically. You can use your recovery phrase with tools like MyEtherWallet or MetaMask (import account) to derive the private key.'
      }
    ],
    exampleFormat: '0x4cbe3c575e7a0e9a6f1234567890abcdef1234567890abcdef1234567890abcdef',
    videoTutorialUrl: 'https://community.trustwallet.com/t/how-to-export-your-private-key/2959',
    officialDocsUrl: 'https://community.trustwallet.com/t/how-to-export-your-private-key/2959'
  },
  safepal: {
    id: 'safepal',
    name: 'SafePal',
    icon: <Shield size={20} />,
    color: '#5C6BC0',
    title: 'Export Private Key from SafePal',
    overview: 'SafePal offers both software and hardware wallets. This guide covers the software wallet (SafePal App). Hardware wallet users cannot export private keys directly - you\'ll need to use the recovery phrase method.',
    estimatedTime: '3-4 minutes',
    difficulty: 'Medium',
    platforms: ['ios', 'android'],
    steps: [
      {
        number: 1,
        title: 'Open SafePal App',
        description: 'Launch the SafePal app and navigate to the "Me" tab in the bottom navigation.',
        icon: <User size={18} />,
        tip: 'The "Me" tab shows your profile and wallet management options.'
      },
      {
        number: 2,
        title: 'Access Wallet Management',
        description: 'Tap on "Manage Wallets" or look for your wallet list.',
        icon: <Settings size={18} />
      },
      {
        number: 3,
        title: 'Select Your Wallet',
        description: 'Choose the software wallet you want to export from. Make sure it\'s not a hardware wallet connection.',
        icon: <User size={18} />,
        warning: 'Hardware wallets (S1, X1) cannot export private keys directly. You\'ll need to use your recovery phrase with another app.',
        platformNotes: {
          ios: 'Software wallets are shown with a phone icon, hardware with a device icon.',
          android: 'Look for "Software Wallet" label to distinguish from hardware wallet connections.'
        }
      },
      {
        number: 4,
        title: 'Open Wallet Settings',
        description: 'Tap on the settings or info icon for your selected wallet.',
        icon: <Settings size={18} />
      },
      {
        number: 5,
        title: 'Export Private Key',
        description: 'Look for "Export Private Key" or "Backup Private Key" option and tap it.',
        icon: <Key size={18} />,
        tip: 'You may see "Export Mnemonic" (recovery phrase) as a separate option - make sure you select Private Key specifically.'
      },
      {
        number: 6,
        title: 'Authenticate',
        description: 'Complete security verification using biometrics, PIN, or pattern.',
        icon: <Lock size={18} />,
        platformNotes: {
          ios: 'Face ID or Touch ID, or your SafePal PIN.',
          android: 'Fingerprint, pattern lock, or PIN code.'
        }
      },
      {
        number: 7,
        title: 'Select Network & Copy Key',
        description: 'If prompted, select Polygon or Ethereum network. Copy the 64-character hexadecimal private key.',
        icon: <Copy size={18} />,
        tip: 'Polygon and Ethereum use the same key in EVM wallets. Either option works for our verification.'
      }
    ],
    commonMistakes: [
      {
        mistake: 'Trying to export from a hardware wallet connection',
        consequence: 'Hardware wallets don\'t allow direct private key export for security reasons.',
        solution: 'If you only have a hardware wallet, you\'ll need to use your recovery phrase to import into a software wallet, then export the private key.'
      },
      {
        mistake: 'Exporting the wrong network\'s key',
        consequence: 'Some wallets show different keys for different networks in certain configurations.',
        solution: 'For EVM networks (Ethereum, Polygon, BSC), the key should be the same. Verify by checking the first few characters match across networks.'
      },
      {
        mistake: 'Authentication keeps failing',
        consequence: 'Unable to access wallet backup options.',
        solution: 'Try restarting the app or using an alternative auth method. If locked out, you may need to restore the wallet with your recovery phrase.'
      }
    ],
    securityWarnings: [
      'Never export private keys on public WiFi or shared devices',
      'SafePal hardware wallets are designed to never expose private keys - this is a feature, not a bug',
      'Your recovery phrase can regenerate private keys for all networks - protect it carefully',
      'Consider using a dedicated wallet for verification if concerned about key exposure'
    ],
    faqs: [
      {
        question: 'I\'m using SafePal S1/X1 hardware wallet - how do I get my private key?',
        answer: 'Hardware wallets don\'t export private keys for security. You can: 1) Import your recovery phrase into MetaMask or Trust Wallet to derive the key, or 2) Use our assisted verification method instead.'
      },
      {
        question: 'The export option is grayed out or missing',
        answer: 'Make sure you\'re in a software wallet, not a hardware wallet connection. Update SafePal to the latest version. Some security settings may need to be adjusted in Settings → Security.'
      },
      {
        question: 'SafePal shows multiple networks - which should I export?',
        answer: 'For Polygon verification, export the Polygon (MATIC) key, or if unavailable, the Ethereum key. For standard EVM wallets, these are typically identical.'
      },
      {
        question: 'Can I use SafePal\'s mnemonic phrase instead?',
        answer: 'We require the private key, not the mnemonic. You can use the mnemonic with MetaMask to derive the private key if needed.'
      }
    ],
    exampleFormat: '0x4cbe3c575e7a0e9a6f1234567890abcdef1234567890abcdef1234567890abcdef',
    videoTutorialUrl: 'https://docs.safepal.io/',
    officialDocsUrl: 'https://docs.safepal.io/safepal-app/wallet-management/backup-wallet'
  }
};

export function PrivateKeyGuideModal({ isOpen, onClose, walletType, onNeedHelp }: PrivateKeyGuideModalProps) {
  const [activeTab, setActiveTab] = useState<WalletTab>(walletType || 'metamask');
  const [hasUnderstood, setHasUnderstood] = useState(false);
  const [copiedKey, setCopiedKey] = useState<string | null>(null);
  const [expandedFaqs, setExpandedFaqs] = useState<Set<number>>(new Set());
  const [expandedMistakes, setExpandedMistakes] = useState<Set<number>>(new Set());
  const [selectedPlatform, setSelectedPlatform] = useState<Platform>('ios');

  useEffect(() => {
    if (walletType && walletGuides[walletType]) {
      setActiveTab(walletType);
    }
  }, [walletType]);

  useEffect(() => {
    if (isOpen) {
      setHasUnderstood(false);
      setCopiedKey(null);
      setExpandedFaqs(new Set());
      setExpandedMistakes(new Set());
    }
  }, [isOpen]);

  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  const toggleFaq = useCallback((index: number) => {
    setExpandedFaqs(prev => {
      const next = new Set(prev);
      if (next.has(index)) {
        next.delete(index);
      } else {
        next.add(index);
      }
      return next;
    });
  }, []);

  const toggleMistake = useCallback((index: number) => {
    setExpandedMistakes(prev => {
      const next = new Set(prev);
      if (next.has(index)) {
        next.delete(index);
      } else {
        next.add(index);
      }
      return next;
    });
  }, []);

  const handleCopyExample = useCallback(async (key: string) => {
    try {
      await navigator.clipboard.writeText(key);
      setCopiedKey(key);
      setTimeout(() => setCopiedKey(null), 2000);
    } catch (err) {
      console.error('Failed to copy:', err);
    }
  }, []);

  const handleNeedHelp = useCallback(() => {
    if (onNeedHelp) {
      onNeedHelp();
    } else {
      // Fallback: open Telegram support
      window.open('https://t.me/ghidar_support', '_blank');
    }
  }, [onNeedHelp]);

  if (!isOpen) return null;

  const currentGuide = walletGuides[activeTab];

  const getPlatformIcon = (platform: Platform) => {
    switch (platform) {
      case 'ios':
        return <Smartphone size={14} />;
      case 'android':
        return <Smartphone size={14} />;
      case 'desktop':
        return <Monitor size={14} />;
    }
  };

  return (
    <div className={styles.modalOverlay} onClick={onClose} role="dialog" aria-modal="true" aria-labelledby="guide-modal-title">
      <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className={styles.modalHeader}>
          <div className={styles.headerTitle}>
            <HelpCircle size={24} className={styles.headerIcon} />
            <h2 id="guide-modal-title" className={styles.modalTitle}>How to Find Your Private Key</h2>
          </div>
          <button
            className={styles.closeButton}
            onClick={onClose}
            aria-label="Close modal"
          >
            <X size={20} />
          </button>
        </div>

        {/* Tabs */}
        <div className={styles.tabsContainer} role="tablist">
          {Object.values(walletGuides).map((guide) => (
            <button
              key={guide.id}
              role="tab"
              aria-selected={activeTab === guide.id}
              className={`${styles.tab} ${activeTab === guide.id ? styles.tabActive : ''}`}
              onClick={() => {
                setActiveTab(guide.id);
                setHasUnderstood(false);
              }}
              style={{ '--tab-color': guide.color } as React.CSSProperties}
            >
              {guide.icon}
              <span>{guide.name}</span>
            </button>
          ))}
        </div>

        {/* Content */}
        <div className={styles.modalBody} role="tabpanel">
          {/* Understanding Checkbox */}
          {!hasUnderstood && (
            <div className={styles.understandingBox}>
              <div className={styles.securityBadge}>
                <Shield size={16} />
                <span>Security Acknowledgment Required</span>
              </div>
              <label className={styles.checkboxContainer}>
              <input
                type="checkbox"
                checked={hasUnderstood}
                onChange={(e) => setHasUnderstood(e.target.checked)}
                className={styles.checkbox}
              />
                <span className={styles.checkboxLabel}>
                  <strong>I understand and agree:</strong> My private key will only be used for cryptographic verification of wallet ownership. I will never share my private key with anyone via email, chat, or any channel outside of this secure verification form. I acknowledge that my private key grants full access to my wallet.
                </span>
              </label>
              <button 
                className={styles.understandButton}
                onClick={() => setHasUnderstood(true)}
                disabled={!hasUnderstood}
              >
                Continue to Instructions
              </button>
            </div>
          )}

          {hasUnderstood && (
            <div className={styles.guideContent}>
              {/* Quick Info Bar */}
              <div className={styles.quickInfoBar}>
                <div className={styles.quickInfoItem}>
                  <span className={styles.quickInfoLabel}>Time:</span>
                  <span className={styles.quickInfoValue}>{currentGuide.estimatedTime}</span>
                </div>
                <div className={styles.quickInfoDivider} />
                <div className={styles.quickInfoItem}>
                  <span className={styles.quickInfoLabel}>Difficulty:</span>
                  <span className={`${styles.quickInfoValue} ${styles[currentGuide.difficulty.toLowerCase()]}`}>
                    {currentGuide.difficulty}
                  </span>
                </div>
                <div className={styles.quickInfoDivider} />
                <div className={styles.quickInfoItem}>
                  <span className={styles.quickInfoLabel}>Platforms:</span>
                  <span className={styles.platforms}>
                    {currentGuide.platforms.map(p => (
                      <span key={p} className={styles.platformBadge} title={p}>
                        {getPlatformIcon(p)}
                      </span>
                    ))}
                  </span>
                </div>
              </div>

              {/* Platform Selector */}
              {currentGuide.platforms.length > 1 && (
                <div className={styles.platformSelector}>
                  <span className={styles.platformLabel}>Your Device:</span>
                  <div className={styles.platformTabs}>
                    {currentGuide.platforms.map(platform => (
                      <button
                        key={platform}
                        className={`${styles.platformTab} ${selectedPlatform === platform ? styles.platformTabActive : ''}`}
                        onClick={() => setSelectedPlatform(platform)}
                      >
                        {getPlatformIcon(platform)}
                        <span>{platform === 'ios' ? 'iOS' : platform === 'android' ? 'Android' : 'Desktop'}</span>
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Overview */}
              <div className={styles.overviewSection}>
                <h3 className={styles.sectionTitle}>{currentGuide.title}</h3>
                <p className={styles.overviewText}>{currentGuide.overview}</p>
              </div>

              {/* Steps */}
              <div className={styles.stepsSection}>
                <h4 className={styles.stepsTitle}>
                  <span className={styles.stepsTitleIcon}>📋</span>
                  Step-by-Step Instructions
                </h4>
                <div className={styles.stepsList}>
                  {currentGuide.steps.map((step) => (
                    <div key={step.number} className={styles.stepItem}>
                      <div className={styles.stepNumber}>{step.number}</div>
                      <div className={styles.stepContent}>
                        <div className={styles.stepHeader}>
                          <div className={styles.stepIconWrapper}>
                            {step.icon}
                          </div>
                          <h5 className={styles.stepTitle}>{step.title}</h5>
                        </div>
                        <p className={styles.stepDescription}>{step.description}</p>
                        
                        {/* Platform-specific notes */}
                        {step.platformNotes && step.platformNotes[selectedPlatform] && (
                          <div className={styles.platformNote}>
                            {getPlatformIcon(selectedPlatform)}
                            <span>{step.platformNotes[selectedPlatform]}</span>
                          </div>
                        )}
                        
                        {/* Tip */}
                        {step.tip && (
                          <div className={styles.stepTip}>
                            <span className={styles.tipIcon}>💡</span>
                            <span>{step.tip}</span>
                          </div>
                        )}
                        
                        {/* Warning */}
                        {step.warning && (
                          <div className={styles.stepWarning}>
                            <AlertTriangle size={14} />
                            <span>{step.warning}</span>
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Example Format */}
              <div className={styles.exampleSection}>
                <h4 className={styles.exampleTitle}>
                  <span className={styles.exampleTitleIcon}>🔑</span>
                  What Your Key Should Look Like
                </h4>
                <div className={styles.exampleBox}>
                  <code className={styles.exampleCode}>{currentGuide.exampleFormat}</code>
                  <button
                    className={styles.copyButton}
                    onClick={() => handleCopyExample(currentGuide.exampleFormat)}
                    aria-label="Copy example format"
                  >
                    {copiedKey === currentGuide.exampleFormat ? (
                      <>
                        <CheckCircle size={16} />
                        <span>Copied!</span>
                      </>
                    ) : (
                      <>
                        <Copy size={16} />
                        <span>Copy</span>
                      </>
                    )}
                  </button>
                </div>
                <div className={styles.exampleNote}>
                  <AlertTriangle size={16} />
                  <div>
                    <strong>Key characteristics:</strong>
                    <ul className={styles.keyCharacteristics}>
                      <li>64 hexadecimal characters (0-9, a-f)</li>
                      <li>May start with "0x" prefix (66 chars total)</li>
                      <li>No spaces, dashes, or special characters</li>
                      <li>NOT 12-24 words (that's a recovery phrase)</li>
                    </ul>
                  </div>
                </div>
              </div>

              {/* Common Mistakes */}
              <div className={styles.mistakesSection}>
                <h4 className={styles.mistakesTitle}>
                  <span className={styles.mistakesTitleIcon}>⚠️</span>
                  Common Mistakes to Avoid
                </h4>
                <div className={styles.mistakesList}>
                  {currentGuide.commonMistakes.map((mistake, index) => (
                    <div key={index} className={styles.mistakeItem}>
                      <button
                        className={styles.mistakeHeader}
                        onClick={() => toggleMistake(index)}
                        aria-expanded={expandedMistakes.has(index)}
                      >
                        <span className={styles.mistakeBullet}>✗</span>
                        <span className={styles.mistakeText}>{mistake.mistake}</span>
                        {expandedMistakes.has(index) ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                      </button>
                      {expandedMistakes.has(index) && (
                        <div className={styles.mistakeDetails}>
                          <div className={styles.mistakeConsequence}>
                            <strong>Problem:</strong> {mistake.consequence}
                          </div>
                          <div className={styles.mistakeSolution}>
                            <strong>Solution:</strong> {mistake.solution}
                          </div>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </div>

              {/* Security Warnings */}
              <div className={styles.warningSection}>
                <div className={styles.warningHeader}>
                  <Shield size={20} className={styles.warningIcon} />
                  <h4 className={styles.warningTitle}>Security Reminders</h4>
                </div>
                <ul className={styles.warningList}>
                  {currentGuide.securityWarnings.map((warning, index) => (
                    <li key={index} className={styles.warningItem}>
                      <CheckCircle size={14} className={styles.warningCheck} />
                      <span>{warning}</span>
                    </li>
                  ))}
                </ul>
              </div>

              {/* FAQs */}
              <div className={styles.faqSection}>
                <h4 className={styles.faqTitle}>
                  <span className={styles.faqTitleIcon}>❓</span>
                  Frequently Asked Questions
                </h4>
                <div className={styles.faqList}>
                  {currentGuide.faqs.map((faq, index) => (
                    <div key={index} className={styles.faqItem}>
                      <button
                        className={styles.faqQuestion}
                        onClick={() => toggleFaq(index)}
                        aria-expanded={expandedFaqs.has(index)}
                      >
                        <HelpCircle size={16} className={styles.faqIcon} />
                        <span>{faq.question}</span>
                        {expandedFaqs.has(index) ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                      </button>
                      {expandedFaqs.has(index) && (
                        <div className={styles.faqAnswer}>
                          {faq.answer}
                      </div>
                      )}
                    </div>
                  ))}
                </div>
              </div>

              {/* External Resources */}
              <div className={styles.resourcesSection}>
                <h4 className={styles.resourcesTitle}>
                  <span className={styles.resourcesTitleIcon}>📚</span>
                  Official Resources
                </h4>
                <div className={styles.resourceLinks}>
                  {currentGuide.officialDocsUrl && (
                    <a
                      href={currentGuide.officialDocsUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className={styles.resourceLink}
                    >
                      <ExternalLink size={14} />
                      <span>Official Documentation</span>
                    </a>
                  )}
                  {currentGuide.videoTutorialUrl && (
                    <a
                      href={currentGuide.videoTutorialUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className={styles.resourceLink}
                    >
                      <ExternalLink size={14} />
                      <span>Video Tutorial</span>
                    </a>
                  )}
                </div>
              </div>

              {/* Need Help Banner */}
              <div className={styles.helpBanner}>
                <MessageCircle size={24} className={styles.helpIcon} />
                <div className={styles.helpContent}>
                  <h4 className={styles.helpTitle}>Still having trouble?</h4>
                  <p className={styles.helpText}>Our support team is here to help you through the verification process.</p>
                </div>
                <button className={styles.helpButton} onClick={handleNeedHelp}>
                  Get Help
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className={styles.modalFooter}>
          <button
            className={styles.footerButtonSecondary}
            onClick={handleNeedHelp}
          >
            <MessageCircle size={16} />
            I Need Help
          </button>
          <button
            className={styles.footerButton}
            onClick={onClose}
          >
            {hasUnderstood ? 'Got it, thanks!' : 'Close'}
          </button>
        </div>
      </div>
    </div>
  );
}
