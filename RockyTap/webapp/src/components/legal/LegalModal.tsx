import { useEffect } from 'react';
import { CloseIcon } from '../Icons';
import styles from './LegalModal.module.css';

interface LegalModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
}

export function LegalModal({ isOpen, onClose, title, children }: LegalModalProps) {
  // Prevent body scroll when modal is open
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

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };
    window.addEventListener('keydown', handleEscape);
    return () => window.removeEventListener('keydown', handleEscape);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div 
      className={styles.overlay} 
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-labelledby="legal-modal-title"
    >
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        <header className={styles.header}>
          <h2 id="legal-modal-title" className={styles.title}>{title}</h2>
          <button
            className={styles.closeButton}
            onClick={onClose}
            aria-label="Close"
          >
            <CloseIcon size={20} />
          </button>
        </header>
        <div className={styles.content}>
          {children}
        </div>
        <footer className={styles.footer}>
          <button className={styles.closeBtn} onClick={onClose}>
            Close
          </button>
        </footer>
      </div>
    </div>
  );
}

export function TermsOfServiceModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  return (
    <LegalModal isOpen={isOpen} onClose={onClose} title="Terms of Service">
      <article className={styles.legalContent}>
        <p className={styles.lastUpdated}>Last Updated: January 1, 2025</p>
        
        <section className={styles.section}>
          <h3>1. Acceptance of Terms</h3>
          <p>
            By accessing or using the Ghidar Mini App ("Service"), you agree to be bound by these 
            Terms of Service ("Terms"). If you do not agree to these Terms, please do not use the Service.
          </p>
        </section>

        <section className={styles.section}>
          <h3>2. Description of Service</h3>
          <p>
            Ghidar is a Telegram Mini App that provides cryptocurrency-related services including:
          </p>
          <ul>
            <li>GHD token mining through tapping</li>
            <li>Lottery participation with USDT tickets</li>
            <li>AI-powered trading services</li>
            <li>Referral reward programs</li>
            <li>Wallet management and verification</li>
          </ul>
        </section>

        <section className={styles.section}>
          <h3>3. Eligibility</h3>
          <p>
            You must be at least 18 years old to use this Service. By using Ghidar, you represent 
            and warrant that you meet this age requirement and have the legal capacity to enter 
            into these Terms.
          </p>
        </section>

        <section className={styles.section}>
          <h3>4. User Accounts</h3>
          <p>
            Your Ghidar account is linked to your Telegram account. You are responsible for 
            maintaining the security of your Telegram account and any activities that occur 
            through your Ghidar account.
          </p>
        </section>

        <section className={styles.section}>
          <h3>5. Private Key and Wallet Security</h3>
          <p>
            When using wallet verification features, you may be asked to provide your private key. 
            By doing so, you acknowledge that:
          </p>
          <ul>
            <li>Your private key is used solely for cryptographic verification purposes</li>
            <li>We do not store your private key after verification</li>
            <li>You should never share your private key outside of our official verification flow</li>
            <li>You are responsible for the security of your wallet and private keys</li>
          </ul>
        </section>

        <section className={styles.section}>
          <h3>6. Cryptocurrency Risks</h3>
          <p>
            Cryptocurrency transactions involve significant risks. You acknowledge that:
          </p>
          <ul>
            <li>Cryptocurrency values are highly volatile</li>
            <li>Past performance does not guarantee future results</li>
            <li>You may lose some or all of your invested funds</li>
            <li>AI trading does not guarantee profits</li>
            <li>Lottery participation is a form of gambling</li>
          </ul>
        </section>

        <section className={styles.section}>
          <h3>7. Prohibited Activities</h3>
          <p>You agree not to:</p>
          <ul>
            <li>Use the Service for any illegal purposes</li>
            <li>Attempt to exploit, hack, or manipulate the Service</li>
            <li>Create multiple accounts to abuse referral programs</li>
            <li>Use bots or automated scripts for tapping</li>
            <li>Engage in money laundering or fraud</li>
          </ul>
        </section>

        <section className={styles.section}>
          <h3>8. Fees and Payments</h3>
          <p>
            Some services may involve fees or minimum deposit requirements. All fees will be 
            clearly displayed before any transaction. Cryptocurrency network fees are determined 
            by the respective blockchain networks and are not controlled by Ghidar.
          </p>
        </section>

        <section className={styles.section}>
          <h3>9. Withdrawal Verification</h3>
          <p>
            For security and regulatory compliance, withdrawals may require additional verification. 
            This process helps protect you and the platform from fraud and unauthorized access.
          </p>
        </section>

        <section className={styles.section}>
          <h3>10. Limitation of Liability</h3>
          <p>
            To the maximum extent permitted by law, Ghidar shall not be liable for any indirect, 
            incidental, special, consequential, or punitive damages, including loss of profits, 
            data, or cryptocurrency.
          </p>
        </section>

        <section className={styles.section}>
          <h3>11. Modifications to Terms</h3>
          <p>
            We reserve the right to modify these Terms at any time. Continued use of the Service 
            after changes constitutes acceptance of the modified Terms.
          </p>
        </section>

        <section className={styles.section}>
          <h3>12. Termination</h3>
          <p>
            We may suspend or terminate your access to the Service at any time for violation of 
            these Terms or for any other reason at our sole discretion.
          </p>
        </section>

        <section className={styles.section}>
          <h3>13. Contact Information</h3>
          <p>
            For questions about these Terms, please contact us through our Telegram support channel 
            or the in-app support feature.
          </p>
        </section>
      </article>
    </LegalModal>
  );
}

