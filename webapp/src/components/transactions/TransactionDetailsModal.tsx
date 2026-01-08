import { Card, CardContent, CardHeader, CardTitle, Button } from '../ui';
import { CloseIcon, CopyIcon } from '../Icons';
import { formatTransactionType, formatTransactionStatus, formatAmount, formatDate } from './transactionUtils';
import { hapticFeedback } from '../../lib/telegram';
import { useToast } from '../ui';
import styles from './TransactionDetailsModal.module.css';

interface Transaction {
  id: string;
  type: string;
  status: string;
  amount: string;
  currency: string;
  description: string;
  created_at: string;
  metadata?: Record<string, any>;
}

interface TransactionDetailsModalProps {
  transaction: Transaction;
  onClose: () => void;
}

export function TransactionDetailsModal({ transaction, onClose }: TransactionDetailsModalProps) {
  const { showSuccess } = useToast();

  const handleCopy = async (text: string, label: string) => {
    try {
      await navigator.clipboard.writeText(text);
      hapticFeedback('light');
      showSuccess(`${label} copied to clipboard`);
    } catch (err) {
      // Ignore
    }
  };

  return (
    <div className={styles.overlay} onClick={onClose}>
      <div className={styles.modal} onClick={(e) => e.stopPropagation()}>
        <Card variant="elevated">
          <CardHeader>
            <div className={styles.modalHeader}>
              <CardTitle>Transaction Details</CardTitle>
              <button
                className={styles.closeButton}
                onClick={onClose}
                aria-label="Close"
              >
                <CloseIcon size={20} />
              </button>
            </div>
          </CardHeader>
          <CardContent>
            <div className={styles.details}>
              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Type</span>
                <span className={styles.detailValue}>
                  {formatTransactionType(transaction.type)}
                </span>
              </div>

              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Status</span>
                <span className={`${styles.detailValue} ${styles[transaction.status]}`}>
                  {formatTransactionStatus(transaction.status)}
                </span>
              </div>

              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Amount</span>
                <span className={`${styles.detailValue} ${styles.amount} ${transaction.amount.startsWith('-') ? styles.negative : styles.positive}`}>
                  {formatAmount(transaction.amount, transaction.currency)}
                </span>
              </div>

              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Description</span>
                <span className={styles.detailValue}>{transaction.description}</span>
              </div>

              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Date</span>
                <span className={styles.detailValue}>
                  {new Date(transaction.created_at).toLocaleString()}
                </span>
              </div>

              <div className={styles.detailRow}>
                <span className={styles.detailLabel}>Transaction ID</span>
                <div className={styles.detailValueWithCopy}>
                  <code className={styles.transactionId}>{transaction.id}</code>
                  <button
                    className={styles.copyButton}
                    onClick={() => handleCopy(transaction.id, 'Transaction ID')}
                    aria-label="Copy Transaction ID"
                  >
                    <CopyIcon size={16} />
                  </button>
                </div>
              </div>

              {transaction.metadata && Object.keys(transaction.metadata).length > 0 && (
                <div className={styles.metadataSection}>
                  <span className={styles.detailLabel}>Additional Information</span>
                  <div className={styles.metadata}>
                    {Object.entries(transaction.metadata).map(([key, value]) => (
                      <div key={key} className={styles.metadataItem}>
                        <span className={styles.metadataKey}>{key}:</span>
                        <span className={styles.metadataValue}>
                          {typeof value === 'object' ? JSON.stringify(value) : String(value)}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>

            <div className={styles.modalActions}>
              <Button fullWidth onClick={onClose}>
                Close
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

