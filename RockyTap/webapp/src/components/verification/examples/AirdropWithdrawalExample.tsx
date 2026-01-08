/**
 * Example: Airdrop Withdrawal Verification Integration
 * 
 * This example shows how to integrate the VerificationModal component
 * into the airdrop withdrawal flow.
 */

import React, { useState } from 'react';
import { VerificationModal } from '../VerificationModal';
import { VerificationSuccess } from '../types';
import { Button } from '../../ui';
import { useToast } from '../../ui';

interface AirdropWithdrawalExampleProps {
  withdrawalAmount: string;
  walletAddress?: string;
  walletNetwork?: 'ERC20' | 'BEP20' | 'TRC20';
  onWithdrawalComplete: () => void;
}

export function AirdropWithdrawalExample({
  withdrawalAmount,
  walletAddress,
  walletNetwork,
  onWithdrawalComplete,
}: AirdropWithdrawalExampleProps) {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { showSuccess } = useToast();

  const handleVerificationSuccess = (result: VerificationSuccess) => {
    showSuccess('Verification successful! Your withdrawal has been processed.');
    setIsModalOpen(false);
    onWithdrawalComplete();
  };

  return (
    <>
      <div style={{ padding: '1rem' }}>
        <h2>Withdraw Airdrop Rewards</h2>
        <p>Amount: ${withdrawalAmount} USDT</p>
        <p>Wallet verification is required for withdrawals.</p>
        <Button
          variant="gold"
          size="lg"
          onClick={() => setIsModalOpen(true)}
        >
          Verify & Withdraw
        </Button>
      </div>

      <VerificationModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        type="airdrop"
        amount={withdrawalAmount}
        walletAddress={walletAddress}
        walletNetwork={walletNetwork}
        onSuccess={handleVerificationSuccess}
        onCancel={() => setIsModalOpen(false)}
      />
    </>
  );
}

