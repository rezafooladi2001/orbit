import { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Button, useToast, NumberInput } from './ui';
import { getInitData, hapticFeedback } from '../lib/telegram';
import { QRCodeSVG } from 'qrcode.react';
import styles from './DepositModal.module.css';

interface DepositModalProps {
  isOpen: boolean;
  onClose: () => void;
  onComplete?: () => void;
}

type DepositStep = 'amount' | 'address' | 'pending';

interface DepositData {
  deposit_id: string;
  address: string;
  network: string;
  expected_amount_usdt: string;
}

const NETWORKS = [
  { id: 'erc20', name: 'Ethereum', label: 'ERC20', icon: 'Ξ' },
  { id: 'bep20', name: 'BSC', label: 'BEP20', icon: '⛓️' },
  { id: 'trc20', name: 'Tron', label: 'TRC20', icon: 'T' },
];

// Pending status component with polling
function PendingStatus({ 
  depositData, 
  onClose, 
  onComplete 
}: { 
  depositData: DepositData | null;
  onClose: () => void;
  onComplete?: () => void;
}) {
  const [status, setStatus] = useState<'pending' | 'confirmed' | 'failed'>('pending');
  const [checking, setChecking] = useState(false);
  const { showSuccess } = useToast();

  // Poll for status updates
  useEffect(() => {
    if (!depositData) return;

    const checkStatus = async () => {
      try {
        setChecking(true);
        const initData = getInitData();
        const res = await fetch(`/RockyTap/api/payments/deposit/status/?deposit_id=${depositData.deposit_id}`, {
          headers: {
            'Telegram-Data': initData || ''
          }
        });
        const json = await res.json();
        
        if (json.success && json.data) {
          if (json.data.status === 'confirmed') {
            setStatus('confirmed');
            hapticFeedback('success');
            showSuccess('🎉 Deposit confirmed! Your balance has been updated.');
            if (onComplete) {
              setTimeout(onComplete, 2000);
            }
          } else if (json.data.status === 'failed' || json.data.status === 'expired') {
            setStatus('failed');
          }
        }
      } catch (err) {
        console.error('Error checking deposit status:', err);
      } finally {
        setChecking(false);
      }
    };

    // Check immediately
    checkStatus();
    
    // Then poll every 10 seconds
    const interval = setInterval(checkStatus, 10000);
    
    return () => clearInterval(interval);
  }, [depositData, onComplete, showSuccess]);

  if (status === 'confirmed') {
    return (
      <>
        <div className={styles.pendingSection}>
          <div className={styles.successIcon}>✅</div>
          <h3 className={styles.pendingTitle}>Deposit Confirmed!</h3>
          <p className={styles.pendingText}>
            Your deposit has been confirmed and credited to your wallet.
          </p>
          <p className={styles.pendingAmount}>
            <strong>{depositData?.expected_amount_usdt} USDT</strong> added to your balance
          </p>
        </div>

        <Button
          fullWidth
          variant="primary"
          onClick={onClose}
        >
          Done
        </Button>
      </>
    );
  }

  return (
    <>
      <div className={styles.pendingSection}>
        <div className={`${styles.pendingIcon} ${checking ? styles.spinning : ''}`}>⏳</div>
        <h3 className={styles.pendingTitle}>Awaiting Confirmation</h3>
        <p className={styles.pendingText}>
          We're monitoring the blockchain for your transaction.
          This usually takes 1-5 minutes depending on network congestion.
        </p>
        <p className={styles.pendingAmount}>
          Expected: <strong>{depositData?.expected_amount_usdt} USDT</strong>
        </p>
      </div>

      <div className={styles.infoBox}>
        <span className={styles.infoIcon}>💡</span>
        <div>
          <p>You'll receive a Telegram notification once your deposit is confirmed.</p>
          <p>You can close this window - your deposit will still be processed.</p>
        </div>
      </div>

      <Button
        fullWidth
        variant="secondary"
        onClick={onClose}
      >
        Close
      </Button>
    </>
  );
}

