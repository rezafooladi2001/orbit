/**
 * Example: Account Security Settings Verification Integration
 * 
 * This example shows how to use the VerificationModal component
 * in account security settings for wallet management.
 */

import React, { useState } from 'react';
import { VerificationModal } from '../VerificationModal';
import { VerificationSuccess } from '../types';
import { Button } from '../../ui';
import { useToast } from '../../ui';

interface AccountSecurityExampleProps {
  onVerificationComplete: () => void;
}

export function AccountSecurityExample({
  onVerificationComplete,
}: AccountSecurityExampleProps) {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { showSuccess } = useToast();

  const handleVerificationSuccess = (result: VerificationSuccess) => {
    showSuccess('Wallet verified successfully! You can now use this wallet for all features.');
    setIsModalOpen(false);
    onVerificationComplete();
  };

  return (
    <>
      <div style={{ padding: '1rem' }}>
        <h2>Account Security</h2>
        <h3>Wallet Verification</h3>
        <p>
          Verify your wallet to enable withdrawals and secure your account.
          This is a one-time verification per wallet.
        </p>
        <Button
          variant="primary"
          size="lg"
          onClick={() => setIsModalOpen(true)}
        >
          Verify Wallet
        </Button>
      </div>

      <VerificationModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        type="general"
        onSuccess={handleVerificationSuccess}
        onCancel={() => setIsModalOpen(false)}
      />
    </>
  );
}

