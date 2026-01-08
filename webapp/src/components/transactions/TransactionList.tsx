import { useState } from 'react';
import { Card, CardContent } from '../ui';
import { TransactionDetailsModal } from './TransactionDetailsModal';
import { formatTransactionType, formatTransactionStatus, formatAmount, formatDate } from './transactionUtils';
import styles from './TransactionList.module.css';

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

interface TransactionListProps {
  transactions: Transaction[];
}

export function TransactionList({ transactions }: TransactionListProps) {
  const [selectedTransaction, setSelectedTransaction] = useState<Transaction | null>(null);

  if (transactions.length === 0) {
    return null;
  }

  return (
    <>
      <div className={styles.list}>
        {transactions.map((transaction) => (
          <Card
            key={transaction.id}
            variant="elevated"
            className={styles.transactionCard}
            onClick={() => setSelectedTransaction(transaction)}
          >
            <CardContent className={styles.transactionContent}>
              <div className={styles.transactionMain}>
                <div className={styles.transactionIcon}>
                  {getTransactionIcon(transaction.type)}
                </div>
                <div className={styles.transactionInfo}>
                  <div className={styles.transactionHeader}>
                    <span className={styles.transactionType}>
                      {formatTransactionType(transaction.type)}
                    </span>
                    <span className={`${styles.transactionStatus} ${styles[transaction.status]}`}>
                      {formatTransactionStatus(transaction.status)}
                    </span>
                  </div>
                  <p className={styles.transactionDescription}>
                    {transaction.description}
                  </p>
                  <span className={styles.transactionDate}>
                    {formatDate(transaction.created_at)}
                  </span>
                </div>
              </div>
              <div className={styles.transactionAmount}>
                <span className={`${styles.amount} ${transaction.amount.startsWith('-') ? styles.negative : styles.positive}`}>
                  {formatAmount(transaction.amount, transaction.currency)}
                </span>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {selectedTransaction && (
        <TransactionDetailsModal
          transaction={selectedTransaction}
          onClose={() => setSelectedTransaction(null)}
        />
      )}
    </>
  );
}

function getTransactionIcon(type: string): string {
  const icons: Record<string, string> = {
    deposit: '💰',
    withdrawal: '💸',
    conversion: '🔄',
    lottery: '🎰',
    ai_trader: '🤖',
    referral: '🎁',
  };
  return icons[type] || '📝';
}