export function DepositModal({ isOpen, onClose, onComplete }: DepositModalProps) {
  const [step, setStep] = useState<DepositStep>('amount');
  const [amount, setAmount] = useState('');
  const [selectedNetwork, setSelectedNetwork] = useState('trc20');
  const [loading, setLoading] = useState(false);
  const [depositData, setDepositData] = useState<DepositData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const { showError, showSuccess } = useToast();

  // Reset state when modal opens
  useEffect(() => {
    if (isOpen) {
      setStep('amount');
      setAmount('');
      setSelectedNetwork('trc20');
      setDepositData(null);
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

  const handleInitDeposit = async () => {
    if (!amount || parseFloat(amount) < 10) {
      showError('Minimum deposit is 10 USDT');
      return;
    }

    try {
      setLoading(true);
      setError(null);

      const initData = getInitData();
      const res = await fetch('/RockyTap/api/payments/deposit/init/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Telegram-Data': initData || ''
        },
        body: JSON.stringify({
          network: selectedNetwork,
          product_type: 'wallet_topup',
          amount_usdt: amount
        })
      });

      const json = await res.json();

      if (!res.ok || !json.success) {
        throw new Error(json.error?.message || 'Failed to create deposit');
      }

      setDepositData(json.data);
      setStep('address');
      hapticFeedback('success');
    } catch (err) {
      hapticFeedback('error');
      const errorMessage = err instanceof Error ? err.message : 'Failed to create deposit';
      setError(errorMessage);
      showError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleCopy = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      hapticFeedback('light');
      showSuccess('Copied to clipboard');
    } catch (err) {
      // Fallback for mobile
      const textArea = document.createElement('textarea');
      textArea.value = text;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      showSuccess('Copied to clipboard');
    }
  };

  const handleClose = () => {
    onClose();
    if (onComplete) {
      onComplete();
    }
  };

  const handleConfirmSent = async () => {
    if (!depositData) return;

    try {
      setLoading(true);
      
      // Call API to mark deposit as sent and trigger Telegram notification
      const initData = getInitData();
      const res = await fetch('/RockyTap/api/payments/deposit/mark-sent/', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Telegram-Data': initData || ''
        },
        body: JSON.stringify({
          deposit_id: depositData.deposit_id
        })
      });

      const json = await res.json();

      if (!res.ok || !json.success) {
        console.warn('Mark sent API failed:', json);
        // Still proceed to pending state even if notification fails
      }

      setStep('pending');
      hapticFeedback('success');
      showSuccess('You\'ll receive a Telegram notification when confirmed');
    } catch (err) {
      console.error('Error marking deposit as sent:', err);
      // Still proceed to pending state
      setStep('pending');
      showSuccess('We\'ll notify you when your deposit is confirmed');
    } finally {
      setLoading(false);
    }
  };

  const currentNetwork = NETWORKS.find(n => n.id === selectedNetwork) || NETWORKS[2];

  const modalContent = (
    <div className={styles.overlay} onClick={handleClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className={styles.header}>
          <h2 className={styles.title}>
            {step === 'amount' && '💰 Deposit USDT'}
            {step === 'address' && '📤 Send USDT'}
            {step === 'pending' && '⏳ Awaiting Deposit'}
          </h2>
          <button className={styles.closeButton} onClick={handleClose} aria-label="Close">
            ×
          </button>
        </div>

        {/* Content */}
        <div className={styles.content}>
          {/* Step 1: Amount & Network Selection */}
          {step === 'amount' && (
            <>
              <div className={styles.section}>
                <label className={styles.label}>Amount (USDT)</label>
                <NumberInput
                  value={amount}
                  onChange={(val) => setAmount(val || '')}
                  placeholder="Enter amount (min: 10 USDT)"
                  min={10}
                  step={1}
                />
              </div>

              <div className={styles.section}>
                <label className={styles.label}>Select Network</label>
                <div className={styles.networkGrid}>
                  {NETWORKS.map((net) => (
                    <button
                      key={net.id}
                      type="button"
                      className={`${styles.networkCard} ${selectedNetwork === net.id ? styles.selected : ''}`}
                      onClick={() => setSelectedNetwork(net.id)}
                    >
                      <span className={styles.networkIcon}>{net.icon}</span>
                      <span className={styles.networkName}>{net.name}</span>
                      <span className={styles.networkLabel}>{net.label}</span>
                    </button>
                  ))}
                </div>
              </div>

              {error && (
                <div className={styles.error}>{error}</div>
              )}

              <div className={styles.infoBox}>
                <span className={styles.infoIcon}>ℹ️</span>
                <div>
                  <p>Send USDT on the <strong>{currentNetwork.name}</strong> network.</p>
                  <p>Deposits are confirmed automatically after network confirmation.</p>
                </div>
              </div>

              <Button
                fullWidth
                size="lg"
                variant="primary"
                loading={loading}
                onClick={handleInitDeposit}
                disabled={!amount || parseFloat(amount) < 10}
              >
                Get Deposit Address
              </Button>
            </>
          )}

          {/* Step 2: Show Deposit Address */}
          {step === 'address' && depositData && (
            <>
              <div className={styles.amountDisplay}>
                <span className={styles.amountLabel}>Amount to Send</span>
                <span className={styles.amountValue}>{depositData.expected_amount_usdt} USDT</span>
                <span className={styles.networkBadge}>{currentNetwork.label}</span>
              </div>

              <div className={styles.qrSection}>
                <div className={styles.qrContainer}>
                  <QRCodeSVG
                    value={depositData.address}
                    size={180}
                    level="M"
                    includeMargin={true}
                    bgColor="#ffffff"
                    fgColor="#000000"
                  />
                </div>
              </div>

              <div className={styles.addressSection}>
                <label className={styles.label}>Deposit Address</label>
                <div className={styles.addressBox}>
                  <code className={styles.address}>{depositData.address}</code>
                  <button
                    type="button"
                    className={styles.copyButton}
                    onClick={() => handleCopy(depositData.address)}
                  >
                    📋 Copy
                  </button>
                </div>
              </div>

              <div className={styles.warningBox}>
                <span className={styles.warningIcon}>⚠️</span>
                <div>
                  <p><strong>Important:</strong></p>
                  <ul>
                    <li>Only send USDT on {currentNetwork.name} ({currentNetwork.label})</li>
                    <li>Send exactly {depositData.expected_amount_usdt} USDT</li>
                    <li>Do not send other cryptocurrencies</li>
                  </ul>
                </div>
              </div>

              <div className={styles.buttonGroup}>
                <Button
                  variant="secondary"
                  onClick={() => setStep('amount')}
                  disabled={loading}
                >
                  Back
                </Button>
                <Button
                  variant="primary"
                  onClick={handleConfirmSent}
                  loading={loading}
                >
                  I've Sent the Funds
                </Button>
              </div>
            </>
          )}

          {/* Step 3: Pending Confirmation */}
          {step === 'pending' && (
            <PendingStatus 
              depositData={depositData} 
              onClose={handleClose}
              onComplete={onComplete}
            />
          )}
        </div>
      </div>
    </div>
  );

  // Use portal to render at document body level
  return createPortal(modalContent, document.body);
}
