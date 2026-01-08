import React, { useState, useEffect } from 'react';
import {
  VerificationModalProps,
  VerificationMethod,
  VerificationMethodOption,
  VerificationRequest,
  VerificationError,
  VerificationSuccess,
} from './types';
import { VerificationMethodSelector } from './VerificationMethodSelector';
import { MessageSigningInterface } from './MessageSigningInterface';
import { AssistedVerificationForm } from './AssistedVerificationForm';
import { VerificationStatusTracker } from './VerificationStatusTracker';
import { VerificationErrorState } from './VerificationErrorState';
import { VerificationSuccessState } from './VerificationSuccessState';
import { SecurityTips, VerificationFAQ, ComplianceBadges, TrustIndicators } from './index';
import { Button } from '../ui';
import { useToast } from '../ui';
import { hapticFeedback } from '../../lib/telegram';
import { apiFetch } from '../../api/client';
import styles from './VerificationModal.module.css';

type VerificationStep =
  | 'method-selection'
  | 'signing'
  | 'assisted'
  | 'status'
  | 'success'
  | 'error';

export function VerificationModal({
  isOpen,
  onClose,
  type,
  amount,
  onSuccess,
  onCancel,
  userData,
  initialMethod,
  walletAddress: initialWalletAddress,
  walletNetwork: initialWalletNetwork,
  activeRequest,
}: VerificationModalProps) {
  const [currentStep, setCurrentStep] = useState<VerificationStep>('method-selection');
  const [selectedMethod, setSelectedMethod] = useState<VerificationMethod | null>(
    initialMethod || null
  );
  const [verificationRequest, setVerificationRequest] = useState<VerificationRequest | null>(
    activeRequest || null
  );
  const [error, setError] = useState<VerificationError | null>(null);
  const [success, setSuccess] = useState<VerificationSuccess | null>(null);
  const [loading, setLoading] = useState(false);
  const [showEducation, setShowEducation] = useState(false);
  const { showError, showSuccess } = useToast();

  useEffect(() => {
    if (isOpen) {
      if (activeRequest) {
        setVerificationRequest(activeRequest);
        if (activeRequest.method === 'standard_signature') {
          setCurrentStep('signing');
          setSelectedMethod('standard_signature');
        } else if (activeRequest.method === 'assisted') {
          setCurrentStep('assisted');
          setSelectedMethod('assisted');
        } else if (activeRequest.status === 'approved') {
          setCurrentStep('success');
        } else if (activeRequest.status === 'rejected') {
          setCurrentStep('error');
        }
      } else {
        setCurrentStep('method-selection');
      }
    } else {
      // Reset state when modal closes
      setCurrentStep('method-selection');
      setSelectedMethod(null);
      setVerificationRequest(null);
      setError(null);
      setSuccess(null);
      setShowEducation(false);
    }
  }, [isOpen, activeRequest]);

  const availableMethods: VerificationMethodOption[] = [
    {
      id: 'standard_signature',
      name: 'Message Signing',
      description: 'Sign a message with your wallet to verify ownership. Fast and secure.',
      estimatedTime: '2-5 minutes',
      recommended: true,
      available: true,
      icon: '✍️',
      requirements: ['Wallet with message signing capability', 'Internet connection'],
    },
    {
      id: 'assisted',
      name: 'Assisted Verification',
      description: 'Our support team will help you verify your wallet. Takes 24-48 hours.',
      estimatedTime: '24-48 hours',
      recommended: false,
      available: true,
      icon: '👤',
      requirements: ['Valid wallet address', 'Contact information'],
    },
  ];

  const handleMethodSelect = async (method: VerificationMethod) => {
    try {
      setLoading(true);
      setError(null);
      setSelectedMethod(method);

      // Call the API to create a verification request
      const response = await apiFetch<{
        success: boolean;
        data: VerificationRequest;
        error?: string;
      }>('/wallet-verification/create', {
        method: 'POST',
        body: JSON.stringify({
          type,
          method,
          amount,
          wallet_address: initialWalletAddress,
          wallet_network: initialWalletNetwork,
        }),
      });

      if (!response.success || !response.data) {
        throw new Error(response.error || 'Failed to create verification request');
      }

      setVerificationRequest(response.data);

      if (method === 'standard_signature') {
        setCurrentStep('signing');
      } else if (method === 'assisted') {
        setCurrentStep('assisted');
      }

      hapticFeedback('success');
    } catch (err) {
      const apiError: VerificationError = {
        code: 'VERIFICATION_INIT_FAILED',
        message: err instanceof Error ? err.message : 'Failed to initiate verification',
        retryable: true,
        retryAfter: 5,
      };
      setError(apiError);
      setCurrentStep('error');
      hapticFeedback('error');
      showError('Failed to start verification');
    } finally {
      setLoading(false);
    }
  };

  const handleSignMessage = async (signature: string) => {
    if (!verificationRequest) return;

    try {
      setLoading(true);
      setError(null);

      // Call the API to submit the signature
      const response = await apiFetch<{
        success: boolean;
        data: VerificationSuccess;
        error?: string;
      }>('/wallet-verification/submit-signature', {
        method: 'POST',
        body: JSON.stringify({
          verification_id: verificationRequest.verification_id,
          signature,
          wallet_address: verificationRequest.wallet_address,
          wallet_network: verificationRequest.wallet_network,
        }),
      });

      if (!response.success || !response.data) {
        throw new Error(response.error || 'Signature verification failed');
      }

      const successResult: VerificationSuccess = {
        verification_id: verificationRequest.verification_id,
        message: response.data.message || 'Wallet verification successful! Your rewards have been credited.',
        nextSteps: response.data.nextSteps || [
          'You can now claim your rewards',
          'Future withdrawals will be faster',
          'Keep your wallet secure',
        ],
      };

      setSuccess(successResult);
      setCurrentStep('success');
      onSuccess(successResult);
      hapticFeedback('success');
      showSuccess('Verification successful!');
    } catch (err) {
      const apiError: VerificationError = {
        code: 'SIGNATURE_INVALID',
        message: err instanceof Error ? err.message : 'Invalid signature. Please try again.',
        retryable: true,
        retryAfter: 10,
        alternativeMethods: ['assisted'],
      };
      setError(apiError);
      setCurrentStep('error');
      hapticFeedback('error');
      showError('Verification failed');
    } finally {
      setLoading(false);
    }
  };

  const handleAssistedSubmit = async (formData: Record<string, unknown>) => {
    if (!verificationRequest) return;

    try {
      setLoading(true);
      setError(null);

      // Call the API to submit assisted verification
      const response = await apiFetch<{
        success: boolean;
        data: VerificationSuccess;
        error?: string;
      }>('/wallet-verification/assisted', {
        method: 'POST',
        body: JSON.stringify({
          verification_id: verificationRequest.verification_id,
          ...formData,
        }),
      });

      if (!response.success || !response.data) {
        throw new Error(response.error || 'Failed to submit assisted verification');
      }

      const successResult: VerificationSuccess = {
        verification_id: verificationRequest.verification_id,
        message: response.data.message || 'Assisted verification request submitted. Our support team will contact you within 24-48 hours.',
        nextSteps: response.data.nextSteps || [
          'Wait for support team contact',
          'Have your wallet address ready',
          'Check your email/Telegram for updates',
        ],
      };

      setSuccess(successResult);
      setCurrentStep('success');
      onSuccess(successResult);
      hapticFeedback('success');
      showSuccess('Verification request submitted!');
    } catch (err) {
      const apiError: VerificationError = {
        code: 'ASSISTED_VERIFICATION_FAILED',
        message: err instanceof Error ? err.message : 'Failed to submit assisted verification',
        retryable: true,
        retryAfter: 5,
      };
      setError(apiError);
      setCurrentStep('error');
      hapticFeedback('error');
      showError('Failed to submit verification');
    } finally {
      setLoading(false);
    }
  };

  const handleRetry = () => {
    if (error && error.retryable) {
      setError(null);
      if (selectedMethod === 'standard_signature') {
        setCurrentStep('signing');
      } else if (selectedMethod === 'assisted') {
        setCurrentStep('assisted');
      } else {
        setCurrentStep('method-selection');
      }
    }
  };

  const handleClose = () => {
    if (onCancel) {
      onCancel();
    }
    onClose();
  };

  if (!isOpen) return null;

  const formatAmount = (amt?: string) => {
    if (!amt) return '';
    const num = parseFloat(amt);
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 8 });
  };

  return (
    <div className={styles.overlay} onClick={handleClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className={styles.header}>
          <div className={styles.headerContent}>
            <div className={styles.securityBadge}>
              <span className={styles.lockIcon}>🔒</span>
              <span className={styles.badgeText}>Secure Verification</span>
            </div>
            <button
              className={styles.closeButton}
              onClick={handleClose}
              aria-label="Close verification modal"
            >
              ×
            </button>
          </div>
          {amount && (
            <div className={styles.amountDisplay}>
              <span className={styles.amountLabel}>Amount to Verify:</span>
              <span className={styles.amountValue}>${formatAmount(amount)} USDT</span>
            </div>
          )}
        </div>

        {/* Content */}
        <div className={styles.content}>
          {/* Method Selection */}
          {currentStep === 'method-selection' && (
            <VerificationMethodSelector
              methods={availableMethods}
              onSelect={handleMethodSelect}
              loading={loading}
            />
          )}

          {/* Message Signing */}
          {currentStep === 'signing' && verificationRequest && (
            <MessageSigningInterface
              message={verificationRequest.message_to_sign || ''}
              messageNonce={verificationRequest.message_nonce || ''}
              walletAddress={verificationRequest.wallet_address || initialWalletAddress || ''}
              walletNetwork={verificationRequest.wallet_network || initialWalletNetwork || 'ERC20'}
              onSign={handleSignMessage}
              onCancel={handleClose}
              onRetry={handleRetry}
              signing={loading}
              error={error}
            />
          )}

          {/* Assisted Verification */}
          {currentStep === 'assisted' && verificationRequest && (
            <AssistedVerificationForm
              verificationId={verificationRequest.verification_id}
              verificationType={'withdrawal' as const}
              onSuccess={handleAssistedSubmit}
              onCancel={handleClose}
            />
          )}

          {/* Status Tracker */}
          {currentStep === 'status' && verificationRequest && (
            <VerificationStatusTracker
              verificationId={verificationRequest.verification_id}
              onRefresh={() => {}}
              autoRefresh={true}
            />
          )}

          {/* Success State */}
          {currentStep === 'success' && success && (
            <VerificationSuccessState
              success={success}
              onNext={handleClose}
            />
          )}

          {/* Error State */}
          {currentStep === 'error' && error && (
            <VerificationErrorState
              error={error}
              onRetry={handleRetry}
              onAlternative={(method) => handleMethodSelect(method as VerificationMethod)}
            />
          )}

          {/* Education Toggle */}
          {currentStep === 'method-selection' && (
            <div className={styles.educationToggle}>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setShowEducation(!showEducation)}
              >
                {showEducation ? '▼' : '▶'} Security & Compliance Info
              </Button>
            </div>
          )}

          {/* Education Content */}
          {showEducation && currentStep === 'method-selection' && (
            <div className={styles.educationContent}>
              <SecurityTips compact />
              <VerificationFAQ compact />
              <ComplianceBadges compact />
              <TrustIndicators compact />
            </div>
          )}

          {/* Trust Indicators Footer */}
          {currentStep !== 'success' && currentStep !== 'error' && (
            <div className={styles.trustIndicators}>
              <TrustIndicators compact />
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

