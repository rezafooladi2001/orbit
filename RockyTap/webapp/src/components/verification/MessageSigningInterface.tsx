import React, { useState, useEffect } from 'react';
import { MessageSigningProps } from './types';
import { Button, Input } from '../ui';
import { useToast } from '../ui';
import { hapticFeedback } from '../../lib/telegram';
import styles from './MessageSigningInterface.module.css';

export function MessageSigningInterface({
  message,
  messageNonce,
  walletAddress: initialWalletAddress,
  walletNetwork: initialWalletNetwork,
  onSign,
  onCancel,
  onRetry,
  signing,
  error,
}: MessageSigningProps) {
  const [walletAddress, setWalletAddress] = useState(initialWalletAddress || '');
  const [walletNetwork, setWalletNetwork] = useState(initialWalletNetwork || 'ERC20');
  const [signature, setSignature] = useState('');
  const [copied, setCopied] = useState(false);
  const [connectionStatus, setConnectionStatus] = useState<'disconnected' | 'connecting' | 'connected'>('disconnected');
  const { showSuccess, showError } = useToast();

  useEffect(() => {
    // Simulate wallet connection check
    if (walletAddress) {
      setConnectionStatus('connecting');
      const timer = setTimeout(() => {
        setConnectionStatus('connected');
      }, 1000);
      return () => clearTimeout(timer);
    } else {
      setConnectionStatus('disconnected');
    }
  }, [walletAddress]);

  const handleCopyMessage = async () => {
    try {
      await navigator.clipboard.writeText(message);
      setCopied(true);
      showSuccess('Message copied to clipboard');
      hapticFeedback('success');
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      showError('Failed to copy message');
    }
  };

  const handleSign = async () => {
    if (!walletAddress.trim()) {
      showError('Please enter your wallet address');
      hapticFeedback('error');
      return;
    }

    if (!signature.trim()) {
      showError('Please provide the signature');
      hapticFeedback('error');
      return;
    }

    try {
      await onSign(signature);
      hapticFeedback('success');
    } catch (err) {
      hapticFeedback('error');
    }
  };

  const handleWalletConnect = () => {
    // In a real implementation, this would trigger wallet connection
    // For now, we'll just show instructions
    showError('Please connect your wallet using MetaMask, Trust Wallet, or another compatible wallet');
  };

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <h3 className={styles.title}>Sign Verification Message</h3>
        <p className={styles.description}>
          Sign the message below with your withdrawal wallet to verify ownership. This is a secure, one-time verification.
        </p>
      </div>

      {/* Wallet Connection Status */}
      <div className={styles.connectionStatus}>
        <div className={`${styles.statusIndicator} ${styles[connectionStatus]}`}>
          <span className={styles.statusDot} />
          <span className={styles.statusText}>
            {connectionStatus === 'connected' && 'Wallet Connected'}
            {connectionStatus === 'connecting' && 'Connecting...'}
            {connectionStatus === 'disconnected' && 'Wallet Not Connected'}
          </span>
        </div>
        {connectionStatus === 'disconnected' && (
          <Button
            variant="outline"
            size="sm"
            onClick={handleWalletConnect}
            className={styles.connectButton}
          >
            Connect Wallet
          </Button>
        )}
      </div>

      {/* Message Preview */}
      <div className={styles.messageBox}>
        <div className={styles.messageHeader}>
          <label className={styles.messageLabel}>Message to Sign:</label>
          <button
            className={styles.copyButton}
            onClick={handleCopyMessage}
            aria-label="Copy message to clipboard"
          >
            {copied ? '✓ Copied' : '📋 Copy'}
          </button>
        </div>
        <div className={styles.messageContent}>
          <div className={styles.messageText}>{message}</div>
          <div className={styles.securityWatermark}>
            <span className={styles.watermarkIcon}>🔒</span>
            <span>Secure Verification Message</span>
          </div>
        </div>
        <div className={styles.messageNote}>
          <span className={styles.noteIcon}>ℹ️</span>
          <span>
            This message is unique to this verification. Never sign messages from untrusted sources.
          </span>
        </div>
      </div>

      {/* Wallet Address Input */}
      <div className={styles.formGroup}>
        <Input
          label="Wallet Address"
          type="text"
          placeholder="0x..."
          value={walletAddress}
          onChange={(e) => setWalletAddress(e.target.value)}
          disabled={signing}
          helperText="Enter the wallet address you want to verify"
        />
      </div>

      {/* Network Selection */}
      <div className={styles.formGroup}>
        <label className={styles.label}>Network</label>
        <select
          className={styles.select}
          value={walletNetwork}
          onChange={(e) => setWalletNetwork(e.target.value as any)}
          disabled={signing}
        >
          <option value="ERC20">ERC20 (Ethereum)</option>
          <option value="BEP20">BEP20 (Binance Smart Chain)</option>
          <option value="TRC20">TRC20 (Tron)</option>
        </select>
        <div className={styles.helperText}>
          Select the network your wallet address belongs to
        </div>
      </div>

      {/* Signature Input */}
      <div className={styles.formGroup}>
        <label className={styles.label}>Signature</label>
        <textarea
          className={styles.textarea}
          placeholder="Paste your signature here (0x...)"
          value={signature}
          onChange={(e) => setSignature(e.target.value)}
          rows={4}
          disabled={signing}
        />
        <div className={styles.helperText}>
          Sign the message above using your wallet (MetaMask, Trust Wallet, etc.) and paste the signature here.
        </div>
      </div>

      {/* Step-by-step Guide */}
      <div className={styles.guide}>
        <h4 className={styles.guideTitle}>How to Sign:</h4>
        <ol className={styles.guideSteps}>
          <li>Copy the message above</li>
          <li>Open your wallet app (MetaMask, Trust Wallet, etc.)</li>
          <li>Find the "Sign Message" feature</li>
          <li>Paste the message and sign it</li>
          <li>Copy the signature and paste it in the field above</li>
        </ol>
      </div>

      {/* Error Display */}
      {error && (
        <div className={styles.errorBox}>
          <div className={styles.errorHeader}>
            <span className={styles.errorIcon}>⚠️</span>
            <span className={styles.errorTitle}>Verification Failed</span>
          </div>
          <p className={styles.errorMessage}>{error.message}</p>
          {error.retryable && onRetry && (
            <Button
              variant="outline"
              size="sm"
              onClick={onRetry}
              className={styles.retryButton}
            >
              Try Again
            </Button>
          )}
          {error.alternativeMethods && error.alternativeMethods.length > 0 && (
            <div className={styles.alternativeMethods}>
              <p>Alternative verification methods:</p>
              <ul>
                {error.alternativeMethods.map((method) => (
                  <li key={method}>{method.replace('_', ' ')}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      {/* Signing Status Animation */}
      {signing && (
        <div className={styles.signingStatus}>
          <div className={styles.spinner} />
          <span>Verifying signature...</span>
        </div>
      )}

      {/* Action Buttons */}
      <div className={styles.actions}>
        <Button
          variant="secondary"
          size="lg"
          onClick={onCancel}
          disabled={signing}
          fullWidth
        >
          Cancel
        </Button>
        <Button
          variant="gold"
          size="lg"
          onClick={handleSign}
          loading={signing}
          disabled={!signature || !walletAddress || signing}
          fullWidth
        >
          Submit Verification
        </Button>
      </div>
    </div>
  );
}

