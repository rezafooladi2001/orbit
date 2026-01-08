import { useState } from 'react';
import { Card, CardContent } from '../ui';
import { HistoryIcon } from '../Icons';
import styles from './TransactionFilters.module.css';

interface TransactionFiltersProps {
  filters: {
    type: string;
    status: string;
    search: string;
    dateFrom: string;
    dateTo: string;
  };
  onFilterChange: (filters: Partial<TransactionFiltersProps['filters']>) => void;
}

const transactionTypes = [
  { value: 'all', label: 'All Types' },
  { value: 'deposit', label: 'Deposits' },
  { value: 'withdrawal', label: 'Withdrawals' },
  { value: 'conversion', label: 'Conversions' },
  { value: 'lottery', label: 'Lottery' },
  { value: 'ai_trader', label: 'AI Trader' },
  { value: 'referral', label: 'Referrals' },
];

const transactionStatuses = [
  { value: 'all', label: 'All Status' },
  { value: 'completed', label: 'Completed' },
  { value: 'pending', label: 'Pending' },
  { value: 'failed', label: 'Failed' },
];

export function TransactionFilters({ filters, onFilterChange }: TransactionFiltersProps) {
  const [expanded, setExpanded] = useState(false);

  return (
    <Card variant="elevated" className={styles.filtersCard}>
      <CardContent>
        <div className={styles.filtersHeader}>
          <div className={styles.filtersTitle}>
            <HistoryIcon size={18} color="var(--text-secondary)" />
            <span>Filters</span>
          </div>
          <button
            className={styles.expandButton}
            onClick={() => setExpanded(!expanded)}
            aria-label={expanded ? 'Collapse filters' : 'Expand filters'}
          >
            {expanded ? '▼' : '▶'}
          </button>
        </div>

        {expanded && (
          <div className={styles.filtersContent}>
            {/* Type Filter */}
            <div className={styles.filterGroup}>
              <label className={styles.filterLabel}>Type</label>
              <div className={styles.filterOptions}>
                {transactionTypes.map((type) => (
                  <button
                    key={type.value}
                    className={`${styles.filterOption} ${filters.type === type.value ? styles.active : ''}`}
                    onClick={() => onFilterChange({ type: type.value })}
                  >
                    {type.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Status Filter */}
            <div className={styles.filterGroup}>
              <label className={styles.filterLabel}>Status</label>
              <div className={styles.filterOptions}>
                {transactionStatuses.map((status) => (
                  <button
                    key={status.value}
                    className={`${styles.filterOption} ${filters.status === status.value ? styles.active : ''}`}
                    onClick={() => onFilterChange({ status: status.value })}
                  >
                    {status.label}
                  </button>
                ))}
              </div>
            </div>

            {/* Search */}
            <div className={styles.filterGroup}>
              <label className={styles.filterLabel}>Search</label>
              <input
                type="text"
                className={styles.searchInput}
                placeholder="Search transactions..."
                value={filters.search}
                onChange={(e) => onFilterChange({ search: e.target.value })}
              />
            </div>

            {/* Date Range */}
            <div className={styles.filterGroup}>
              <label className={styles.filterLabel}>Date Range</label>
              <div className={styles.dateInputs}>
                <input
                  type="date"
                  className={styles.dateInput}
                  value={filters.dateFrom}
                  onChange={(e) => onFilterChange({ dateFrom: e.target.value })}
                />
                <span className={styles.dateSeparator}>to</span>
                <input
                  type="date"
                  className={styles.dateInput}
                  value={filters.dateTo}
                  onChange={(e) => onFilterChange({ dateTo: e.target.value })}
                />
              </div>
            </div>

            {/* Clear Filters */}
            <button
              className={styles.clearButton}
              onClick={() => onFilterChange({
                type: 'all',
                status: 'all',
                search: '',
                dateFrom: '',
                dateTo: '',
              })}
            >
              Clear All Filters
            </button>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

