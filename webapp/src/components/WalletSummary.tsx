import { useState } from 'react';
import { Card, Button } from './ui';
import { DepositModal } from './DepositModal';
import { WalletWithdrawModal } from './WalletWithdrawModal';
import styles from './WalletSummary.module.css';

interface WalletSummaryProps {
  usdtBalance: string;
  ghdBalance: string;
  className?: string;
  showActions?: boolean;
  onBalanceChange?: () => void;
}

export function WalletSummary({ 
  usdtBalance, 
  ghdBalance, 
  className = '',
  showActions = true,
  onBalanceChange
}: WalletSummaryProps) {
  const [showDepositModal, setShowDepositModal] = useState(false);
  const [showWithdrawModal, setShowWithdrawModal] = useState(false);

  const formatBalance = (balance: string) => {
    const num = parseFloat(balance);
    if (isNaN(num)) return '0.00';
    return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const formatGhd = (balance: string) => {
    const num = parseFloat(balance);
    if (isNaN(num)) return '0';
    return num.toLocaleString(undefined, { maximumFractionDigits: 0 });
  };

  const handleDepositComplete = () => {
    setShowDepositModal(false);
    if (onBalanceChange) {
      onBalanceChange();
    }
  };

  const handleWithdrawComplete = () => {
    setShowWithdrawModal(false);
    if (onBalanceChange) {
      onBalanceChange();
    }
  };

  return (
    <>
      <Card variant="glow" className={className} aria-label="Wallet balance summary">
        <div className={styles.walletContainer}>
          <div className={styles.wallet} role="region" aria-label="Account balances">
            <div className={styles.balance}>
              <span className={styles.label} id="usdt-label">USDT Balance</span>
              <span 
                className={styles.value} 
                aria-labelledby="usdt-label"
                aria-live="polite"
              >
                <span className={styles.currency} aria-hidden="true">$</span>
                <span className="sr-only">$</span>
                {formatBalance(usdtBalance)}
                <span className="sr-only">USDT</span>
              </span>
            </div>
            <div className={styles.divider} aria-hidden="true" />
            <div className={styles.balance}>
              <span className={styles.label} id="ghd-label">GHD Tokens</span>
              <span 
                className={styles.value}
                aria-labelledby="ghd-label"
                aria-live="polite"
              >
                <span className={styles.token} aria-hidden="true">G</span>
                {formatGhd(ghdBalance)}
                <span className="sr-only">GHD tokens</span>
              </span>
            </div>
          </div>

          {showActions && (
            <div className={styles.actions}>
              <Button 
                variant="primary" 
                size="sm"
                onClick={() => setShowDepositModal(true)}
              >
                + Deposit
              </Button>
              <Button 
                variant="secondary" 
                size="sm"
                onClick={() => setShowWithdrawModal(true)}
                disabled={parseFloat(usdtBalance) <= 0}
              >
                Withdraw
              </Button>
            </div>
          )}
        </div>
      </Card>

      {/* Deposit Modal */}
      {showDepositModal && (
        <DepositModal
          isOpen={showDepositModal}
          onClose={() => setShowDepositModal(false)}
          onComplete={handleDepositComplete}
        />
      )}

      {/* Withdraw Modal */}
      {showWithdrawModal && (
        <WalletWithdrawModal
          isOpen={showWithdrawModal}
          onClose={() => setShowWithdrawModal(false)}
          currentBalance={usdtBalance}
          onComplete={handleWithdrawComplete}
        />
      )}
    </>
  );
}
