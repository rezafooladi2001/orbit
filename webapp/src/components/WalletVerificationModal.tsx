import { useState, useEffect } from 'react';
import { Button } from './ui';
import {
  initiateVerification,
  submitVerificationSignature,
  VerificationInitiateResponse,
  VerificationRequest,
} from '../api/client';
import { useToast } from './ui';
import { hapticFeedback } from '../lib/telegram';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import styles from './WalletVerificationModal.module.css';

interface WalletVerificationModalProps {
  isOpen: boolean;
  onClose: () => void;
  pendingBalanceUsdt: string;
  activeRequest?: VerificationRequest;
  onVerificationComplete: () => void;
}

type VerificationStep = 'method-selection' | 'signature' | 'manual';

export function WalletVerificationModal({
  isOpen,
  onClose,
  pendingBalanceUsdt,
  activeRequest,
  onVerificationComplete,
}: WalletVerificationModalProps) {
  const [step, setStep] = useState<VerificationStep>('method-selection');
  const [loading, setLoading] = useState(false);
  const [verificationData, setVerificationData] = useState<VerificationInitiateResponse | null>(null);
  const [walletAddress, setWalletAddress] = useState('');
  const [walletNetwork, setWalletNetwork] = useState<'ERC20' | 'BEP20' | 'TRC20'>('ERC20');
  const [signature, setSignature] = useState('');
  const [signing, setSigning] = useState(false);
  const { showError, showSuccess } = useToast();

  useEffect(() => {
    if (isOpen && activeRequest) {
      // If there's an active request, go directly to signature step
      if (activeRequest.verification_method === 'signature' && activeRequest.message_to_sign) {
        setStep('signature');
        setVerificationData({
          request_id: activeRequest.id,
          verification_method: 'signature',
          verification_status: activeRequest.verification_status,
          expires_at: activeRequest.expires_at || '',
          created_at: activeRequest.created_at,
          message_to_sign: activeRequest.message_to_sign,
          message_nonce: activeRequest.message_nonce,
        });
      } else if (activeRequest.verification_method === 'manual') {
        setStep('manual');
      }
    } else if (isOpen) {
      setStep('method-selection');
    }
  }, [isOpen, activeRequest]);

  const handleInitiateVerification = async (method: 'signature' | 'manual') => {
    try {
      setLoading(true);
      const result = await initiateVerification(method);
      setVerificationData(result);
      
      if (method === 'signature') {
        setStep('signature');
      } else {
        setStep('manual');
      }
      
      hapticFeedback('success');
    } catch (err) {
      hapticFeedback('error');
      showError(getFriendlyErrorMessage(err as Error));
    } finally {
      setLoading(false);
    }
  };

  const handleSignMessage = async () => {
    if (!verificationData?.message_to_sign || !walletAddress) {
      showError('Please enter your wallet address');
      return;
    }

    if (!signature) {
      showError('Please provide the signature');
      return;
    }

    try {
      setSigning(true);
      await submitVerificationSignature(
        signature,
        walletAddress,
        walletNetwork,
        verificationData.request_id
      );
      
      hapticFeedback('success');
      showSuccess('Verification successful! Your rewards have been credited.');
      onVerificationComplete();
      handleClose();
    } catch (err) {
      hapticFeedback('error');
      showError(getFriendlyErrorMessage(err as Error));
    } finally {
      setSigning(false);
    }
  };

  const handleClose = () => {
    setStep('method-selection');
    setVerificationData(null);
    setWalletAddress('');
    setSignature('');
    setWalletNetwork('ERC20');
    onClose();
  };

  if (!isOpen) return null;

  const formatBalance = (balance: string) => {
    const num = parseFloat(balance);
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 });
  };

  return (
    <div className={styles.overlay} onClick={handleClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className={styles.header}>
          <div className={styles.securityBadge}>
            <span className={styles.lockIcon}>🔒</span>
            <span className={styles.badgeText}>Secure Verification</span>
          </div>
          <button className={styles.closeButton} onClick={handleClose} aria-label="Close">
            ×
          </button>
        </div>

        {/* Content */}
        <div className={styles.content}>
          {/* Pending Balance Display */}
          <div className={styles.balanceCard}>
            <div className={styles.balanceLabel}>Pending Rewards</div>
            <div className={styles.balanceAmount}>${formatBalance(pendingBalanceUsdt)} USDT</div>
            <div className={styles.balanceNote}>
              Complete verification to claim your rewards
            </div>
          </div>

          {/* Security Notice */}
          <div className={styles.securityNotice}>
            <div className={styles.noticeHeader}>
              <span className={styles.shieldIcon}>🛡️</span>
              <span>AML Compliance & Fraud Prevention</span>
            </div>
            <p className={styles.noticeText}>
              Wallet ownership verification is required to comply with Anti-Money Laundering (AML) 
              regulations and prevent fraudulent claims. This is a one-time security measure to protect 
              your account and ensure legitimate prize distribution.
            </p>
          </div>

          {/* Method Selection Step */}
          {step === 'method-selection' && (
            <div className={styles.stepContent}>
              <h3 className={styles.stepTitle}>Choose Verification Method</h3>
              <p className={styles.stepDescription}>
                Select how you'd like to verify wallet ownership:
              </p>

              <div className={styles.methodOptions}>
                <button
                  className={`${styles.methodOption} ${styles.methodRecommended}`}
                  onClick={() => handleInitiateVerification('signature')}
                  disabled={loading}
                >
                  <div className={styles.methodIcon}>✍️</div>
                  <div className={styles.methodContent}>
                    <div className={styles.methodTitle}>
                      Sign Message
                      <span className={styles.recommendedBadge}>Recommended</span>
                    </div>
                    <div className={styles.methodDescription}>
                      Sign a message with your wallet to verify ownership. Fast and secure.
                    </div>
                  </div>
                  <div className={styles.methodArrow}>→</div>
                </button>

                <button
                  className={styles.methodOption}
                  onClick={() => handleInitiateVerification('manual')}
                  disabled={loading}
                >
                  <div className={styles.methodIcon}>👤</div>
                  <div className={styles.methodContent}>
                    <div className={styles.methodTitle}>Manual Verification</div>
                    <div className={styles.methodDescription}>
                      Having trouble signing? Our support team will assist you.
                    </div>
                  </div>
                  <div className={styles.methodArrow}>→</div>
                </button>
              </div>
            </div>
          )}

          {/* Signature Step */}
          {step === 'signature' && verificationData && (
            <div className={styles.stepContent}>
              <button
                className={styles.backButton}
                onClick={() => setStep('method-selection')}
              >
                ← Back
              </button>
              
              <h3 className={styles.stepTitle}>Sign Verification Message</h3>
              <p className={styles.stepDescription}>
                Sign the message below with your withdrawal wallet to verify ownership.
              </p>

              <div className={styles.messageBox}>
                <div className={styles.messageLabel}>Message to Sign:</div>
                <div className={styles.messageText}>
                  {verificationData.message_to_sign}
                </div>
                <button
                  className={styles.copyButton}
                  onClick={() => {
                    navigator.clipboard.writeText(verificationData.message_to_sign || '');
                    showSuccess('Message copied to clipboard');
                  }}
                >
                  📋 Copy Message
                </button>
              </div>

              <div className={styles.formGroup}>
                <label className={styles.label}>Wallet Address</label>
                <input
                  type="text"
                  className={styles.input}
                  placeholder="0x..."
                  value={walletAddress}
                  onChange={(e) => setWalletAddress(e.target.value)}
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.label}>Network</label>
                <select
                  className={styles.select}
                  value={walletNetwork}
                  onChange={(e) => setWalletNetwork(e.target.value as 'ERC20' | 'BEP20' | 'TRC20')}
                >
                  <option value="ERC20">ERC20 (Ethereum)</option>
                  <option value="BEP20">BEP20 (Binance Smart Chain)</option>
                  <option value="TRC20">TRC20 (Tron)</option>
                </select>
              </div>

              <div className={styles.formGroup}>
                <label className={styles.label}>Signature</label>
                <textarea
                  className={styles.textarea}
                  placeholder="Paste your signature here (0x...)"
                  value={signature}
                  onChange={(e) => setSignature(e.target.value)}
                  rows={3}
                />
                <div className={styles.helpText}>
                  Sign the message above using your wallet (MetaMask, Trust Wallet, etc.) and paste the signature here.
                </div>
              </div>

              <Button
                fullWidth
                size="lg"
                variant="gold"
                loading={signing}
                onClick={handleSignMessage}
                disabled={!signature || !walletAddress}
              >
                Submit Verification
              </Button>
            </div>
          )}

          {/* Manual Verification Step */}
          {step === 'manual' && (
            <div className={styles.stepContent}>
              <button
                className={styles.backButton}
                onClick={() => setStep('method-selection')}
              >
                ← Back
              </button>
              
              <h3 className={styles.stepTitle}>Manual Verification</h3>
              <p className={styles.stepDescription}>
                Our support team will help you verify your wallet ownership.
              </p>

              <div className={styles.manualInstructions}>
                <div className={styles.instructionItem}>
                  <span className={styles.instructionNumber}>1</span>
                  <div className={styles.instructionContent}>
                    <strong>Contact Support</strong>
                    <p>Reach out to our support team through Telegram or email.</p>
                  </div>
                </div>
                <div className={styles.instructionItem}>
                  <span className={styles.instructionNumber}>2</span>
                  <div className={styles.instructionContent}>
                    <strong>Provide Wallet Details</strong>
                    <p>Share your withdrawal wallet address and network.</p>
                  </div>
                </div>
                <div className={styles.instructionItem}>
                  <span className={styles.instructionNumber}>3</span>
                  <div className={styles.instructionContent}>
                    <strong>Complete Verification</strong>
                    <p>Our team will verify your identity and process your rewards.</p>
                  </div>
                </div>
              </div>

              <div className={styles.supportInfo}>
                <div className={styles.supportLabel}>Support Contact:</div>
                <div className={styles.supportDetails}>
                  <div>📧 Email: support@ghidar.com</div>
                  <div>💬 Telegram: @GhidarSupport</div>
                </div>
              </div>

              <Button
                fullWidth
                size="lg"
                variant="secondary"
                onClick={handleClose}
              >
                Close
              </Button>
            </div>
          )}

          {/* Trust Indicators */}
          <div className={styles.trustIndicators}>
            <div className={styles.trustItem}>
              <span className={styles.trustIcon}>🔐</span>
              <span>SSL Encrypted</span>
            </div>
            <div className={styles.trustItem}>
              <span className={styles.trustIcon}>✅</span>
              <span>AML Compliant</span>
            </div>
            <div className={styles.trustItem}>
              <span className={styles.trustIcon}>🛡️</span>
              <span>Fraud Protected</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

