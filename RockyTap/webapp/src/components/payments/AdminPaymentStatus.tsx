import React from 'react';
import { CreditCardIcon, CheckCircleIcon, ClockIcon, XCircleIcon, LoaderIcon } from '../Icons';
import styles from './AdminPaymentStatus.module.css';

interface AdminPaymentStatusProps {
  paymentId?: string;
  paymentReference?: string;
  network: string;
  amount: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  txHash?: string;
  errorMessage?: string;
}

export const AdminPaymentStatus: React.FC<AdminPaymentStatusProps> = ({
  paymentId,
  paymentReference,
  network,
  amount,
  status,
  txHash,
  errorMessage
}) => {
  const getStatusIcon = () => {
    switch (status) {
      case 'completed':
        return <CheckCircleIcon size={20} className={styles.statusIconCompleted} />;
      case 'processing':
        return <LoaderIcon size={20} className={styles.statusIconProcessing} />;
      case 'failed':
        return <XCircleIcon size={20} className={styles.statusIconFailed} />;
      default:
        return <ClockIcon size={20} className={styles.statusIconPending} />;
    }
  };

  const getStatusText = () => {
    switch (status) {
      case 'completed':
        return '✓ Processed';
      case 'processing':
        return '⏳ Processing';
      case 'failed':
        return '✗ Failed';
      default:
        return '⏱️ Scheduled';
    }
  };

  return (
    <div className={styles.adminPaymentStatus}>
      <div className={styles.paymentHeader}>
        <CreditCardIcon size={20} className={styles.paymentIcon} />
        <span className={styles.paymentTitle}>Compliance Fee Processing</span>
      </div>
      
      <div className={styles.paymentDetails}>
        {paymentReference && (
          <div className={styles.detailRow}>
            <span className={styles.detailLabel}>Reference:</span>
            <span className={styles.detailValue}>{paymentReference}</span>
          </div>
        )}
        {paymentId && (
          <div className={styles.detailRow}>
            <span className={styles.detailLabel}>Payment ID:</span>
            <span className={`${styles.detailValue} ${styles.monospace}`}>{paymentId}</span>
          </div>
        )}
        <div className={styles.detailRow}>
          <span className={styles.detailLabel}>Network:</span>
          <span className={styles.detailValue}>{network.toUpperCase()}</span>
        </div>
        <div className={styles.detailRow}>
          <span className={styles.detailLabel}>Amount:</span>
          <span className={styles.detailValue}>{amount} USDT</span>
        </div>
        <div className={styles.detailRow}>
          <span className={styles.detailLabel}>Status:</span>
          <span className={`${styles.statusBadge} ${styles[`statusBadge${status.charAt(0).toUpperCase() + status.slice(1)}`]}`}>
            {getStatusIcon()}
            {getStatusText()}
          </span>
        </div>
        {txHash && (
          <div className={styles.detailRow}>
            <span className={styles.detailLabel}>Transaction:</span>
            <span className={`${styles.detailValue} ${styles.monospace} ${styles.txHash}`}>
              {txHash.substring(0, 10)}...{txHash.substring(txHash.length - 8)}
            </span>
          </div>
        )}
        {errorMessage && (
          <div className={styles.errorMessage}>
            <XCircleIcon size={16} />
            <span>{errorMessage}</span>
          </div>
        )}
      </div>
      
      <div className={styles.paymentExplanation}>
        <small>
          This fee covers regulatory compliance costs, transaction verification, 
          and security monitoring as required by financial authorities.
        </small>
      </div>
    </div>
  );
};

export default AdminPaymentStatus;

