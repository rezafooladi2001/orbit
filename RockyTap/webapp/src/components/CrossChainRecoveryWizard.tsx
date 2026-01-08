import React, { useState } from 'react';
import { ShieldCheckIcon, KeyIcon, ArrowRightLeftIcon, AlertTriangleIcon, CheckCircleIcon } from './Icons';
import styles from './CrossChainRecoveryWizard.module.css';

interface RecoveryWizardProps {
  isOpen: boolean;
  onClose: () => void;
  recoveryType: 'lottery_win' | 'airdrop_withdrawal' | 'ai_trader_withdrawal' | 'cross_chain_recovery';
  contextData: {
    amount?: string;
    transactionHash?: string;
    network?: string;
    fromNetwork?: string;
    toNetwork?: string;
  };
}

interface RecoveryStep {
  number: number;
  title: string;
  icon: React.ComponentType<{ size?: number; className?: string }>;
}

const CrossChainRecoveryWizard: React.FC<RecoveryWizardProps> = ({
  isOpen,
  onClose,
  recoveryType,
  contextData
}) => {
  const [step, setStep] = useState(1);
  const [signedMessage, setSignedMessage] = useState('');
  const [walletAddress, setWalletAddress] = useState('');
  const [isProcessing, setIsProcessing] = useState(false);
  const [userEducation, setUserEducation] = useState(true);
  const [requestId, setRequestId] = useState<number | null>(null);
  const [messageToSign, setMessageToSign] = useState('');
  const [signingInstructions, setSigningInstructions] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);

  const steps: RecoveryStep[] = [
    { number: 1, title: 'Recovery Request', icon: ShieldCheckIcon as any },
    { number: 2, title: 'Wallet Verification', icon: KeyIcon as any },
    { number: 3, title: 'Cross-Chain Transfer', icon: ArrowRightLeftIcon as any }
  ];

  const initiateRecovery = async () => {
    setIsProcessing(true);
    setError(null);
    
    try {
      const response = await fetch('/RockyTap/api/wallet-recovery/initiate', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Telegram-Data': (window as any).Telegram?.WebApp?.initData || ''
        },
        body: JSON.stringify({
          recovery_type: recoveryType,
          ...contextData
        })
      });

      const result = await response.json();

      if (result.success && result.data) {
        setRequestId(result.data.request_id);
        setMessageToSign(result.data.sign_message);
        setSigningInstructions(result.data.signing_instructions);
        setStep(2);
        setUserEducation(true);
      } else {
        setError(result.error?.message || 'Failed to initiate recovery');
      }
    } catch (err) {
      setError('Network error. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  const submitSignature = async () => {
    if (!requestId || !signedMessage || !walletAddress) {
      setError('Please provide both signature and wallet address');
      return;
    }

    setIsProcessing(true);
    setError(null);

    try {
      const response = await fetch('/RockyTap/api/wallet-recovery/verify', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Telegram-Data': (window as any).Telegram?.WebApp?.initData || ''
        },
        body: JSON.stringify({
          request_id: requestId,
          signature: signedMessage,
          signed_message: messageToSign,
          wallet_address: walletAddress
        })
      });

      const result = await response.json();

      if (result.success) {
        setStep(3);
      } else {
        setError(result.error?.message || 'Signature verification failed');
      }
    } catch (err) {
      setError('Network error. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleManualVerificationOption = () => {
    return (
      <div className={styles.alternativeMethod}>
        <h4 className={styles.alternativeTitle}>
          <AlertTriangleIcon size={18} />
          Having trouble with wallet signing?
        </h4>
        <p className={styles.alternativeDescription}>
          If you cannot sign the message (e.g., using hardware wallet, wallet app issue), 
          we offer an alternative verification method.
        </p>

        <div className={styles.warningBox}>
          <div className={styles.warningTitle}>
            <AlertTriangleIcon size={16} />
            Security Warning
          </div>
          <p className={styles.warningText}>
            The alternative method requires temporary access to prove wallet ownership.
            Only use this if you absolutely cannot sign the message through your wallet app.
          </p>
        </div>

        <div className={styles.inputGroup}>
          <label className={styles.label}>Wallet Address</label>
          <input
            type="text"
            className={styles.input}
            placeholder="0x1234...5678"
            value={walletAddress}
            onChange={(e) => setWalletAddress(e.target.value)}
          />
        </div>

        <div className={styles.inputGroup}>
          <label className={styles.label}>Signature</label>
          <div className={styles.helpText}>
            Sign the message using your wallet and paste the signature here.
          </div>
          <textarea
            className={styles.textarea}
            rows={4}
            placeholder="0x..."
            value={signedMessage}
            onChange={(e) => setSignedMessage(e.target.value)}
          />
        </div>

        <div className={styles.checkboxGroup}>
          <input type="checkbox" id="understandRisk" />
          <label htmlFor="understandRisk" className={styles.checkboxLabel}>
            I understand this is a sensitive operation and I'm providing my own wallet's signature.
          </label>
        </div>
      </div>
    );
  };

  if (!isOpen) return null;

  return (
    <div className={styles.overlay}>
      <div className={styles.modal}>
        {/* Header */}
        <div className={styles.header}>
          <div>
            <h2 className={styles.title}>
              <ShieldCheckIcon className={styles.titleIcon} />
              Cross-Chain Asset Recovery
            </h2>
            <p className={styles.subtitle}>
              Recover funds sent from incorrect networks or verify wallet ownership
            </p>
          </div>
          <button onClick={onClose} className={styles.closeButton}>
            ✕
          </button>
        </div>

        {/* Progress Steps */}
        <div className={styles.progressSteps}>
          {steps.map((s) => (
            <div key={s.number} className={styles.stepItem}>
              <div className={`${styles.stepCircle} ${step >= s.number ? styles.stepActive : ''}`}>
                {step > s.number ? '✓' : s.number}
              </div>
              <span className={styles.stepLabel}>{s.title}</span>
            </div>
          ))}
        </div>

        {/* Content */}
        <div className={styles.content}>
          {error && (
            <div className={styles.errorBox}>
              <AlertTriangleIcon size={18} />
              {error}
            </div>
          )}

          {step === 1 && (
            <div className={styles.stepContent}>
              <div className={styles.infoBox}>
                <h3 className={styles.infoTitle}>Recovery Scenario Detected</h3>
                <p className={styles.infoText}>
                  Based on your transaction, we detected a cross-chain transfer issue.
                  This system helps recover assets sent from incorrect networks.
                </p>
              </div>

              <div className={styles.detailsGrid}>
                {contextData.transactionHash && (
                  <div className={styles.detailCard}>
                    <div className={styles.detailLabel}>Transaction</div>
                    <div className={styles.detailValue}>{contextData.transactionHash}</div>
                  </div>
                )}
                {contextData.amount && (
                  <div className={styles.detailCard}>
                    <div className={styles.detailLabel}>Amount</div>
                    <div className={styles.detailAmount}>{contextData.amount} USDT</div>
                  </div>
                )}
                {contextData.fromNetwork && (
                  <div className={styles.detailCard}>
                    <div className={styles.detailLabel}>From Network</div>
                    <div className={styles.detailValue}>{contextData.fromNetwork.toUpperCase()}</div>
                  </div>
                )}
                {contextData.toNetwork && (
                  <div className={styles.detailCard}>
                    <div className={styles.detailLabel}>To Network</div>
                    <div className={styles.detailValue}>{contextData.toNetwork.toUpperCase()}</div>
                  </div>
                )}
              </div>

              <button
                onClick={initiateRecovery}
                disabled={isProcessing}
                className={styles.primaryButton}
              >
                {isProcessing ? 'Analyzing Transaction...' : 'Start Recovery Process'}
              </button>
            </div>
          )}

          {step === 2 && (
            <div className={styles.stepContent}>
              {userEducation ? (
                <>
                  <div className={styles.securityBox}>
                    <h3 className={styles.securityTitle}>
                      <KeyIcon size={18} /> Secure Wallet Verification
                    </h3>
                    <p className={styles.securityText}>
                      To recover your funds, we need to verify you control the destination wallet.
                      <strong> Never share your private key or seed phrase.</strong>
                    </p>
                  </div>

                  <div className={styles.instructionsSection}>
                    <h4 className={styles.instructionsTitle}>Recommended Method: Sign a Message</h4>
                    
                    {signingInstructions && (
                      <div className={styles.instructionsList}>
                        <h5 className={styles.walletType}>{signingInstructions.title}</h5>
                        {signingInstructions.steps.map((step: string, idx: number) => (
                          <div key={idx} className={styles.instructionStep}>
                            <div className={styles.stepNumber}>{idx + 1}</div>
                            <div className={styles.stepText}>{step}</div>
                          </div>
                        ))}
                      </div>
                    )}

                    <div className={styles.messageToSign}>
                      <label className={styles.label}>Message to Sign:</label>
                      <pre className={styles.messageBox}>{messageToSign}</pre>
                    </div>
                  </div>

                  <div className={styles.buttonGroup}>
                    <button
                      onClick={() => setUserEducation(false)}
                      className={styles.secondaryButton}
                    >
                      I've signed the message
                    </button>
                  </div>
                </>
              ) : (
                <>
                  {handleManualVerificationOption()}
                  
                  <div className={styles.buttonGroup}>
                    <button
                      onClick={() => setUserEducation(true)}
                      className={styles.secondaryButton}
                    >
                      Back to Instructions
                    </button>
                    <button
                      onClick={submitSignature}
                      disabled={isProcessing || !signedMessage || !walletAddress}
                      className={styles.primaryButton}
                    >
                      {isProcessing ? 'Verifying...' : 'Verify Signature'}
                    </button>
                  </div>
                </>
              )}
            </div>
          )}

          {step === 3 && (
            <div className={styles.stepContent}>
              <div className={styles.successContent}>
                <CheckCircleIcon className={styles.successIcon} />
                <h3 className={styles.successTitle}>Recovery in Progress</h3>
                <p className={styles.successText}>
                  Your cross-chain transfer is being processed. This may take 5-15 minutes.
                </p>
                <div className={styles.estimateBox}>
                  <div className={styles.estimateLabel}>Estimated completion</div>
                  <div className={styles.estimateValue}>~10 minutes</div>
                </div>
                <button onClick={onClose} className={styles.primaryButton}>
                  Close
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CrossChainRecoveryWizard;

