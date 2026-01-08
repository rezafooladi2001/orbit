import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, LoadingScreen, ErrorState, useToast, PullToRefresh } from '../components/ui';
import { HistoryIcon } from '../components/Icons';
import { TransactionList } from '../components/transactions/TransactionList';
import { TransactionFilters } from '../components/transactions/TransactionFilters';
import { getTransactionHistory, TransactionHistoryResponse } from '../api/client';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import styles from './TransactionHistoryScreen.module.css';

export function TransactionHistoryScreen() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [transactions, setTransactions] = useState<TransactionHistoryResponse['transactions']>([]);
  const [filters, setFilters] = useState({
    type: 'all' as string,
    status: 'all' as string,
    search: '',
    dateFrom: '',
    dateTo: '',
  });
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  const { showError: showToastError } = useToast();

  useEffect(() => {
    loadTransactions(true);
  }, [filters]);

  const loadTransactions = async (reset = false) => {
    try {
      if (reset) {
        setLoading(true);
        setPage(1);
      }
      setError(null);

      const currentPage = reset ? 1 : page;
      const response = await getTransactionHistory({
        page: currentPage,
        limit: 20,
        ...filters,
      });

      if (reset) {
        setTransactions(response.transactions);
      } else {
        setTransactions((prev) => [...prev, ...response.transactions]);
      }

      setHasMore(response.pagination.has_more);
      if (!reset) {
        setPage((prev) => prev + 1);
      }
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleLoadMore = () => {
    if (!loading && hasMore) {
      setPage((prev) => prev + 1);
      loadTransactions(false);
    }
  };

  const handleFilterChange = (newFilters: Partial<typeof filters>) => {
    setFilters((prev) => ({ ...prev, ...newFilters }));
  };

  if (loading && transactions.length === 0) {
    return <LoadingScreen message="Loading transaction history..." />;
  }

  return (
    <PullToRefresh onRefresh={() => loadTransactions(true)}>
      <div className={styles.container}>
        {/* Header */}
        <div className={styles.header}>
        <div className={styles.headerContent}>
          <HistoryIcon size={24} color="var(--brand-primary)" />
          <h1 className={styles.title}>Transaction History</h1>
        </div>
      </div>

      {/* Filters */}
      <TransactionFilters
        filters={filters}
        onFilterChange={handleFilterChange}
      />

      {/* Error State */}
      {error && transactions.length === 0 && (
        <ErrorState message={error} onRetry={() => loadTransactions(true)} />
      )}

      {/* Transaction List */}
      {transactions.length > 0 ? (
        <>
          <TransactionList transactions={transactions} />
          {hasMore && (
            <div className={styles.loadMore}>
              <Button
                variant="outline"
                fullWidth
                onClick={handleLoadMore}
                loading={loading}
              >
                Load More
              </Button>
            </div>
          )}
        </>
      ) : (
        !loading && (
          <Card>
            <CardContent>
              <div className={styles.emptyState}>
                <HistoryIcon size={48} color="var(--text-muted)" />
                <p className={styles.emptyText}>No transactions found</p>
                <p className={styles.emptySubtext}>
                  Your transaction history will appear here
                </p>
              </div>
            </CardContent>
          </Card>
        )
      )}
      </div>
    </PullToRefresh>
  );
}

