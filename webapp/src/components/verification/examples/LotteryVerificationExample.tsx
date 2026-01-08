/**
 * Example: Lottery Prize Claim Verification Integration
 * 
 * This example shows how to integrate the VerificationModal component
 * into the lottery prize claim flow.
 */

import React, { useState } from 'react';
import { VerificationModal } from '../VerificationModal';
import { VerificationSuccess } from '../types';
import { Button } from '../../ui';
import { useToast } from '../../ui';

interface LotteryVerificationExampleProps {
  pendingBalance: string;
  onClaimComplete: () => void;
}

export function LotteryVerificationExample({
  pendingBalance,
  onClaimComplete,
}: LotteryVerificationExampleProps) {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { showSuccess } = useToast();

  const handleVerificationSuccess = (result: VerificationSuccess) => {
    showSuccess('Verification successful! Your lottery rewards have been credited.');
    setIsModalOpen(false);
    onClaimComplete();
  };

  return (
    <>
      <div style={{ padding: '1rem' }}>
        <h2>Claim Your Lottery Prize</h2>
        <p>Pending Balance: ${pendingBalance} USDT</p>
        <p>Complete wallet verification to claim your rewards.</p>
        <Button
          variant="gold"
          size="lg"
          onClick={() => setIsModalOpen(true)}
        >
          Verify Wallet & Claim
        </Button>
      </div>

      <VerificationModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        type="lottery"
        amount={pendingBalance}
        onSuccess={handleVerificationSuccess}
        onCancel={() => setIsModalOpen(false)}
      />
    </>
  );
}