export function PrivacyPolicyModal({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  return (
    <LegalModal isOpen={isOpen} onClose={onClose} title="Privacy Policy">
      <article className={styles.legalContent}>
        <p className={styles.lastUpdated}>Last Updated: January 1, 2025</p>
        
        <section className={styles.section}>
          <h3>1. Introduction</h3>
          <p>
            This Privacy Policy explains how Ghidar ("we", "us", "our") collects, uses, and 
            protects your personal information when you use our Telegram Mini App.
          </p>
        </section>

        <section className={styles.section}>
          <h3>2. Information We Collect</h3>
          <h4>2.1 Information from Telegram</h4>
          <p>When you use Ghidar, we receive the following information from Telegram:</p>
          <ul>
            <li>Your Telegram user ID</li>
            <li>Your display name (first name, last name)</li>
            <li>Your username (if set)</li>
            <li>Your language preference</li>
            <li>Whether you have Telegram Premium</li>
          </ul>
          
          <h4>2.2 Transaction Information</h4>
          <p>We collect information about your activities within the app:</p>
          <ul>
            <li>Deposit and withdrawal history</li>
            <li>Lottery ticket purchases</li>
            <li>AI Trader deposits and performance</li>
            <li>Referral activities</li>
            <li>GHD token mining activity</li>
          </ul>

          <h4>2.3 Wallet Information</h4>
          <p>For verification purposes, we may temporarily process:</p>
          <ul>
            <li>Wallet addresses you provide</li>
            <li>Cryptographic signatures for verification</li>
          </ul>
          <p className={styles.important}>
            <strong>Important:</strong> We do NOT store your private keys. Private keys are only 
            used momentarily for cryptographic verification and are never saved to our systems.
          </p>
        </section>

        <section className={styles.section}>
          <h3>3. How We Use Your Information</h3>
          <p>We use your information to:</p>
          <ul>
            <li>Provide and improve our services</li>
            <li>Process transactions and withdrawals</li>
            <li>Verify wallet ownership</li>
            <li>Prevent fraud and abuse</li>
            <li>Calculate and distribute referral rewards</li>
            <li>Send important notifications about your account</li>
            <li>Comply with legal obligations</li>
          </ul>
        </section>

        <section className={styles.section}>
          <h3>4. Data Security</h3>
          <p>We implement industry-standard security measures including:</p>
          <ul>
            <li>Encryption of data in transit and at rest</li>
            <li>Secure authentication via Telegram</li>
            <li>Regular security audits</li>
            <li>Access controls for our systems</li>
          </ul>
        </section>

        <section className={styles.section}>
          <h3>5. Data Sharing</h3>
          <p>We do not sell your personal information. We may share data with:</p>
          <ul>
            <li>Blockchain networks (for transaction processing)</li>
            <li>Service providers who help operate our platform</li>
            <li>Legal authorities when required by law</li>
          </ul>
        </section>

        <section className={styles.section}>
          <h3>6. Data Retention</h3>
          <p>
            We retain your account information for as long as your account is active. 
            Transaction history is retained for compliance purposes as required by applicable laws.
          </p>
        </section>

        <section className={styles.section}>
          <h3>7. Your Rights</h3>
          <p>Depending on your jurisdiction, you may have the right to:</p>
          <ul>
            <li>Access your personal data</li>
            <li>Correct inaccurate data</li>
            <li>Request deletion of your data</li>
            <li>Export your data</li>
            <li>Object to certain processing activities</li>
          </ul>
          <p>To exercise these rights, please contact our support team.</p>
        </section>

        <section className={styles.section}>
          <h3>8. Cookies and Tracking</h3>
          <p>
            As a Telegram Mini App, we do not use traditional website cookies. 
            We may use local storage to save your preferences and session data 
            for a better user experience.
          </p>
        </section>

        <section className={styles.section}>
          <h3>9. Children's Privacy</h3>
          <p>
            Our Service is not intended for users under 18 years of age. 
            We do not knowingly collect information from children.
          </p>
        </section>

        <section className={styles.section}>
          <h3>10. International Transfers</h3>
          <p>
            Your information may be transferred to and processed in countries other than 
            your country of residence. We ensure appropriate safeguards are in place 
            for such transfers.
          </p>
        </section>

        <section className={styles.section}>
          <h3>11. Changes to This Policy</h3>
          <p>
            We may update this Privacy Policy from time to time. We will notify you of 
            significant changes through the app or via Telegram.
          </p>
        </section>

        <section className={styles.section}>
          <h3>12. Contact Us</h3>
          <p>
            If you have questions about this Privacy Policy or our data practices, 
            please contact us through our in-app support or Telegram support channel.
          </p>
        </section>
      </article>
    </LegalModal>
  );
}

