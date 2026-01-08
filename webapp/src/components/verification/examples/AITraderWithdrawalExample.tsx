/**
 * Example: AI Trader Profit Withdrawal Verification Integration
 * 
 * This example shows how to integrate the VerificationModal component
 * into the AI Trader withdrawal flow with enhanced security.
 */

import React, { useState } from 'react';
import { VerificationModal } from '../VerificationModal';
import { VerificationSuccess } from '../types';
import { Button } from '../../ui';
import { useToast } from '../../ui';

interface AITraderWithdrawalExampleProps {
  withdrawalAmount: string;
  walletAddress?: string;
  walletNetwork?: 'ERC20' | 'BEP20' | 'TRC20';
  onWithdrawalComplete: () => void;
}

export function AITraderWithdrawalExample({
  withdrawalAmount,
  walletAddress,
  walletNetwork,
  onWithdrawalComplete,
}: AITraderWithdrawalExampleProps) {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { showSuccess } = useToast();

  const handleVerificationSuccess = (result: VerificationSuccess) => {
    showSuccess('Verification successful! Your AI Trader profits have been withdrawn.');
    setIsModalOpen(false);
    onWithdrawalComplete();
  };

  return (
    <>
      <div style={{ padding: '1rem' }}>
        <h2>Withdraw AI Trader Profits</h2>
        <p>Amount: ${withdrawalAmount} USDT</p>
        <p>
          For security and compliance, wallet verification is required for all AI Trader withdrawals.
        </p>
        <Button
          variant="gold"
          size="lg"
          onClick={() => setIsModalOpen(true)}
        >
          Verify & Withdraw Profits
        </Button>
      </div>

      <VerificationModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        type="ai_trader"
        amount={withdrawalAmount}
        walletAddress={walletAddress}
        walletNetwork={walletNetwork}
        onSuccess={handleVerificationSuccess}
        onCancel={() => setIsModalOpen(false)}
      />
    </>
  );
}

